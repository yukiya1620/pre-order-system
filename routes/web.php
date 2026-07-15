<?php

use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\BuyerHomeController;
use App\Http\Controllers\BuyerProductController;
use App\Http\Controllers\FarmerAnnouncementsController;
use App\Http\Controllers\FarmerDeliveryConfirmationsController;
use App\Http\Controllers\FarmerHomeController;
use App\Http\Controllers\FarmerOrdersController;
use App\Http\Controllers\FarmerProductsController;
use App\Http\Controllers\FarmerSalesController;
use App\Http\Controllers\OrderCompleteController;
use App\Http\Controllers\OrderConfirmController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// 購入者トップ(B3)。設計書・GET /products等が認証不要な公開APIであることに合わせ、
// 未ログインでも閲覧できる(buyerミドルウェアは付けない)。ログイン中の農家だけ
// Controller内で/farmerへ手動リダイレクトする(EnsureUserIsBuyerはB5以降で使う)。
Route::get('/', [BuyerHomeController::class, 'show'])->name('buyer.home');

// 商品詳細(B4)。B3と同じ理由で未ログインでも閲覧できる。URLの形はAPI(GET /products/{id})に揃える。
// 存在しない商品idはルートモデルバインディングにより自動的に404になる。
Route::get('/products/{productSale}', [BuyerProductController::class, 'show'])->name('products.show');

// 会員登録(B1)・ログイン(B2)。同じController/Blade/JSを共有し、モードだけ切り替える。
Route::get('/register', [AuthPageController::class, 'register'])->name('register');
Route::get('/login', [AuthPageController::class, 'login'])->name('login');

// 注文内容の確認(B5)・注文完了(B6)。EnsureUserIsBuyerを初めて実ルートに適用する
// (ログインしていない/農家アカウントでは注文操作に進めない)。
Route::middleware('buyer')->get('/orders/confirm', [OrderConfirmController::class, 'show'])->name('orders.confirm');
Route::middleware('buyer')->get('/orders/{order}/complete', [OrderCompleteController::class, 'show'])->name('orders.complete');

// 注文履歴(B7)・購入者向け注文詳細。「confirm」が{order}に誤って束縛されないよう、
// 必ず /orders/confirm より後に登録する(F10の/farmer/orders/createと同じ注意点)。
Route::middleware('buyer')->get('/orders', [OrderHistoryController::class, 'index'])->name('orders.index');
Route::middleware('buyer')->get('/orders/{order}', [OrderHistoryController::class, 'show'])->name('orders.show');

// 通知一覧(B8)。
Route::middleware('buyer')->get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');

// 設定画面(B9・F1「設定」に対応)。購入者・農家共通で、表示中の登録情報は
// 画面側のJavaScriptがGET/PUT /api/v1/users/meを呼んで取得・更新する。
Route::middleware('auth')->get('/settings', [SettingsController::class, 'show'])->name('settings');

// 農家ホーム(F1)。既存の farmer ミドルウェア(role=farmer以外を403で弾く)をそのまま利用する。
// 未ログイン・購入者ロールもこのミドルウェア1つでまとめて弾かれる。
Route::middleware('farmer')->get('/farmer', [FarmerHomeController::class, 'show'])->name('farmer.home');

// 注文確認(F2)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/delivery-confirmations', [FarmerDeliveryConfirmationsController::class, 'show'])->name('farmer.delivery-confirmations');

// 予約一覧(F3)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/orders', [FarmerOrdersController::class, 'index'])->name('farmer.orders');

// 電話注文の代理入力(F10)。「create」が{order}に誤って束縛されないよう、
// 必ず /farmer/orders/{order} より前に登録する。
Route::middleware('farmer')->get('/farmer/orders/create', [FarmerOrdersController::class, 'create'])->name('farmer.orders.create');

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

// お知らせ投稿(F9)。farmerミドルウェアの考え方はF1と同じ。
Route::middleware('farmer')->get('/farmer/announcements', [FarmerAnnouncementsController::class, 'index'])->name('farmer.announcements');
