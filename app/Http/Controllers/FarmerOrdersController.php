<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class FarmerOrdersController extends Controller
{
    /**
     * 予約一覧(F3)。一覧の取得・絞り込み・配達完了操作は画面側のJavaScriptが
     * 既存のfarmer向けAPIを呼んで行う(FarmerHomeController/FarmerDeliveryConfirmationsControllerと同じ方針)。
     */
    public function index(): View
    {
        return view('farmer-orders');
    }

    /**
     * 注文詳細(F4)。表示専用画面。データ取得は画面側のJavaScriptが
     * 既存の GET /api/v1/farmer/orders/{id} を呼んで行う。
     * ルートモデルバインディングにより、存在しない注文idは自動的に404になる。
     */
    public function show(Order $order): View
    {
        return view('farmer-order-detail', ['orderId' => $order->id]);
    }
}
