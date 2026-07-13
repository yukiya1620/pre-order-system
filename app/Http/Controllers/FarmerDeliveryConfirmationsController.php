<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class FarmerDeliveryConfirmationsController extends Controller
{
    /**
     * 注文確認(F2)。未回答の配達確認一覧・回答処理は画面側のJavaScriptが
     * 既存のfarmer向けAPIを呼んで行う(FarmerHomeControllerと同じ方針)。
     */
    public function show(): View
    {
        return view('farmer-delivery-confirmations');
    }
}
