<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmPageTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(): ProductSale
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);

        return ProductSale::create([
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
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_page(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id='.$sale->id.'&quantity=2');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertOk();
        $response->assertSee('js/order-confirm.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertOk();
        $response->assertSee('data-preview-url="'.url('/api/v1/orders/preview').'"', false);
        $response->assertSee('data-orders-url="'.url('/api/v1/orders').'"', false);
        $response->assertSee('data-order-complete-base-url="'.url('/orders').'"', false);
    }

    public function test_back_link_points_to_product_detail_when_product_sale_id_present(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id='.$sale->id.'&quantity=1');

        $response->assertOk();
        $response->assertSee('href="'.route('products.show', $sale->id).'"', false);
    }

    public function test_back_link_falls_back_to_buyer_home_without_product_sale_id(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm');

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_page_has_reassurance_message(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertOk();
        $response->assertSee('まだ注文は確定していません');
    }

    public function test_page_has_summary_and_action_elements(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id=1&quantity=1');

        $response->assertOk();
        $response->assertSee('id="confirm-product-name"', false);
        $response->assertSee('id="confirm-amount"', false);
        $response->assertSee('id="confirm-address"', false);
        $response->assertSee('id="confirm-delivery-date"', false);
        $response->assertSee('id="order-confirm-submit-button"', false);
    }
}
