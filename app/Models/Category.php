<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    // created_at/updated_atカラムが無いテーブルなので自動更新をオフにする
    public $timestamps = false;

    protected $fillable = [
        'name',
        'display_order',
    ];

    /**
     * このカテゴリーに属する商品マスタ
     */
    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }
}
