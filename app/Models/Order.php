<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // statusカラムに入る値。文字列を直接書き散らさないよう定数にまとめる
    public const STATUS_RECEIVED = '受付済';

    public const STATUS_DELIVERY_CONFIRMED = '配達確認済';

    public const STATUS_DELIVERY_CHANGED = '配達日変更';

    public const STATUS_DELIVERED = '配達完了';

    public const STATUS_CANCELLED = 'キャンセル';

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'total_amount',
        'delivery_address',
        'delivery_date',
        'delivery_time_slot',
        'is_proxy_order',
        'proxy_note',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'is_proxy_order' => 'boolean',
        ];
    }

    /**
     * この注文をした利用者
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この注文の明細(商品ごとの内訳)
     */
    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * この注文に対する配達確認(1対1)
     */
    public function deliveryConfirmation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryConfirmation::class);
    }
}
