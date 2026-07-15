<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class OrderConfirmController extends Controller
{
    /**
     * 注文内容の確認(B5)。表示専用画面。buyerミドルウェアにより
     * 購入者としてログイン済みであることが保証されている。
     * クエリ文字列(product_sale_id/quantity/delivery_time_slot)はB4からの初期値の受け渡しにのみ使い、
     * 金額・在庫・正式な配達予定日は画面側のJavaScriptが POST /api/v1/orders/preview を呼んで
     * 取得した値を正とする(URLの値をそのまま信用しない)。
     */
    public function show(): View
    {
        return view('order-confirm');
    }
}
