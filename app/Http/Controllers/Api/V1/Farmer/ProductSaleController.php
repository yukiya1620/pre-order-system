<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\StoreProductSaleRequest;
use App\Http\Requests\Farmer\UpdateProductSaleRequest;
use App\Models\Product;
use App\Models\ProductSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ProductSaleController extends Controller
{
    /**
     * 商品マスタに対して、新しい販売シーズンを作る(再販売もここから行う)
     */
    public function store(StoreProductSaleRequest $request, Product $product): JsonResponse
    {
        $saleStartDate = Carbon::parse($request->input('sale_start_date'));
        $saleEndDate = Carbon::parse($request->input('sale_end_date'));
        $stockQuantity = (int) $request->input('stock_quantity');

        $productSale = $product->productSales()->create([
            'price' => $request->input('price'),
            // 販売開始時点なので、在庫数と開始時在庫数は同じ値になる
            'stock_quantity' => $stockQuantity,
            'initial_stock' => $stockQuantity,
            'sale_start_date' => $saleStartDate,
            'sale_end_date' => $saleEndDate,
            'delivery_date_from' => $request->input('delivery_date_from'),
            'delivery_date_to' => $request->input('delivery_date_to'),
            'delivery_note' => $request->input('delivery_note', '天候等により前後する場合があります'),
            'is_reservation_open' => $request->boolean('is_reservation_open', true),
            'status' => ProductSale::determineStatus($saleStartDate, $saleEndDate),
        ]);

        return response()->json(['product_sale' => $productSale->load('product')], 201);
    }

    /**
     * 販売シーズンの編集(価格・在庫・期間・予約受付可否)
     */
    public function update(UpdateProductSaleRequest $request, ProductSale $sale): JsonResponse
    {
        $sale->fill($request->only([
            'price',
            'stock_quantity',
            'sale_start_date',
            'sale_end_date',
            'delivery_date_from',
            'delivery_date_to',
            'delivery_note',
        ]));

        if ($request->has('is_reservation_open')) {
            $sale->is_reservation_open = $request->boolean('is_reservation_open');
        }

        // 期間を変更した場合に備えて、状態(準備中/販売中/販売終了)を再判定する
        if ($request->hasAny(['sale_start_date', 'sale_end_date'])) {
            $sale->status = ProductSale::determineStatus($sale->sale_start_date, $sale->sale_end_date);
        }

        $sale->save();

        return response()->json(['product_sale' => $sale->load('product')]);
    }

    /**
     * 販売停止(予約受付を閉じ、状態を「販売終了」にする)
     */
    public function stop(ProductSale $sale): JsonResponse
    {
        $sale->update([
            'is_reservation_open' => false,
            'status' => ProductSale::STATUS_ENDED,
        ]);

        return response()->json(['product_sale' => $sale->load('product')]);
    }
}
