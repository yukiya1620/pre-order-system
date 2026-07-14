<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\StoreProductRequest;
use App\Http\Requests\Farmer\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * 商品マスタ一覧(過去に使ったものを含む全件)。
     * 各商品には最新の販売シーズン(latest_product_sale)を1件だけ含める(無ければnull)。
     * F5一覧で「今の販売設定」を表示するために使う。
     */
    public function index(): JsonResponse
    {
        $products = Product::with(['category', 'latestProductSale'])->orderBy('created_at', 'desc')->get();

        return response()->json(['products' => $products]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
            'image' => $this->storeImage($request),
            'unit_label' => $request->input('unit_label', '個'),
        ]);

        return response()->json(['product' => $product], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->fill([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
            'unit_label' => $request->input('unit_label', $product->unit_label),
        ]);

        if ($request->hasFile('image')) {
            $product->image = $this->storeImage($request);
        }

        if ($request->has('is_archived')) {
            $product->is_archived = $request->boolean('is_archived');
        }

        $product->save();

        return response()->json(['product' => $product]);
    }

    private function storeImage(StoreProductRequest|UpdateProductRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('products', 'public');
    }
}
