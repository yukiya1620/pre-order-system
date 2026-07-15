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

class OrderCompletePageTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->get('/orders/'.$order->id.'/complete');

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders/'.$order->id.'/complete');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_owner_can_access_page(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
    }

    public function test_other_buyer_cannot_access_page(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrderFor($owner);
        $otherBuyer = User::factory()->create();

        $response = $this->actingAs($otherBuyer)->get('/orders/'.$order->id.'/complete');

        $response->assertStatus(403);
    }

    public function test_page_uses_common_layout(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('js/order-complete.js', false);
    }

    public function test_page_embeds_correct_api_url(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('data-order-url="'.url('/api/v1/orders/'.$order->id).'"', false);
    }

    public function test_page_has_expected_elements(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('ご注文ありがとうございました');
        $response->assertSee('id="complete-order-number"', false);
        $response->assertSee('id="complete-delivery-date"', false);
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }
}
