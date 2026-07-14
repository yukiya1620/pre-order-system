<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\CompleteOrderRequest;
use App\Http\Requests\Farmer\PlaceProxyOrderRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderPlacementService $orderPlacementService)
    {
    }

    /**
     * 予約一覧(配達日順)。状態(status)・商品(product_id)で絞り込み可能。
     * product_idはorders自体には持たせていないので、order_items→product_sales経由でたどる。
     * active_only=1で「配達完了・キャンセルを除いた未完了のみ」に絞り込める(F3一覧の初期表示用。
     * 後方互換のため未指定時は従来通り全ステータスを返す)。
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['user:id,name,phone_number,address', 'orderItems.productSale.product', 'deliveryConfirmation'])
            ->when($request->boolean('active_only'), function ($query) {
                $query->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED]);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $productId = (int) $request->input('product_id');
                $query->whereHas('orderItems.productSale', function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                });
            })
            ->orderBy('delivery_date')
            ->paginate(20);

        return response()->json(['orders' => $orders]);
    }

    /**
     * 注文詳細。農家は全購入者の注文を見る役割なので、購入者向けshow()と違い
     * 「自分の注文かどうか」のチェックは行わない(routes/api.phpのfarmerミドルウェアで守る)。
     * userはF4画面表示に必要なid・name・phone_numberだけに絞る(email・notify設定等は不要)。
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'order' => $order->load(['user:id,name,phone_number', 'orderItems.productSale.product', 'deliveryConfirmation']),
        ]);
    }

    /**
     * 配達完了への更新+支払い状態の記録。
     * すでに配達完了の注文への再リクエストはエラーにせず、支払い情報の修正だけ反映する
     * (べき等にする。誤操作で2回押されても購入者へ重複通知が飛ばないように)。
     */
    public function complete(CompleteOrderRequest $request, Order $order): JsonResponse
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json([
                'error' => [
                    'code' => 'ORDER_CANCELLED',
                    'message' => 'キャンセル済みの注文は配達完了にできません。',
                ],
            ], 422);
        }

        $wasAlreadyDelivered = $order->status === Order::STATUS_DELIVERED;

        $paymentStatus = $request->input('payment_status');

        $order->status = Order::STATUS_DELIVERED;
        $order->payment_status = $paymentStatus;

        if ($request->filled('payment_method')) {
            $order->payment_method = $request->input('payment_method');
        }

        if ($paymentStatus === Order::PAYMENT_STATUS_PAID) {
            $order->paid_at = now();
        } elseif ($paymentStatus === Order::PAYMENT_STATUS_UNPAID) {
            $order->paid_at = null;
        }
        // refundedの場合はpaid_atを現状維持する

        $order->save();

        if (! $wasAlreadyDelivered) {
            Notification::create([
                'user_id' => $order->user_id,
                'type' => '配達完了',
                'title' => '配達が完了しました',
                'body' => "注文番号 {$order->order_number} の配達が完了しました。ご利用ありがとうございました。",
                'related_order_id' => $order->id,
                'is_read' => false,
            ]);
        }

        return response()->json([
            'order' => $order->fresh(['user', 'orderItems.productSale.product', 'deliveryConfirmation']),
        ]);
    }

    /**
     * 電話注文の代理入力。電話番号で既存購入者を探し、いなければその場で簡易登録する
     * (SmsAuthController::verify()と同じ「見つからなければ登録」のパターン)。
     * 注文本体の処理は、通常注文と全く同じOrderPlacementServiceに任せる。
     */
    public function store(PlaceProxyOrderRequest $request): JsonResponse
    {
        $phoneNumber = $request->string('phone_number')->toString();
        $buyer = User::where('phone_number', $phoneNumber)->first();

        if (! $buyer) {
            if (blank($request->input('name')) || blank($request->input('address'))) {
                return response()->json([
                    'error' => [
                        'code' => 'REGISTRATION_INFO_REQUIRED',
                        'message' => '初めての購入者です。お名前とご住所を入力してください。',
                    ],
                ], 422);
            }

            $buyer = User::create([
                'role' => User::ROLE_BUYER,
                'name' => $request->input('name'),
                'phone_number' => $phoneNumber,
                'address' => $request->input('address'),
                'is_active' => true,
            ]);
        }

        try {
            $order = $this->orderPlacementService->place(
                $buyer,
                (int) $request->input('product_sale_id'),
                (int) $request->input('quantity'),
                $request->input('delivery_time_slot'),
                [
                    'is_proxy_order' => true,
                    'proxy_note' => $request->input('proxy_note'),
                    'payment_method' => $request->input('payment_method', Order::PAYMENT_METHOD_CASH),
                    'payment_status' => $request->input('payment_status', Order::PAYMENT_STATUS_UNPAID),
                ]
            );
        } catch (OrderPlacementException $exception) {
            return response()->json([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        return response()->json(['order' => $order->load('orderItems')], 201);
    }
}
