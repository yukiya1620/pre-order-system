<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    // created_at/updated_atカラムが無いテーブルなので自動更新をオフにする
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_sale_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    /**
     * この明細の親となる注文
     */
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * この明細が対象とする販売シーズン
     */
    public function productSale(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductSale::class);
    }
}
