<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderFor(User $buyer): Order
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
        ]);

        $order = Order::create([
            'order_number' => '20260715-0001',
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 500,
            'delivery_address' => $buyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => 'トマト',
            'unit_price' => 500,
            'quantity' => 1,
            'subtotal' => 500,
        ]);

        return $order;
    }

    // === B7: 注文履歴一覧 ===

    public function test_guest_is_redirected_to_login_for_index(): void
    {
        $response = $this->get('/orders');

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home_for_index(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_index(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
    }

    public function test_index_uses_common_layout(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_index_loads_javascript(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('js/order-history.js', false);
    }

    public function test_index_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('data-orders-url="'.url('/api/v1/orders').'"', false);
        $response->assertSee('data-order-detail-base-url="'.url('/orders').'"', false);
        $response->assertSee('data-order-confirm-base-url="'.url('/orders/confirm').'"', false);
    }

    public function test_index_has_back_link_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_index_has_filter_elements(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('id="history-period-select"', false);
        $response->assertSee('id="history-month-select"', false);
    }

    // === 購入者向け注文詳細 ===

    public function test_guest_is_redirected_to_login_for_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->get('/orders/'.$order->id);

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home_for_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders/'.$order->id);

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_owner_can_access_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
    }

    public function test_other_buyer_cannot_access_show(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrderFor($owner);
        $otherBuyer = User::factory()->create();

        $response = $this->actingAs($otherBuyer)->get('/orders/'.$order->id);

        $response->assertStatus(403);
    }

    public function test_show_uses_common_layout(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_show_loads_javascript(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('js/order-detail.js', false);
    }

    public function test_show_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('data-order-url="'.url('/api/v1/orders/'.$order->id).'"', false);
        $response->assertSee('data-reorder-preview-url="'.url('/api/v1/orders/'.$order->id.'/reorder-preview').'"', false);
    }

    public function test_show_has_back_link_to_order_history(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('href="'.route('orders.index').'"', false);
    }

    public function test_show_has_reorder_button(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="order-detail-reorder-button"', false);
    }
}
