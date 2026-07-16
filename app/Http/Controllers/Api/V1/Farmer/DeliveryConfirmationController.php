<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\RespondDeliveryConfirmationRequest;
use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeliveryConfirmationController extends Controller
{
    /**
     * 未回答かつ注文が受付済のままの配達確認一覧(通知が古い順)。
     * 配達完了・キャンセル済みの注文に紐づく確認は、回答してもステータスを
     * 後退させるだけになるため一覧に出さない(受付済のみを許可するallowlist方式)。
     */
    public function index(): JsonResponse
    {
        $confirmations = DeliveryConfirmation::with('order.user', 'order.orderItems')
            ->whereNull('responded_at')
            ->whereHas('order', function ($query) {
                $query->where('status', Order::STATUS_RECEIVED);
            })
            ->orderBy('notified_at')
            ->get();

        return response()->json(['delivery_confirmations' => $confirmations]);
    }

    /**
     * 配達確認への回答。注文の状態を更新し、購入者へ自動通知する。
     *
     * 受付済の時点で配達確認が生成された後、農家がF3から先に配達完了/キャンセルに
     * してしまうと、未回答の確認だけが残ることがある。そのまま回答を許すと注文の
     * ステータスが配達完了/キャンセルから後退してしまうため、ロック取得後に
     * 注文が受付済のままかを再検証する(数量減少・キャンセルのOrderAdjustmentServiceと
     * 同じ「ロック後に再検証」の考え方)。
     */
    public function respond(RespondDeliveryConfirmationRequest $request, DeliveryConfirmation $deliveryConfirmation): JsonResponse
    {
        return DB::transaction(function () use ($request, $deliveryConfirmation) {
            $lockedConfirmation = DeliveryConfirmation::where('id', $deliveryConfirmation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::where('id', $lockedConfirmation->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedConfirmation->responded_at !== null) {
                return response()->json([
                    'error' => [
                        'code' => 'ALREADY_RESPONDED',
                        'message' => 'この配達確認はすでに回答済みです。',
                    ],
                ], 422);
            }

            if ($order->status !== Order::STATUS_RECEIVED) {
                return response()->json([
                    'error' => [
                        'code' => 'ORDER_NOT_RESPONDABLE',
                        'message' => '受付済の注文以外には回答できません。',
                    ],
                ], 422);
            }

            $response = $request->input('response');

            $lockedConfirmation->update([
                'response' => $response,
                'new_delivery_date' => $request->input('new_delivery_date'),
                'response_note' => $request->input('response_note'),
                'responded_at' => now(),
                'buyer_notified_at' => now(),
            ]);

            if ($response === '配達可能') {
                $order->update(['status' => Order::STATUS_DELIVERY_CONFIRMED]);
            } elseif ($response === '配達日変更') {
                $order->update([
                    'delivery_date' => $request->input('new_delivery_date'),
                    'status' => Order::STATUS_DELIVERY_CHANGED,
                ]);
            }

            $this->notifyBuyer($lockedConfirmation, $order);

            return response()->json([
                'delivery_confirmation' => $lockedConfirmation->fresh()->load('order'),
            ]);
        });
    }

    private function notifyBuyer(DeliveryConfirmation $confirmation, Order $order): void
    {
        // 設計書のnotifications.type一覧には「数量変更」「キャンセル相談」専用の種別が無いため、
        // 配達日が変わる場合だけ「配達予定変更」、それ以外は「配達予定確認」として通知する。
        $type = $confirmation->response === '配達日変更' ? '配達予定変更' : '配達予定確認';

        $body = match ($confirmation->response) {
            '配達可能' => "注文番号 {$order->order_number} は予定通り".$order->delivery_date->format('n月j日').'に配達予定です。',
            '配達日変更' => "注文番号 {$order->order_number} の配達予定日が".$order->delivery_date->format('n月j日').'に変更になりました。',
            '数量変更' => "注文番号 {$order->order_number} について、数量のご相談があります。農家からの連絡をお待ちください。",
            'キャンセル相談' => "注文番号 {$order->order_number} について、キャンセルのご相談があります。農家からの連絡をお待ちください。",
            default => "注文番号 {$order->order_number} について、ご相談があります。農家からの連絡をお待ちください。",
        };

        if ($confirmation->response_note) {
            $body .= "\n".$confirmation->response_note;
        }

        Notification::create([
            'user_id' => $order->user_id,
            'type' => $type,
            'title' => $type === '配達予定変更' ? '配達予定日が変更になりました' : '配達予定についてのお知らせ',
            'body' => $body,
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }
}
