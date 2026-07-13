<?php

use App\Http\Controllers\FarmerHomeController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 設定画面(B9・F1「設定」に対応)。購入者・農家共通で、表示中の登録情報は
// 画面側のJavaScriptがGET/PUT /api/v1/users/meを呼んで取得・更新する。
Route::get('/settings', [SettingsController::class, 'show'])->name('settings');

// 農家ホーム(F1)。既存の farmer ミドルウェア(role=farmer以外を403で弾く)をそのまま利用する。
// 未ログイン・購入者ロールもこのミドルウェア1つでまとめて弾かれる。
Route::middleware('farmer')->get('/farmer', [FarmerHomeController::class, 'show'])->name('farmer.home');
