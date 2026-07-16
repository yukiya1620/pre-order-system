<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAdjustment extends Model
{
    // created_at only, no updated_at(記録は作成後に書き換えない前提)
    public $timestamps = false;

    // change_typeカラムに入る値
    public const CHANGE_TYPE_QUANTITY_REDUCED = 'quantity_reduced';

    public const CHANGE_TYPE_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'change_type',
        'previous_status',
        'new_status',
        'previous_quantity',
        'new_quantity',
        'stock_restored',
        'confirmed_with_buyer_at',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_with_buyer_at' => 'datetime',
        ];
    }

    /**
     * この変更履歴の対象となる注文
     */
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * この変更履歴の対象となる明細
     */
    public function orderItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * 操作した農家
     */
    public function changedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
