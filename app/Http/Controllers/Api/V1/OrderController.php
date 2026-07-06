<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * 注文履歴。データは削除せず蓄積し続ける前提で、以下の3パターンに対応する。
     * - パラメータなし: 直近6か月分(履歴ページを開いたときの初期表示)
     * - year のみ: 指定年の全件(去年以前をプルダウンで選んだ場合)
     * - year + month: 指定年月分(今年以内を月のプルダウンで選んだ場合)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->orders()->with('orderItems');

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;

        if ($year !== null && $month !== null) {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } elseif ($year !== null) {
            $query->whereYear('created_at', $year);
        } else {
            $query->where('created_at', '>=', now()->subMonths(6));
        }

        return response()->json(['orders' => $query->latest()->get()]);
    }

    /**
     * 注文詳細。自分の注文以外は見せない。
     */
    public function show(Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        return response()->json(['order' => $order->load('orderItems')]);
    }

    /**
     * 「前回と同じ内容で注文」。過去の注文明細と同じ商品・数量で、
     * 今の価格・在庫を使って確認画面用データを作り直す。
     */
    public function reorderPreview(Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        $orderItem = $order->orderItems->first();
        $productSale = ProductSale::with('product')->find($orderItem->product_sale_id);

        if (! $productSale) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_AVAILABLE',
                    'message' => 'この商品は現在お取り扱いしていません。',
                ],
            ], 422);
        }

        if ($error = $this->availabilityError($productSale, $orderItem->quantity)) {
            return response()->json(['error' => $error], 422);
        }

        return response()->json([
            'order_preview' => $this->buildPreview(
                $productSale,
                $orderItem->quantity,
                $order->delivery_time_slot,
                Auth::user()
            ),
        ]);
    }

    /**
     * 注文確認画面用。在庫を減らさずに、金額と配達予定を検証して返す。
     */
    public function preview(PlaceOrderRequest $request): JsonResponse
    {
        $productSale = ProductSale::with('product')->findOrFail($request->input('product_sale_id'));
        $quantity = (int) $request->input('quantity');

        if ($error = $this->availabilityError($productSale, $quantity)) {
            return response()->json(['error' => $error], 422);
        }

        return response()->json([
            'order_preview' => $this->buildPreview(
                $productSale,
                $quantity,
                $request->input('delivery_time_slot'),
                Auth::user()
            ),
        ]);
    }

    /**
     * 注文確定。設計書3.4の手順(行ロック→在庫確認→減算→注文作成→通知作成)通りに、
     * 1つのDBトランザクションの中で処理する。
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $quantity = (int) $request->input('quantity');
        $user = Auth::user();

        try {
            $order = DB::transaction(function () use ($request, $quantity, $user) {
                // lockForUpdate()で対象行に「行ロック」をかける。
                // このロックが外れるまで、他のリクエストは同じ行の続きの処理を待たされるため、
                // 同時に注文しても在庫がマイナスになることはない。
                $productSale = ProductSale::where('id', $request->input('product_sale_id'))
                    ->lockForUpdate()
                    ->with('product')
                    ->firstOrFail();

                if ($error = $this->availabilityError($productSale, $quantity)) {
                    throw new OrderPlacementException($error['message'], $error['code']);
                }

                $productSale->decrement('stock_quantity', $quantity);

                if ($productSale->stock_quantity === 0) {
                    $productSale->update(['status' => ProductSale::STATUS_SOLD_OUT]);
                }

                $subtotal = $productSale->price * $quantity;

                $order = Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    'user_id' => $user->id,
                    'status' => Order::STATUS_RECEIVED,
                    'total_amount' => $subtotal,
                    'delivery_address' => $user->address,
                    'delivery_date' => $productSale->delivery_date_from,
                    'delivery_time_slot' => $request->input('delivery_time_slot'),
                    'is_proxy_order' => false,
                ]);

                $order->orderItems()->create([
                    'product_sale_id' => $productSale->id,
                    'product_name' => $productSale->product->name,
                    'unit_price' => $productSale->price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                $this->createOrderNotifications($order, $user);

                return $order;
            });
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

    /**
     * 注文番号を発番する(例: 20260704-0012)。当日の注文件数+1を4桁で採番する。
     */
    private function generateOrderNumber(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = Order::whereDate('created_at', today())->count() + 1;

        return $datePart.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 注文受付を購入者へ、新規注文を農家へ通知する。
     */
    private function createOrderNotifications(Order $order, User $buyer): void
    {
        Notification::create([
            'user_id' => $buyer->id,
            'type' => '注文受付',
            'title' => 'ご注文を受け付けました',
            'body' => "注文番号 {$order->order_number} を受け付けました。配達予定日は".$order->delivery_date->format('n月j日')."です。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);

        $farmers = User::where('role', User::ROLE_FARMER)->get();

        foreach ($farmers as $farmer) {
            Notification::create([
                'user_id' => $farmer->id,
                'type' => '新規注文',
                'title' => '新しい注文が入りました',
                'body' => "注文番号 {$order->order_number}({$buyer->name}様)",
                'related_order_id' => $order->id,
                'is_read' => false,
            ]);
        }
    }

    /**
     * ログイン中の利用者本人の注文かどうかを確認する。
     *
     * @return array<string, string>|null
     */
    private function ownershipError(Order $order): ?array
    {
        if ($order->user_id !== Auth::id()) {
            return [
                'code' => 'FORBIDDEN',
                'message' => 'この注文は閲覧できません。',
            ];
        }

        return null;
    }

    /**
     * 在庫が足りているか、販売中かどうかを確認する。
     * 問題があればエラー内容を、問題無ければnullを返す。
     *
     * @return array<string, string>|null
     */
    private function availabilityError(ProductSale $productSale, int $quantity): ?array
    {
        if ($productSale->status !== ProductSale::STATUS_ON_SALE || ! $productSale->is_reservation_open) {
            return [
                'code' => 'NOT_AVAILABLE',
                'message' => '現在この商品は注文を受け付けていません。',
            ];
        }

        if ($productSale->stock_quantity < $quantity) {
            return [
                'code' => 'OUT_OF_STOCK',
                'message' => '申し訳ありません。売り切れました。',
            ];
        }

        return null;
    }

    /**
     * 確認画面に表示するためのデータをまとめる。
     *
     * @return array<string, mixed>
     */
    private function buildPreview(ProductSale $productSale, int $quantity, ?string $deliveryTimeSlot, User $user): array
    {
        $subtotal = $productSale->price * $quantity;

        return [
            'product_sale_id' => $productSale->id,
            'product_name' => $productSale->product->name,
            'unit_price' => $productSale->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'delivery_address' => $user->address,
            'delivery_date' => $productSale->delivery_date_from,
            'delivery_time_slot' => $deliveryTimeSlot,
            'delivery_note' => $productSale->delivery_note,
        ];
    }
}
