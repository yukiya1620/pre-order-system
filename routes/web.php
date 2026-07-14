<?php

use App\Http\Controllers\FarmerDeliveryConfirmationsController;
use App\Http\Controllers\FarmerHomeController;
use App\Http\Controllers\FarmerOrdersController;
use App\Http\Controllers\FarmerProductsController;
use App\Http\Controllers\FarmerSalesController;
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

// 注文確認(F2)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/delivery-confirmations', [FarmerDeliveryConfirmationsController::class, 'show'])->name('farmer.delivery-confirmations');

// 予約一覧(F3)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/orders', [FarmerOrdersController::class, 'index'])->name('farmer.orders');

// 注文詳細(F4)。存在しない注文idはルートモデルバインディングにより自動的に404になる。
Route::middleware('farmer')->get('/farmer/orders/{order}', [FarmerOrdersController::class, 'show'])->name('farmer.orders.show');

// 商品管理(F5)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/products', [FarmerProductsController::class, 'index'])->name('farmer.products');

// 商品登録・編集(F6)。新規登録・編集は共通のBlade/JSで、モードのみ切り替える。
// 存在しない商品idはルートモデルバインディングにより自動的に404になる。
Route::middleware('farmer')->get('/farmer/products/create', [FarmerProductsController::class, 'create'])->name('farmer.products.create');
Route::middleware('farmer')->get('/farmer/products/{product}/edit', [FarmerProductsController::class, 'edit'])->name('farmer.products.edit');

// 再販売設定(F7・新規販売シーズン作成専用)。既存シーズンの編集・停止は今回対応しない。
Route::middleware('farmer')->get('/farmer/products/{product}/resell', [FarmerProductsController::class, 'resell'])->name('farmer.products.resell');

// 売上確認(F8)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/sales', [FarmerSalesController::class, 'index'])->name('farmer.sales');
