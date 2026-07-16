<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerProductFormPageTest extends TestCase
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

    // --- 新規登録ページ ---

    public function test_guest_cannot_access_create_page(): void
    {
        $response = $this->get('/farmer/products/create');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_create_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/products/create');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_create_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/create');

        $response->assertOk();
        $response->assertSee('商品登録');
    }

    public function test_create_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/create');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_create_page_has_back_link_to_products_list(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/create');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.products').'"', false);
    }

    public function test_create_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/create');

        $response->assertOk();
        $response->assertSee('js/farmer-product-form.js', false);
    }

    public function test_create_page_has_no_archived_checkbox_shown_initially(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/create');

        $response->assertOk();
        // 新規登録モードではarchived-fieldがhiddenのまま出力される
        $response->assertSee('class="field" id="archived-field" hidden', false);
    }

    // --- 編集ページ ---

    public function test_guest_cannot_access_edit_page(): void
    {
        $product = $this->createProduct();

        $response = $this->get('/farmer/products/'.$product->id.'/edit');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_edit_page(): void
    {
        $product = $this->createProduct();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/products/'.$product->id.'/edit');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_edit_page(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/edit');

        $response->assertOk();
        $response->assertSee('商品編集');
    }

    public function test_nonexistent_product_edit_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/999999/edit');

        $response->assertStatus(404);
    }

    public function test_edit_page_embeds_product_api_url(): void
    {
        $product = $this->createProduct();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products/'.$product->id.'/edit');

        $response->assertOk();
        $response->assertSee('data-product-api-url="'.url('/api/v1/farmer/products/'.$product->id).'"', false);
    }
}
