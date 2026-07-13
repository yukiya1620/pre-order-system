<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class FarmerOrdersController extends Controller
{
    /**
     * 予約一覧(F3)。一覧の取得・絞り込み・配達完了操作は画面側のJavaScriptが
     * 既存のfarmer向けAPIを呼んで行う(FarmerHomeController/FarmerDeliveryConfirmationsControllerと同じ方針)。
     */
    public function show(): View
    {
        return view('farmer-orders');
    }
}
