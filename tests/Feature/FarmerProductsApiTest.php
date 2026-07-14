<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerProductsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);

        return Product::create(array_merge([
            'name' => 'トマト',
            'description' => '説明',
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
            'sale_start_date' => now(),
            'sale_end_date' => now()->addMonth(),
            'delivery_date_from' => now()->addDays(3),
            'status' => '販売中',
        ], $overrides));
    }

    public function test_guest_cannot_list_products(): void
    {
        $response = $this->getJson('/api/v1/farmer/products');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_list_products(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/products');

        $response->assertStatus(403);
    }

    public function test_product_without_sales_has_null_latest_product_sale(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->createProduct();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.latest_product_sale', null);
    }

    public function test_latest_product_sale_picks_newest_by_sale_start_date(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct();

        $old = $this->createSale($product, [
            'sale_start_date' => now()->subYear(),
            'sale_end_date' => now()->subYear()->addMonth(),
            'status' => '販売終了',
        ]);
        $newest = $this->createSale($product, [
            'sale_start_date' => now(),
            'sale_end_date' => now()->addMonth(),
            'status' => '販売中',
        ]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.latest_product_sale.id', $newest->id);
    }

    public function test_latest_product_sale_breaks_tie_by_id_when_same_start_date(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct();
        $sameDate = now();

        $this->createSale($product, ['sale_start_date' => $sameDate]);
        $latest = $this->createSale($product, ['sale_start_date' => $sameDate]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.latest_product_sale.id', $latest->id);
    }

    public function test_archived_products_are_still_included(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->createProduct(['is_archived' => true]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'products');
        $response->assertJsonPath('products.0.is_archived', true);
    }

    public function test_existing_response_fields_are_unaffected(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct();
        $this->createSale($product);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products');

        $response->assertOk();
        $response->assertJsonPath('products.0.id', $product->id);
        $response->assertJsonPath('products.0.name', 'トマト');
        $response->assertJsonPath('products.0.category.name', '季節商品');
        $response->assertJsonPath('products.0.unit_label', '袋');
    }
}
