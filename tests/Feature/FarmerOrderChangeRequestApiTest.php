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

class FarmerOrderChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(array $overrides = []): ProductSale
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);

        return ProductSale::create(array_merge([
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
        ], $overrides));
    }

    private function createOrder(ProductSale $sale, int $quantity, User $buyer, string $status = '受付済'): Order
    {
        $order = Order::create([
            'order_number' => 'TEST-'.random_int(10000, 99999),
            'user_id' => $buyer->id,
            'status' => $status,
            'total_amount' => $sale->price * $quantity,
            'delivery_address' => 'テスト住所',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => $quantity,
            'subtotal' => $sale->price * $quantity,
        ]);

        return $order;
    }

    private function createPendingRequest(Order $order, User $buyer, string $type = OrderChangeRequest::REQUEST_TYPE_CANCELLATION, ?int $requestedQuantity = null): OrderChangeRequest
    {
        $orderItem = $order->orderItems()->first();

        return OrderChangeRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'request_type' => $type,
            'quantity_at_request' => $orderItem->quantity,
            'requested_quantity' => $requestedQuantity,
            'requested_by' => $buyer->id,
        ]);
    }

    // === count ===

    public function test_count_returns_zero_when_no_pending_requests(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/order-change-requests/count');

        $response->assertOk();
        $response->assertJsonPath('count', 0);
    }

    public function test_count_returns_number_of_pending_requests_only(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();

        $order1 = $this->createOrder($sale, 2, $buyer);
        $order2 = $this->createOrder($sale, 2, $buyer);
        $order3 = $this->createOrder($sale, 2, $buyer);

        $this->createPendingRequest($order1, $buyer);
        $this->createPendingRequest($order2, $buyer);
        $resolved = $this->createPendingRequest($order3, $buyer);
        $resolved->update(['resolution_type' => OrderChangeRequest::RESOLUTION_TYPE_NO_CHANGE, 'resolved_by' => $farmer->id, 'resolved_at' => now()]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/order-change-requests/count');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
    }

    // === resolve-without-change ===

    public function test_farmer_can_resolve_without_change(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder($sale, 3, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 2);

        $response = $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            ['note' => '電話で確認したが今回は見送り']
        );

        $response->assertOk();
        $response->assertJsonPath('order_change_request.resolution_type', OrderChangeRequest::RESOLUTION_TYPE_NO_CHANGE);
        $response->assertJsonPath('order_change_request.resolution_note', '電話で確認したが今回は見送り');

        $changeRequest->refresh();
        $this->assertSame(OrderChangeRequest::RESOLUTION_TYPE_NO_CHANGE, $changeRequest->resolution_type);
        $this->assertSame($farmer->id, $changeRequest->resolved_by);
        $this->assertNotNull($changeRequest->resolved_at);
        $this->assertNull($changeRequest->resolved_order_adjustment_id);

        // 注文数量・在庫・ステータス・配達日・売上には一切触れない
        $order->refresh();
        $this->assertSame('受付済', $order->status);
        $this->assertSame(3, $order->orderItems()->first()->quantity);
        $this->assertSame($sale->price * 3, $order->total_amount);
        $this->assertSame(5, $sale->fresh()->stock_quantity);
    }

    public function test_resolve_without_change_notifies_buyer(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        )->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $buyer->id,
            'type' => '相談終了',
            'related_order_id' => $order->id,
        ]);
    }

    public function test_resolve_without_change_rejects_double_execution(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $first = $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        );
        $first->assertOk();

        $second = $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        );
        $second->assertStatus(422);
        $second->assertJsonPath('error.code', 'ALREADY_RESOLVED');

        $this->assertSame(1, Notification::where('related_order_id', $order->id)->where('type', '相談終了')->count());
    }

    public function test_resolve_without_change_returns_404_for_unknown_id(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson('/api/v1/farmer/order-change-requests/999999/resolve-without-change', []);

        $response->assertStatus(404);
    }

    public function test_guest_cannot_resolve_without_change(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $response = $this->putJson("/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change", []);

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_resolve_without_change(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $response = $this->actingAs($buyer)->putJson("/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change", []);

        $response->assertStatus(403);
    }

    public function test_new_request_can_be_created_after_no_change_resolution(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        )->assertOk();

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertCreated();
        $this->assertSame(2, OrderChangeRequest::where('order_id', $order->id)->count());
        $this->assertSame(1, OrderChangeRequest::where('order_id', $order->id)->whereNull('resolved_at')->count());
    }

    // === GET /farmer/orders との連携 ===

    public function test_farmer_orders_can_filter_by_pending_change_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();

        $orderWithRequest = $this->createOrder($sale, 2, $buyer);
        $this->createPendingRequest($orderWithRequest, $buyer);
        $orderWithoutRequest = $this->createOrder($sale, 2, $buyer);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?has_pending_change_request=1');

        $response->assertOk();
        $ids = collect($response->json('orders.data'))->pluck('id')->all();
        $this->assertContains($orderWithRequest->id, $ids);
        $this->assertNotContains($orderWithoutRequest->id, $ids);
    }

    public function test_resolved_request_excluded_from_pending_change_request_filter(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();

        $order = $this->createOrder($sale, 2, $buyer);
        $changeRequest = $this->createPendingRequest($order, $buyer);

        $this->actingAs($farmer)->putJson(
            "/api/v1/farmer/order-change-requests/{$changeRequest->id}/resolve-without-change",
            []
        )->assertOk();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders?has_pending_change_request=1');

        $response->assertOk();
        $ids = collect($response->json('orders.data'))->pluck('id')->all();
        $this->assertNotContains($order->id, $ids);
    }

    public function test_farmer_orders_index_includes_pending_change_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();

        $order = $this->createOrder($sale, 2, $buyer);
        $this->createPendingRequest($order, $buyer, OrderChangeRequest::REQUEST_TYPE_CANCELLATION);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/orders');

        $response->assertOk();
        $data = collect($response->json('orders.data'))->firstWhere('id', $order->id);
        $this->assertSame(OrderChangeRequest::REQUEST_TYPE_CANCELLATION, $data['pending_change_request']['request_type']);
    }

    public function test_farmer_order_show_includes_pending_change_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $farmer = User::factory()->farmer()->create();

        $order = $this->createOrder($sale, 3, $buyer);
        $this->createPendingRequest($order, $buyer, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 2);

        $response = $this->actingAs($farmer)->getJson("/api/v1/farmer/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('order.pending_change_request.request_type', OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION);
        $response->assertJsonPath('order.pending_change_request.requested_quantity', 2);
        $response->assertJsonPath('order.pending_change_request.quantity_at_request', 3);
    }
}
