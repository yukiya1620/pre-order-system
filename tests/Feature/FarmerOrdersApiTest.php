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

class FarmerOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    private int $productSaleId;

    private int $orderCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '検証用の説明文',
            'category_id' => $category->id,
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 100,
            'initial_stock' => 100,
            'sale_start_date' => now()->subDays(10),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3),
            'status' => '販売中',
        ]);

        $this->productSaleId = $sale->id;
    }

    private function createOrder(string $status, int $deliveryDaysFromNow = 3): Order
    {
        $this->orderCounter++;
        $buyer = User::factory()->create();

        $order = Order::create([
            'order_number' => sprintf('TEST-%04d', $this->orderCounter),
            'user_id' => $buyer->id,
            'status' => $status,
            'total_amount' => 500,
            'delivery_address' => 'テスト住所',
            'delivery_date' => now()->addDays($deliveryDaysFromNow)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $this->productSaleId,
            'product_name' => 'トマト',
            'unit_price' => 500,
            'quantity' => 1,
            'subtotal' => 500,
        ]);

        return $order;
    }

    public function test_guest_cannot_list_orders(): void
    {
        $response = $this->getJson('/api/v1/farmer/orders');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_list_orders(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/orders');

        $response->assertStatus(403);
    }

    public function test_active_only_excludes_delivered_and_cancelled(): void
    {
        $farmer = User::factory()->farmer()->create();
        $received = $this->createOrder(Order::STATUS_RECEIVED);
        $this->createOrder(Order::STATUS_DELIVERED);
        $this->createOrder(Order::STATUS_CANCELLED);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?active_only=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'orders.data');
        $response->assertJsonPath('orders.data.0.id', $received->id);
    }

    public function test_without_active_only_returns_all_statuses(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->createOrder(Order::STATUS_RECEIVED);
        $this->createOrder(Order::STATUS_DELIVERED);
        $this->createOrder(Order::STATUS_CANCELLED);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders');

        $response->assertOk();
        $response->assertJsonCount(3, 'orders.data');
    }

    public function test_status_filter_still_works_independently_of_active_only(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->createOrder(Order::STATUS_RECEIVED);
        $delivered = $this->createOrder(Order::STATUS_DELIVERED);
        $this->createOrder(Order::STATUS_CANCELLED);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?status='.Order::STATUS_DELIVERED);

        $response->assertOk();
        $response->assertJsonCount(1, 'orders.data');
        $response->assertJsonPath('orders.data.0.id', $delivered->id);
    }

    public function test_active_only_zero_behaves_like_unspecified(): void
    {
        $farmer = User::factory()->farmer()->create();
        $this->createOrder(Order::STATUS_RECEIVED);
        $this->createOrder(Order::STATUS_DELIVERED);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?active_only=0');

        $response->assertOk();
        $response->assertJsonCount(2, 'orders.data');
    }

    public function test_orders_are_sorted_by_delivery_date_ascending_with_active_only(): void
    {
        $farmer = User::factory()->farmer()->create();
        $later = $this->createOrder(Order::STATUS_RECEIVED, 10);
        $sooner = $this->createOrder(Order::STATUS_RECEIVED, 2);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?active_only=1');

        $response->assertOk();
        $response->assertJsonPath('orders.data.0.id', $sooner->id);
        $response->assertJsonPath('orders.data.1.id', $later->id);
    }
}
