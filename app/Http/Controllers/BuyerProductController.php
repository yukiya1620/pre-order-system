<?php

namespace App\Http\Controllers;

use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BuyerProductController extends Controller
{
    /**
     * 商品詳細(B4)。B3と同じく未ログインでも閲覧できる。
     * ログイン中の農家だけ農家ホームへ手動で振り分ける。
     * 存在しない商品idはルートモデルバインディングにより自動的に404になる。
     * データ取得は画面側のJavaScriptが既存の GET /api/v1/products/{id} を呼んで行う。
     */
    public function show(ProductSale $productSale): View|RedirectResponse
    {
        if (Auth::user()?->role === User::ROLE_FARMER) {
            return redirect()->route('farmer.home');
        }

        return view('product-detail', ['productSaleId' => $productSale->id]);
    }
}
