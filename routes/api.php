<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Farmer\DeliveryConfirmationController;
use App\Http\Controllers\Api\V1\Farmer\ProductController as FarmerProductController;
use App\Http\Controllers\Api\V1\Farmer\ProductSaleController;
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

    // 農家向け:商品マスタ・販売シーズンの管理
    Route::middleware(['auth:sanctum', 'farmer'])->prefix('farmer')->group(function () {
        Route::get('products', [FarmerProductController::class, 'index']);
        Route::post('products', [FarmerProductController::class, 'store']);
        Route::put('products/{product}', [FarmerProductController::class, 'update']);
        Route::post('products/{product}/sales', [ProductSaleController::class, 'store']);
        Route::put('sales/{sale}', [ProductSaleController::class, 'update']);
        Route::put('sales/{sale}/stop', [ProductSaleController::class, 'stop']);

        Route::get('delivery-confirmations', [DeliveryConfirmationController::class, 'index']);
        Route::post('delivery-confirmations/{deliveryConfirmation}/respond', [DeliveryConfirmationController::class, 'respond']);
    });
});
