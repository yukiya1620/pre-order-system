<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class FarmerHomeController extends Controller
{
    /**
     * 農家ホーム(F1)。ログイン中農家の名前はここで直接渡す(すでにAuth::user()で
     * 取得できているものをそのまま使うだけなので、settings画面のように改めてAPIを
     * 呼び直す必要が無い)。要対応件数・本日の売上は画面側のJavaScriptが
     * 既存の一覧・集計APIから取得する。
     */
    public function show(): View
    {
        return view('farmer-home', [
            'farmer' => Auth::user(),
        ]);
    }
}
