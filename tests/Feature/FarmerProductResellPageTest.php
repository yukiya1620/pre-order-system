<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerProductResellPageTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);

        return Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
    }

    public function test_guest_cannot_access_page(): void
    {
        $product = $this->createProduct();

        $response = $this->get('/farmer/products/'.$product->id.'/resell');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $product = $this->createProduct();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('再販売設定');
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/999999/resell');

        $response->assertStatus(404);
    }

    public function test_page_uses_common_layout(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_products_list(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.products').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('js/farmer-product-resell.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('data-product-api-url="'.url('/api/v1/farmer/products/'.$product->id).'"', false);
        $response->assertSee('data-sales-api-url="'.url('/api/v1/farmer/products/'.$product->id.'/sales').'"', false);
    }

    public function test_page_has_delivery_date_to_hint(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/resell');

        $response->assertOk();
        $response->assertSee('単日の場合は空欄のままにしてください');
    }
}
