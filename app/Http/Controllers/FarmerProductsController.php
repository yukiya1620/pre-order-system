<?php

namespace App\Http\Controllers;

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
}
