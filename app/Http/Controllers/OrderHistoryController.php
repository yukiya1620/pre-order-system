<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class OrderHistoryController extends Controller
{
    /**
     * 注文履歴(B7)。表示専用画面。buyerミドルウェアにより
     * 購入者としてログイン済みであることが保証されている。
     * 一覧・年月絞り込み・「前回と同じ内容で注文」は画面側のJavaScriptが
     * 既存の GET /api/v1/orders・POST /api/v1/orders/{id}/reorder-preview を呼んで行う。
     */
    public function index(): View
    {
        return view('order-history');
    }

    /**
     * 購入者向け注文詳細。本人の注文以外は403にする(buyerミドルウェアはロールのみ確認するため、
     * 所有者チェックはここで行う。B6のOrderCompleteControllerと同じ考え方)。
     * 表示内容は画面側のJavaScriptが既存の GET /api/v1/orders/{id} を呼んで取得する。
     */
    public function show(Order $order): View
    {
        abort_if($order->user_id !== Auth::id(), 403);

        return view('order-detail', ['orderId' => $order->id]);
    }
}
