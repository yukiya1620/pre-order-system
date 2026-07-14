<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'image',
        'unit_label',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /**
     * この商品が属するカテゴリー
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * この商品の年ごとの販売シーズン一覧
     */
    public function productSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    /**
     * 最新の販売シーズン(sale_start_date降順、同日ならid降順で1件)。
     * 商品管理一覧(F5)で「今の販売設定」を表示するために使う。無ければnull。
     */
    public function latestProductSale(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductSale::class)->latestOfMany(['sale_start_date', 'id']);
    }
}
