<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderChangeRequest;
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

    private function createOrder(string $status, int $deliveryDaysFromNow = 3, int $quantity = 1): Order
    {
        $this->orderCounter++;
        $buyer = User::factory()->create();

        $order = Order::create([
            'order_number' => sprintf('TEST-%04d', $this->orderCounter),
            'user_id' => $buyer->id,
            'status' => $status,
            'total_amount' => 500 * $quantity,
            'delivery_address' => 'テスト住所',
            'delivery_date' => now()->addDays($deliveryDaysFromNow)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $this->productSaleId,
            'product_name' => 'トマト',
            'unit_price' => 500,
            'quantity' => $quantity,
            'subtotal' => 500 * $quantity,
        ]);

        return $order;
    }

    private function createPendingChangeRequest(Order $order, string $type = OrderChangeRequest::REQUEST_TYPE_CANCELLATION, ?int $requestedQuantity = null): OrderChangeRequest
    {
        $orderItem = $order->orderItems()->first();

        return OrderChangeRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'request_type' => $type,
            'quantity_at_request' => $orderItem->quantity,
            'requested_quantity' => $requestedQuantity,
            'requested_by' => $order->user_id,
        ]);
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

    // === 配達完了 ===

    public function test_farmer_can_complete_order(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED);

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', Order::STATUS_DELIVERED);
        $response->assertJsonPath('order.payment_status', 'paid');

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'type' => '配達完了',
            'related_order_id' => $order->id,
        ]);
    }

    public function test_complete_is_idempotent_and_does_not_duplicate_notification(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED);

        $payload = ['payment_status' => 'unpaid'];

        $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", $payload)->assertOk();
        $second = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", ['payment_status' => 'paid']);

        $second->assertOk();
        $second->assertJsonPath('order.payment_status', 'paid');
        $this->assertSame(1, Notification::where('related_order_id', $order->id)->where('type', '配達完了')->count());
    }

    public function test_complete_rejected_when_pending_quantity_reduction_request_exists(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED, 3, 2);
        $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 1);

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_CHANGE_REQUEST_PENDING');
    }

    public function test_complete_rejected_when_pending_cancellation_request_exists(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED);
        $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_CANCELLATION);

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_CHANGE_REQUEST_PENDING');
    }

    public function test_complete_rejection_leaves_order_and_request_unchanged(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED);
        $changeRequest = $this->createPendingChangeRequest($order);

        $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
            'payment_method' => 'card',
        ])->assertStatus(422);

        $order->refresh();
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);
        // DBのデフォルト値(payment_status='unpaid'・payment_method='cash')から変化しないこと
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNull($order->paid_at);
        $this->assertNull($changeRequest->fresh()->resolved_at);
        $this->assertSame(0, Notification::where('related_order_id', $order->id)->where('type', '配達完了')->count());
    }

    public function test_complete_allowed_after_resolve_without_change(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED);
        $changeRequest = $this->createPendingChangeRequest($order);

        $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        )->assertOk();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', Order::STATUS_DELIVERED);
    }

    public function test_complete_allowed_after_reduce_quantity_auto_resolves_request(): void
    {
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder(Order::STATUS_RECEIVED, 3, 2);
        $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 1);

        $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ])->assertOk();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/complete", [
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', Order::STATUS_DELIVERED);
    }
}
