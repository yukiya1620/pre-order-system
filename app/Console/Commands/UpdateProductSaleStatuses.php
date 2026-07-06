<?php

namespace App\Console\Commands;

use App\Models\ProductSale;
use Illuminate\Console\Command;

/**
 * 設計書4.10「販売期間の自動更新」バッチ(毎日0:05実行を想定)。
 * sale_start_date/sale_end_dateと今日の日付から、準備中/販売中/販売終了を判定し直す。
 */
class UpdateProductSaleStatuses extends Command
{
    protected $signature = 'orders:update-product-sale-statuses';

    protected $description = '販売シーズンの状態(準備中/販売中/販売終了)を日付に基づいて自動更新する';

    public function handle(): int
    {
        $updated = 0;

        ProductSale::where('status', '!=', ProductSale::STATUS_ENDED)->each(function (ProductSale $sale) use (&$updated) {
            // 売り切れは在庫の状態なので日付では上書きしない。ただし販売期間が終わっていれば販売終了にする
            if ($sale->status === ProductSale::STATUS_SOLD_OUT) {
                if (today()->gt($sale->sale_end_date)) {
                    $sale->update(['status' => ProductSale::STATUS_ENDED]);
                    $updated++;
                }

                return;
            }

            $newStatus = ProductSale::determineStatus($sale->sale_start_date, $sale->sale_end_date);

            if ($newStatus !== $sale->status) {
                $sale->update(['status' => $newStatus]);
                $updated++;
            }
        });

        $this->info("{$updated}件の販売シーズンの状態を更新しました。");

        return self::SUCCESS;
    }
}
