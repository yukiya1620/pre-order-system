<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FarmerProductFormApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createCategory(): Category
    {
        return Category::create(['name' => '季節商品', 'display_order' => 1]);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $this->createCategory()->id,
            'unit_label' => '袋',
        ], $overrides));
    }

    // --- GET /farmer/products/{product} ---

    public function test_guest_cannot_show_product(): void
    {
        $product = $this->createProduct();

        $response = $this->getJson('/api/v1/farmer/products/'.$product->id);

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_show_product(): void
    {
        $product = $this->createProduct();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/products/'.$product->id);

        $response->assertStatus(403);
    }

    public function test_farmer_can_show_product_with_category(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonPath('product.id', $product->id);
        $response->assertJsonPath('product.name', 'トマト');
        $response->assertJsonPath('product.category.name', '季節商品');
    }

    public function test_show_includes_null_latest_product_sale_when_none_exists(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonPath('product.latest_product_sale', null);
    }

    public function test_show_includes_latest_product_sale_when_it_exists(): void
    {
        $product = $this->createProduct();
        $sale = \App\Models\ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 20,
            'sale_start_date' => now()->subMonth(),
            'sale_end_date' => now()->subDays(5),
            'delivery_date_from' => now()->subDays(10),
            'status' => '販売終了',
        ]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonPath('product.latest_product_sale.id', $sale->id);
        $response->assertJsonPath('product.latest_product_sale.price', 500);
        $response->assertJsonPath('product.latest_product_sale.initial_stock', 20);
    }

    public function test_show_nonexistent_product_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/products/999999');

        $response->assertStatus(404);
    }

    // --- POST /farmer/products(新規登録) ---

    public function test_store_creates_product_without_image(): void
    {
        $farmer = User::factory()->farmer()->create();
        $category = $this->createCategory();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products', [
            'name' => 'きゅうり',
            'description' => '新鮮なきゅうり',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'きゅうり', 'image' => null]);
    }

    public function test_store_validation_requires_name_description_category(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/products', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'description', 'category_id']);
    }

    public function test_store_saves_uploaded_image(): void
    {
        $farmer = User::factory()->farmer()->create();
        $category = $this->createCategory();
        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($farmer)->post('/api/v1/farmer/products', [
            'name' => 'なす',
            'description' => 'つやつやのなす',
            'category_id' => $category->id,
            'image' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $path = $response->json('product.image');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    // --- PUT /farmer/products/{product}(編集) ---

    public function test_update_without_new_image_keeps_existing_image(): void
    {
        $product = $this->createProduct(['image' => 'products/existing.jpg']);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson('/api/v1/farmer/products/'.$product->id, [
            'name' => 'トマト(改)',
            'description' => '甘いトマト',
            'category_id' => $product->category_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('product.image', 'products/existing.jpg');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'image' => 'products/existing.jpg']);
    }

    public function test_update_with_new_image_replaces_it(): void
    {
        $product = $this->createProduct(['image' => 'products/old.jpg']);
        $farmer = User::factory()->farmer()->create();
        $file = UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($farmer)->post('/api/v1/farmer/products/'.$product->id, [
            '_method' => 'PUT',
            'name' => 'トマト(改)',
            'description' => '甘いトマト',
            'category_id' => $product->category_id,
            'image' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $newPath = $response->json('product.image');
        $this->assertNotSame('products/old.jpg', $newPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_update_can_toggle_is_archived_to_true(): void
    {
        $product = $this->createProduct(['is_archived' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson('/api/v1/farmer/products/'.$product->id, [
            'name' => $product->name,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'is_archived' => 1,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_archived' => true]);
    }

    public function test_update_can_toggle_is_archived_to_false(): void
    {
        $product = $this->createProduct(['is_archived' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson('/api/v1/farmer/products/'.$product->id, [
            'name' => $product->name,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'is_archived' => 0,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_archived' => false]);
    }

    public function test_update_validation_still_requires_name_description_category(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson('/api/v1/farmer/products/'.$product->id, []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'description', 'category_id']);
    }
}
