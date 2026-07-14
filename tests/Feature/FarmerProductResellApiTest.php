<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerProductResellApiTest extends TestCase
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

    private function validPayload(): array
    {
        return [
            'price' => 500,
            'stock_quantity' => 30,
            'sale_start_date' => now()->addDays(1)->toDateString(),
            'sale_end_date' => now()->addDays(30)->toDateString(),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'is_reservation_open' => 1,
        ];
    }

    public function test_guest_cannot_create_sale(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_create_sale(): void
    {
        $product = $this->createProduct();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_store_unarchives_product_when_it_was_archived(): void
    {
        $product = $this->createProduct(['is_archived' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_archived' => false]);
    }

    public function test_store_keeps_product_visible_when_already_visible(): void
    {
        $product = $this->createProduct(['is_archived' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_archived' => false]);
    }

    public function test_store_sets_initial_stock_equal_to_stock_quantity(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('product_sale.stock_quantity', 30);
        $response->assertJsonPath('product_sale.initial_stock', 30);
    }

    public function test_store_allows_creating_sale_even_when_latest_sale_is_still_active(): void
    {
        $product = $this->createProduct();
        ProductSale::create([
            'product_id' => $product->id,
            'price' => 400,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(10),
            'delivery_date_from' => now()->addDays(1),
            'status' => '販売中',
        ]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseCount('product_sales', 2);
    }

    public function test_store_validation_requires_core_fields(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/'.$product->id.'/sales', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'price', 'stock_quantity', 'sale_start_date', 'sale_end_date', 'delivery_date_from',
        ]);
    }

    public function test_store_for_nonexistent_product_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products/999999/sales', $this->validPayload());

        $response->assertStatus(404);
    }
}
