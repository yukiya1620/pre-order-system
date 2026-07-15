<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class OrderCompleteController extends Controller
{
    /**
     * 注文完了(B6)。POST /orders成功後、window.location.replace()で
     * ここへ遷移してくる(再読み込み・戻る操作での二重注文を防ぐため)。
     * 本人の注文以外は403にする(buyerミドルウェアはロールのみ確認するため、
     * 所有者チェックはここで行う)。
     * 表示内容(注文番号・配達予定日)は画面側のJavaScriptが
     * 既存の GET /api/v1/orders/{id} を呼んで取得する。
     */
    public function show(Order $order): View
    {
        abort_if($order->user_id !== Auth::id(), 403);

        return view('order-complete', ['orderId' => $order->id]);
    }
}
