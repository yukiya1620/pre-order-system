<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    // updated_atカラムが無いため、Eloquentにその存在を教えない
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'related_order_id',
        'is_read',
        'sent_email_at',
        'sent_sms_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'sent_email_at' => 'datetime',
            'sent_sms_at' => 'datetime',
        ];
    }

    /**
     * 通知の宛先となる利用者
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この通知が関連する注文(無い場合もある)
     */
    public function relatedOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }
}
