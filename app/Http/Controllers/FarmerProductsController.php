<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class FarmerProductsController extends Controller
{
    /**
     * 商品管理(F5)。一覧専用画面。表示は画面側のJavaScriptが
     * 既存の GET /api/v1/farmer/products を呼んで行う。
     */
    public function index(): View
    {
        return view('farmer-products');
    }

    /**
     * 商品登録(F6・新規登録モード)。新規登録・編集は共通のBlade/JSを使い、
     * モードの違いはdata属性で画面側に渡す。
     */
    public function create(): View
    {
        return view('farmer-product-form', [
            'mode' => 'create',
            'productId' => null,
        ]);
    }

    /**
     * 商品編集(F6・編集モード)。存在しない商品idはルートモデルバインディングにより
     * 自動的に404になる。初期値の取得は画面側のJavaScriptが
     * GET /api/v1/farmer/products/{id} を呼んで行う。
     */
    public function edit(Product $product): View
    {
        return view('farmer-product-form', [
            'mode' => 'edit',
            'productId' => $product->id,
        ]);
    }

    /**
     * 再販売設定(F7・新規販売シーズン作成専用)。既存シーズンの編集・停止はここでは扱わない。
     * 存在しない商品idはルートモデルバインディングにより自動的に404になる。
     * 前回の販売設定(あれば)の取得は画面側のJavaScriptが
     * GET /api/v1/farmer/products/{id} を呼んで行う。
     */
    public function resell(Product $product): View
    {
        return view('farmer-product-resell', [
            'productId' => $product->id,
        ]);
    }
}
