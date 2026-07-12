<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * 設定画面(購入者・農家共通)。ページの枠だけを返し、実際の登録情報は
     * 画面側のJavaScriptがGET/PUT /api/v1/users/meを呼んで表示・保存する。
     *
     * 'login'という名前のルートがまだ存在しないため、標準のauthミドルウェアには頼らず、
     * ここで直接ログイン状態を確認する(未ログインなら401)。
     */
    public function show(): View
    {
        abort_unless(Auth::check(), 401);

        return view('settings');
    }
}
