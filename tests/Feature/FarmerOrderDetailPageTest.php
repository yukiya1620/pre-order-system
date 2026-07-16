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

class FarmerOrderDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '検証用の説明文',
            'category_id' => $category->id,
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3),
            'status' => '販売中',
        ]);

        $buyer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'F4TEST-0001',
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 500,
            'delivery_address' => 'テスト住所',
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

    public function test_guest_cannot_access_page(): void
    {
        $order = $this->createOrder();

        $response = $this->get('/farmer/orders/'.$order->id);

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $order = $this->createOrder();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/orders/'.$order->id);

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
    }

    public function test_nonexistent_order_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/999999');

        $response->assertStatus(404);
    }

    public function test_page_uses_common_layout(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_orders_list(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.orders').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('js/farmer-order-detail.js', false);
    }

    public function test_page_embeds_correct_order_api_url(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('data-order-url="'.url('/api/v1/farmer/orders/'.$order->id).'"', false);
    }

    public function test_page_has_required_section_headings(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('配達情報');
        $response->assertSee('購入者情報');
        $response->assertSee('商品明細');
        $response->assertSee('支払い情報');
        $response->assertSee('配達確認');
    }

    public function test_orders_list_links_to_detail_page(): void
    {
        $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('data-order-detail-base-url="'.url('/farmer/orders').'"', false);
    }
}
