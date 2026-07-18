<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Farmer\AnnouncementController as FarmerAnnouncementController;
use App\Http\Controllers\Api\V1\Farmer\CategoryController as FarmerCategoryController;
use App\Http\Controllers\Api\V1\Farmer\DeliveryConfirmationController;
use App\Http\Controllers\Api\V1\Farmer\OrderController as FarmerOrderController;
use App\Http\Controllers\Api\V1\Farmer\ProductController as FarmerProductController;
use App\Http\Controllers\Api\V1\Farmer\ProductSaleController;
use App\Http\Controllers\Api\V1\Farmer\SalesController;
use App\Http\Controllers\Api\V1\Farmer\OrderChangeRequestController as FarmerOrderChangeRequestController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderChangeRequestController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SmsAuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('sms/send', [SmsAuthController::class, 'send']);
        Route::post('sms/verify', [SmsAuthController::class, 'verify']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    // 購入者向け:販売中の商品一覧・詳細
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{productSale}', [ProductController::class, 'show']);

    // 購入者向け:公開中のお知らせ一覧(トップページ表示用)
    Route::get('announcements', [AnnouncementController::class, 'index']);

    // 共通(購入者・農家どちらも):自分の登録情報
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('users/me', [UserController::class, 'me']);
        Route::put('users/me', [UserController::class, 'update']);
    });

    // 購入者向け:注文(B5・B6実装に合わせ、農家アカウントが自分で注文を作れないようbuyerミドルウェアを付ける。
    // 所有者以外の注文取得を防ぐチェックはController側(ownershipError)に既存)
    Route::middleware(['auth:sanctum', 'buyer'])->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/preview', [OrderController::class, 'preview']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::post('orders/{order}/reorder-preview', [OrderController::class, 'reorderPreview']);

        // 購入者からの「数量変更を相談する」「キャンセルを相談する」。
        // この時点では注文・在庫・売上に一切触れない(実際の確定は農家がF4の既存機能で行う)。
        Route::post('orders/{order}/quantity-change-requests', [OrderChangeRequestController::class, 'requestQuantityChange']);
        Route::post('orders/{order}/cancellation-requests', [OrderChangeRequestController::class, 'requestCancellation']);
    });

    // 共通(購入者・農家どちらも):通知
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });

    // 農家向け:管理API(auth:sanctum + farmerミドルウェア配下に集約。設計書4.4〜4.9に対応)
    Route::middleware(['auth:sanctum', 'farmer'])->prefix('farmer')->group(function () {
        // 4.4 商品管理:商品マスタ・販売シーズンの管理
        Route::get('products', [FarmerProductController::class, 'index']);
        Route::get('products/{product}', [FarmerProductController::class, 'show']);
        Route::post('products', [FarmerProductController::class, 'store']);
        Route::put('products/{product}', [FarmerProductController::class, 'update']);
        Route::post('products/{product}/sales', [ProductSaleController::class, 'store']);
        Route::put('sales/{sale}', [ProductSaleController::class, 'update']);
        Route::put('sales/{sale}/stop', [ProductSaleController::class, 'stop']);

        // カテゴリー一覧(F6の商品登録・編集フォーム用。カテゴリー自体のCRUDは無い)
        Route::get('categories', [FarmerCategoryController::class, 'index']);

        // 4.7 配達確認
        Route::get('delivery-confirmations', [DeliveryConfirmationController::class, 'index']);
        Route::post('delivery-confirmations/{deliveryConfirmation}/respond', [DeliveryConfirmationController::class, 'respond']);

        // 4.6 予約・注文管理
        Route::get('orders', [FarmerOrderController::class, 'index']);
        Route::get('orders/{order}', [FarmerOrderController::class, 'show']);
        Route::post('orders', [FarmerOrderController::class, 'store']);
        Route::put('orders/{order}/complete', [FarmerOrderController::class, 'complete']);
        Route::put('orders/{order}/reduce-quantity', [FarmerOrderController::class, 'reduceQuantity']);
        Route::put('orders/{order}/cancel', [FarmerOrderController::class, 'cancel']);

        // 購入者からの数量変更・キャンセル相談。件数取得と「変更せず終了する」のみ
        // (実際の確定は既存のreduce-quantity/cancelを使う)。
        Route::get('order-change-requests/count', [FarmerOrderChangeRequestController::class, 'count']);
        Route::put('order-change-requests/{orderChangeRequest}/resolve-without-change', [FarmerOrderChangeRequestController::class, 'resolveWithoutChange']);

        // 4.8 売上
        Route::get('sales-summary', [SalesController::class, 'summary']);
        Route::get('sales-by-product', [SalesController::class, 'byProduct']);

        // 4.9 お知らせ管理
        Route::get('announcements', [FarmerAnnouncementController::class, 'index']);
        Route::post('announcements', [FarmerAnnouncementController::class, 'store']);
        Route::put('announcements/{announcement}', [FarmerAnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}', [FarmerAnnouncementController::class, 'destroy']);
    });
});
