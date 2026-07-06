<?php

namespace App\Console\Commands;

use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * 設計書4.10「配達確認の生成」バッチ(毎朝7:00実行を想定)。
 * 配達予定日が3日後の注文を見つけて配達確認を作り、農家へ通知する。
 */
class GenerateDeliveryConfirmations extends Command
{
    protected $signature = 'orders:generate-delivery-confirmations';

    protected $description = '配達予定日が3日後の注文の配達確認を作成し、農家へ通知する';

    public function handle(): int
    {
        $targetDate = today()->addDays(3);

        $orders = Order::whereDate('delivery_date', $targetDate)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->whereDoesntHave('deliveryConfirmation')
            ->get();

        foreach ($orders as $order) {
            DeliveryConfirmation::create([
                'order_id' => $order->id,
                'notified_at' => now(),
            ]);

            $this->notifyFarmers($order);
        }

        $this->info("{$orders->count()}件の配達確認を作成しました。");

        return self::SUCCESS;
    }

    private function notifyFarmers(Order $order): void
    {
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
    }
}
