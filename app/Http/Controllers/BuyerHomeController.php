<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class BuyerHomeController extends Controller
{
    /**
     * 購入者トップ(B3の暫定版)。/ にアクセスした購入者の着地点。
     * buyerミドルウェアにより、未認証は/login、農家は/farmerへ振り分け済みなので、
     * ここに到達する時点でログイン中の購入者であることが保証されている。
     * B3本実装(お知らせ+商品一覧)までの間の最小限のプレースホルダー。
     */
    public function show(): View
    {
        return view('buyer-home');
    }
}
