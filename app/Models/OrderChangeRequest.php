<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChangeRequest extends Model
{
    // created_at only, no updated_at(order_adjustmentsと同じく、記録は作成後にresolved_at等を個別に更新する)
    public $timestamps = false;

    // request_typeカラムに入る値
    public const REQUEST_TYPE_QUANTITY_REDUCTION = 'quantity_reduction';

    public const REQUEST_TYPE_CANCELLATION = 'cancellation';

    // resolution_typeカラムに入る値
    public const RESOLUTION_TYPE_ADJUSTMENT_APPLIED = 'adjustment_applied';

    public const RESOLUTION_TYPE_NO_CHANGE = 'no_change';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'request_type',
        'quantity_at_request',
        'requested_quantity',
        'requested_by',
        'resolution_type',
        'resolved_by',
        'resolution_note',
        'resolved_at',
        'resolved_order_adjustment_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * この相談の対象となる注文
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * この相談の対象となる明細
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * 相談した購入者
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * 対応した農家
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * resolution_type=adjustment_appliedのとき、実際に確定した数量減少・キャンセル履歴
     */
    public function resolvedOrderAdjustment(): BelongsTo
    {
        return $this->belongsTo(OrderAdjustment::class);
    }
}
