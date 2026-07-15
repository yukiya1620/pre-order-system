<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class SettingsController extends Controller
{
    /**
     * 設定画面(B9・F1「設定」共通)。ページの枠だけを返し、実際の登録情報は
     * 画面側のJavaScriptがGET/PUT /api/v1/users/meを呼んで表示・保存する。
     * 未ログイン時のリダイレクトは標準のauthミドルウェア(routes/web.php側)に任せる。
     */
    public function show(): View
    {
        return view('settings');
    }
}
