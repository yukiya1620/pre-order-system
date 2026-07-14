<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class FarmerSalesController extends Controller
{
    /**
     * 売上確認(F8)。表示専用画面。集計は画面側のJavaScriptが
     * 既存の GET /api/v1/farmer/sales-summary・GET /api/v1/farmer/sales-by-product を呼んで行う。
     */
    public function index(): View
    {
        return view('farmer-sales');
    }
}
