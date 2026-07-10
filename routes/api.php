<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Farmer\AnnouncementController as FarmerAnnouncementController;
use App\Http\Controllers\Api\V1\Farmer\DeliveryConfirmationController;
use App\Http\Controllers\Api\V1\Farmer\OrderController as FarmerOrderController;
use App\Http\Controllers\Api\V1\Farmer\ProductController as FarmerProductController;
use App\Http\Controllers\Api\V1\Farmer\ProductSaleController;
use App\Http\Controllers\Api\V1\Farmer\SalesController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SmsAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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

    // 購入者向け:注文
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/preview', [OrderController::class, 'preview']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::post('orders/{order}/reorder-preview', [OrderController::class, 'reorderPreview']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });

    // 農家向け:管理API(auth:sanctum + farmerミドルウェア配下に集約。設計書4.4〜4.9に対応)
    Route::middleware(['auth:sanctum', 'farmer'])->prefix('farmer')->group(function () {
        // 4.4 商品管理:商品マスタ・販売シーズンの管理
        Route::get('products', [FarmerProductController::class, 'index']);
        Route::post('products', [FarmerProductController::class, 'store']);
        Route::put('products/{product}', [FarmerProductController::class, 'update']);
        Route::post('products/{product}/sales', [ProductSaleController::class, 'store']);
        Route::put('sales/{sale}', [ProductSaleController::class, 'update']);
        Route::put('sales/{sale}/stop', [ProductSaleController::class, 'stop']);

        // 4.7 配達確認
        Route::get('delivery-confirmations', [DeliveryConfirmationController::class, 'index']);
        Route::post('delivery-confirmations/{deliveryConfirmation}/respond', [DeliveryConfirmationController::class, 'respond']);

        // 4.6 予約・注文管理
        Route::get('orders', [FarmerOrderController::class, 'index']);
        Route::get('orders/{order}', [FarmerOrderController::class, 'show']);
        Route::post('orders', [FarmerOrderController::class, 'store']);
        Route::put('orders/{order}/complete', [FarmerOrderController::class, 'complete']);

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
