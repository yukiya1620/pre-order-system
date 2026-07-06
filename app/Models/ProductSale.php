<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSale extends Model
{
    // statusカラムに入る値。文字列を直接書き散らさないよう定数にまとめる
    public const STATUS_PREPARING = '準備中';

    public const STATUS_ON_SALE = '販売中';

    public const STATUS_SOLD_OUT = '売り切れ';

    public const STATUS_ENDED = '販売終了';

    protected $fillable = [
        'product_id',
        'price',
        'stock_quantity',
        'initial_stock',
        'sale_start_date',
        'sale_end_date',
        'delivery_date_from',
        'delivery_date_to',
        'delivery_note',
        'is_reservation_open',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sale_start_date' => 'date',
            'sale_end_date' => 'date',
            'delivery_date_from' => 'date',
            'delivery_date_to' => 'date',
            'is_reservation_open' => 'boolean',
        ];
    }

    /**
     * この販売シーズンの元になっている商品マスタ
     */
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * この販売シーズンに対する注文明細
     */
    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * 現在の予約数(= 開始時在庫 - 残り在庫)
     */
    public function getReservedQuantityAttribute(): int
    {
        return $this->initial_stock - $this->stock_quantity;
    }

    /**
     * 販売開始日・終了日と今日の日付から、初期状態を判定する。
     * (期間が来たら自動で切り替える処理は、フェーズ4のバッチ処理で別途行う)
     */
    public static function determineStatus(\Illuminate\Support\Carbon $saleStartDate, \Illuminate\Support\Carbon $saleEndDate): string
    {
        $today = now()->startOfDay();

        if ($today->gt($saleEndDate)) {
            return self::STATUS_ENDED;
        }

        if ($today->lt($saleStartDate)) {
            return self::STATUS_PREPARING;
        }

        return self::STATUS_ON_SALE;
    }
}
