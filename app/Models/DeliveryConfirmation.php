<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConfirmation extends Model
{
    // created_at/updated_atカラムが無いテーブルなので自動更新をオフにする
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'notified_at',
        'response',
        'new_delivery_date',
        'response_note',
        'responded_at',
        'buyer_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'new_delivery_date' => 'date',
            'responded_at' => 'datetime',
            'buyer_notified_at' => 'datetime',
        ];
    }

    /**
     * 対象の注文
     */
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
