<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProductSale;
use App\Services\DeliveryConfirmationService;
use Illuminate\Console\Command;

/**
 * 設計書4.10「配達確認の生成」バッチ(毎朝7:00実行を想定)。
 * 配達予定日が3日後の注文のうち、配達確認が必要(requires_delivery_confirmation)かつ
 * 固定配達日(delivery_date_type=fixed)のものを見つけて配達確認を作り、農家へ通知する。
 * 当日・翌日配達(delivery_date_type=auto)は3日前バッチでは間に合わないため対象外
 * (OrderPlacementServiceが注文確定直後に作成済み)。
 */
class GenerateDeliveryConfirmations extends Command
{
    protected $signature = 'orders:generate-delivery-confirmations';

    protected $description = '配達予定日が3日後の注文の配達確認を作成し、農家へ通知する';

    public function __construct(private readonly DeliveryConfirmationService $deliveryConfirmationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetDate = today()->addDays(3);

        $orders = Order::whereDate('delivery_date', $targetDate)
            ->where('status', Order::STATUS_RECEIVED)
            ->whereDoesntHave('deliveryConfirmation')
            ->whereHas('orderItems.productSale', function ($query) {
                $query->where('requires_delivery_confirmation', true)
                    ->where('delivery_date_type', ProductSale::DELIVERY_DATE_TYPE_FIXED);
            })
            ->get();

        foreach ($orders as $order) {
            $this->deliveryConfirmationService->createForOrder($order);
        }

        $this->info("{$orders->count()}件の配達確認を作成しました。");

        return self::SUCCESS;
    }
}
