<?php

namespace App\Console\Commands;

use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * 設計書4.10「未回答リマインド」バッチ(毎夕17:00実行を想定)。
 * まだ回答されていない配達確認について、農家へ再通知する。
 */
class RemindUnansweredDeliveryConfirmations extends Command
{
    protected $signature = 'orders:remind-unanswered-delivery-confirmations';

    protected $description = '未回答の配達確認について、農家へ再通知する';

    public function handle(): int
    {
        $unanswered = DeliveryConfirmation::whereNull('responded_at')->with('order')->get();

        $farmers = User::where('role', User::ROLE_FARMER)->get();

        foreach ($unanswered as $confirmation) {
            $order = $confirmation->order;

            foreach ($farmers as $farmer) {
                Notification::create([
                    'user_id' => $farmer->id,
                    'type' => '配達確認依頼',
                    'title' => 'まだ回答されていない配達確認があります',
                    'body' => $order->delivery_date->format('n月j日')."に配達予定の商品(注文番号 {$order->order_number})の確認がまだ済んでいません。",
                    'related_order_id' => $order->id,
                    'is_read' => false,
                ]);
            }
        }

        $this->info("{$unanswered->count()}件の未回答配達確認について、農家へ再通知しました。");

        return self::SUCCESS;
    }
}
