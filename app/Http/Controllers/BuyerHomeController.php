<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BuyerHomeController extends Controller
{
    /**
     * 購入者トップ(B3)。設計書・公開APIの方針に合わせ、未ログインでも閲覧できる。
     * ログイン中の農家だけ、業務用の農家ホームへ手動で振り分ける
     * (buyerミドルウェアは付けていないため、ここでロールを確認する)。
     * お知らせ・商品一覧の取得は画面側のJavaScriptが
     * 既存の GET /api/v1/announcements・GET /api/v1/products を呼んで行う。
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::user()?->role === User::ROLE_FARMER) {
            return redirect()->route('farmer.home');
        }

        return view('buyer-home');
    }
}
