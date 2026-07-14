<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);

        return Product::create(array_merge([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ], $overrides));
    }

    private function createSale(Product $product, array $overrides = []): ProductSale
    {
        return ProductSale::create(array_merge([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
            'is_reservation_open' => true,
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
        ], $overrides));
    }

    public function test_index_includes_on_sale_products(): void
    {
        $sale = $this->createSale($this->createProduct());

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.id', $sale->id);
    }

    public function test_index_includes_sold_out_products(): void
    {
        $sale = $this->createSale($this->createProduct(['name' => 'スイカ']), [
            'status' => ProductSale::STATUS_SOLD_OUT,
            'stock_quantity' => 0,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.id', $sale->id);
        $response->assertJsonPath('products.0.status', ProductSale::STATUS_SOLD_OUT);
    }

    public function test_index_excludes_preparing_products(): void
    {
        $this->createSale($this->createProduct(), ['status' => ProductSale::STATUS_PREPARING]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(0, 'products');
    }

    public function test_index_excludes_ended_products(): void
    {
        $this->createSale($this->createProduct(), ['status' => ProductSale::STATUS_ENDED]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(0, 'products');
    }

    public function test_index_includes_reservation_open_flag(): void
    {
        $openSale = $this->createSale($this->createProduct(['name' => '受付中']), ['is_reservation_open' => true]);
        $closedSale = $this->createSale($this->createProduct(['name' => '受付停止']), ['is_reservation_open' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $byId = collect($response->json('products'))->keyBy('id');
        $this->assertTrue($byId[$openSale->id]['is_reservation_open']);
        $this->assertFalse($byId[$closedSale->id]['is_reservation_open']);
    }

    public function test_index_includes_category_information(): void
    {
        $sale = $this->createSale($this->createProduct());

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.product.category.name', '季節商品');
    }

    public function test_fixed_type_delivery_date_matches_delivery_date_from(): void
    {
        $deliveryDate = now()->addDays(9)->toDateString();
        $sale = $this->createSale($this->createProduct(), [
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            'delivery_date_from' => $deliveryDate,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.delivery_date', $deliveryDate);
    }

    public function test_auto_type_delivery_date_is_pushed_back_one_day_after_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00', 'Asia/Tokyo'));

        $sale = $this->createSale($this->createProduct(), [
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_AUTO,
            'earliest_delivery_days' => 1,
            'order_deadline_time' => '18:00:00',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        // 締切(18:00)を過ぎているため、本来の翌日配達(7/16)がさらに1日後ろ倒しになり7/17になる
        $response->assertJsonPath('products.0.delivery_date', '2026-07-17');

        Carbon::setTestNow();
    }

    public function test_show_returns_product_detail_with_delivery_date(): void
    {
        $deliveryDate = now()->addDays(6)->toDateString();
        $sale = $this->createSale($this->createProduct(), ['delivery_date_from' => $deliveryDate]);

        $response = $this->getJson('/api/v1/products/'.$sale->id);

        $response->assertOk();
        $response->assertJsonPath('product.id', $sale->id);
        $response->assertJsonPath('product.delivery_date', $deliveryDate);
        $response->assertJsonPath('product.product.category.name', '季節商品');
    }

    public function test_show_returns_404_for_nonexistent_product(): void
    {
        $response = $this->getJson('/api/v1/products/99999');

        $response->assertStatus(404);
    }
}
