<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductSale;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * 販売中+売り切れの商品一覧(B3のワイヤーフレーム通り、売り切れもグレー表示で一覧に残す)。
     * 準備中・販売終了は対象外。
     * 画面に必要な「価格・残り数・配達予定日」などは商品マスタではなく
     * 販売シーズン(product_sales)側に持つ情報なので、こちらを起点に取得する。
     */
    public function index(): JsonResponse
    {
        $productSales = ProductSale::with('product.category')
            ->whereIn('status', [ProductSale::STATUS_ON_SALE, ProductSale::STATUS_SOLD_OUT])
            ->orderBy('sale_start_date')
            ->get();

        return response()->json(['products' => $productSales]);
    }

    /**
     * 商品詳細(配達予定日を必ず含む)
     */
    public function show(ProductSale $productSale): JsonResponse
    {
        return response()->json(['product' => $productSale->load('product.category')]);
    }
}
