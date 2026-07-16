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

    // payment_methodカラムに入る値
    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_PAYPAY = 'paypay';

    // payment_statusカラムに入る値
    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_REFUNDED = 'refunded';

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
        'payment_method',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            // 'date:Y-m-d'で明示的にフォーマットを指定すると、内部的には引き続きCarbonインスタンス
            // として扱えつつ(->format()等はそのまま使える)、JSONシリアライズ時だけ
            // UTC変換された日時("...T15:00:00.000000Z")ではなく"YYYY-MM-DD"を返すようになる。
            // ProductSale.delivery_date(GET /products)と表記を揃えるための変更。
            'delivery_date' => 'date:Y-m-d',
            'is_proxy_order' => 'boolean',
            'paid_at' => 'datetime',
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

    /**
     * この注文に対する数量減少・キャンセルの変更履歴
     */
    public function orderAdjustments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }
}
