<?php

namespace App\Services;

use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;

/**
 * 配達確認の生成+農家への通知を1か所にまとめる。
 * 3日前バッチ(GenerateDeliveryConfirmations)と、当日・翌日配達の即時生成(OrderPlacementService)の
 * 両方から同じロジックを使うための共通クラス。
 */
class DeliveryConfirmationService
{
    public function createForOrder(Order $order): DeliveryConfirmation
    {
        $confirmation = DeliveryConfirmation::create([
            'order_id' => $order->id,
            'notified_at' => now(),
        ]);

        $farmers = User::where('role', User::ROLE_FARMER)->get();

        foreach ($farmers as $farmer) {
            Notification::create([
                'user_id' => $farmer->id,
                'type' => '配達確認依頼',
                'title' => '配達予定の確認をお願いします',
                'body' => $order->delivery_date->format('n月j日')."に配達予定の商品があります。内容を確認してください。(注文番号 {$order->order_number})",
                'related_order_id' => $order->id,
                'is_read' => false,
            ]);
        }

        return $confirmation;
    }
}
