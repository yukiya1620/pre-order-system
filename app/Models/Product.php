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
}
