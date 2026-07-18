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

class OrderChangeRequestApiTest extends TestCase
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

    // === 数量変更を相談する ===

    public function test_buyer_can_request_quantity_reduction(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('order_change_request.request_type', OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION);
        $response->assertJsonPath('order_change_request.quantity_at_request', 3);
        $response->assertJsonPath('order_change_request.requested_quantity', 2);

        $this->assertDatabaseHas('order_change_requests', [
            'order_id' => $order->id,
            'request_type' => OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION,
            'quantity_at_request' => 3,
            'requested_quantity' => 2,
            'requested_by' => $buyer->id,
            'resolution_type' => null,
            'resolved_at' => null,
        ]);

        // 相談時点では注文・在庫・売上を一切変更しない
        $this->assertSame(3, $order->fresh()->orderItems()->first()->quantity);
        $this->assertSame($sale->price * 3, $order->fresh()->total_amount);
        $this->assertSame(5, $sale->fresh()->stock_quantity);
        $this->assertSame('受付済', $order->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'type' => '数量変更相談',
            'related_order_id' => $order->id,
        ]);
    }

    public function test_notifies_all_farmers_on_quantity_reduction_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);
        $farmer1 = User::factory()->farmer()->create();
        $farmer2 = User::factory()->farmer()->create();

        $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 1,
        ])->assertCreated();

        $this->assertSame(2, Notification::where('related_order_id', $order->id)
            ->where('type', '数量変更相談')
            ->whereIn('user_id', [$farmer1->id, $farmer2->id])
            ->count());
    }

    public function test_quantity_reduction_rejected_when_quantity_is_already_one(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 1, $buyer);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'QUANTITY_ALREADY_MINIMUM');
        $this->assertSame(0, OrderChangeRequest::count());
    }

    public function test_quantity_reduction_rejected_when_requested_quantity_is_not_less_than_current(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_quantity_reduction_requires_requested_quantity_field(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('requested_quantity');
    }

    public function test_quantity_reduction_rejects_zero_requested_quantity(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('requested_quantity');
    }

    // === キャンセルを相談する ===

    public function test_buyer_can_request_cancellation(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 1, $buyer);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertCreated();
        $response->assertJsonPath('order_change_request.request_type', OrderChangeRequest::REQUEST_TYPE_CANCELLATION);
        $response->assertJsonPath('order_change_request.quantity_at_request', 1);
        $response->assertJsonPath('order_change_request.requested_quantity', null);

        // 相談時点では注文・在庫・ステータスを一切変更しない(数量1でもキャンセル相談は可能)
        $this->assertSame('受付済', $order->fresh()->status);
        $this->assertSame(5, $sale->fresh()->stock_quantity);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'type' => '注文キャンセル相談',
            'related_order_id' => $order->id,
        ]);
    }

    // === 対象外ステータス ===

    public function test_request_rejected_on_delivered_order(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer, Order::STATUS_DELIVERED);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');
    }

    public function test_request_rejected_on_cancelled_order(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer, Order::STATUS_CANCELLED);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');
    }

    // === 重複防止 ===

    public function test_duplicate_quantity_reduction_request_rejected(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 2,
        ])->assertCreated();

        $second = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 1,
        ]);

        $second->assertStatus(422);
        $second->assertJsonPath('error.code', 'REQUEST_ALREADY_PENDING');
        $this->assertSame(1, OrderChangeRequest::where('order_id', $order->id)->count());
    }

    /**
     * 数量変更を相談した後にキャンセルを相談しても、種別をまたいで重複扱いになる。
     */
    public function test_cancellation_request_rejected_after_pending_quantity_reduction_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 2,
        ])->assertCreated();

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'REQUEST_ALREADY_PENDING');
        $this->assertSame(1, OrderChangeRequest::where('order_id', $order->id)->count());
    }

    // === アクセス制御・その他の異常系 ===

    public function test_other_buyer_cannot_request_change(): void
    {
        $sale = $this->createSale();
        $owner = User::factory()->create();
        $order = $this->createOrder($sale, 2, $owner);
        $otherBuyer = User::factory()->create();

        $response = $this->actingAs($otherBuyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertStatus(403);
        $this->assertSame(0, OrderChangeRequest::count());
    }

    public function test_guest_cannot_request_change(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertStatus(401);
    }

    public function test_request_rejected_for_order_with_multiple_items(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);
        // この仕組みは1注文1明細を前提とするため、想定外の2件目明細を追加してみる
        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => 1,
            'subtotal' => $sale->price,
        ]);

        $response = $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'MULTIPLE_ITEMS_NOT_SUPPORTED');
    }

    // === レスポンスにpending_change_requestが含まれること ===

    public function test_order_show_includes_pending_change_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 3, $buyer);

        $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/quantity-change-requests", [
            'requested_quantity' => 2,
        ])->assertCreated();

        $response = $this->actingAs($buyer)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('order.pending_change_request.request_type', OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION);
        $response->assertJsonPath('order.pending_change_request.requested_quantity', 2);
    }

    public function test_order_index_includes_pending_change_request(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);

        $this->actingAs($buyer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests")->assertCreated();

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders');

        $response->assertOk();
        $response->assertJsonPath('orders.0.pending_change_request.request_type', OrderChangeRequest::REQUEST_TYPE_CANCELLATION);
    }

    public function test_order_without_pending_request_has_null(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrder($sale, 2, $buyer);

        $response = $this->actingAs($buyer)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('order.pending_change_request', null);
    }
}
