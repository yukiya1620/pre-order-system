<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAdjustmentApiTest extends TestCase
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

    private function createOrder(ProductSale $sale, int $quantity, string $status = '受付済'): Order
    {
        $buyer = User::factory()->create();

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

    // === 購入者からの相談の自動解消 ===

    public function test_reduce_quantity_auto_resolves_pending_change_request(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 3);
        $farmer = User::factory()->farmer()->create();
        $changeRequest = $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 1);

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();

        $changeRequest->refresh();
        $adjustment = OrderAdjustment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderChangeRequest::RESOLUTION_TYPE_ADJUSTMENT_APPLIED, $changeRequest->resolution_type);
        $this->assertSame($farmer->id, $changeRequest->resolved_by);
        $this->assertSame($adjustment->id, $changeRequest->resolved_order_adjustment_id);
        $this->assertNotNull($changeRequest->resolved_at);
    }

    public function test_cancel_auto_resolves_pending_change_request_even_when_request_type_differs(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();
        // 購入者は「数量変更」を相談したが、農家は全キャンセルで対応するケース
        $changeRequest = $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 1);

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();

        $changeRequest->refresh();
        $adjustment = OrderAdjustment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderChangeRequest::RESOLUTION_TYPE_ADJUSTMENT_APPLIED, $changeRequest->resolution_type);
        $this->assertSame($adjustment->id, $changeRequest->resolved_order_adjustment_id);
    }

    /**
     * quantity_at_requestは相談時点のスナップショットなので、確定操作で注文の数量が
     * 変わったあとも書き換わらないことを確認する。
     */
    public function test_quantity_at_request_is_preserved_after_resolution(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 3);
        $farmer = User::factory()->farmer()->create();
        $changeRequest = $this->createPendingChangeRequest($order, OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION, 1);

        $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ])->assertOk();

        $this->assertSame(3, $changeRequest->fresh()->quantity_at_request);
    }

    public function test_no_pending_change_request_is_left_untouched_by_unrelated_orders(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 3);
        $otherOrder = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();
        $otherChangeRequest = $this->createPendingChangeRequest($otherOrder);

        $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ])->assertOk();

        $this->assertNull($otherChangeRequest->fresh()->resolved_at);
    }

    // === 数量を減らす ===

    public function test_farmer_can_reduce_quantity(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 3);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
            'note' => '電話で確認済み',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.order_items.0.quantity', 1);
        $response->assertJsonPath('order.order_items.0.subtotal', 500);
        $response->assertJsonPath('order.total_amount', 500);

        $this->assertSame(7, $sale->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_adjustments', [
            'order_id' => $order->id,
            'change_type' => OrderAdjustment::CHANGE_TYPE_QUANTITY_REDUCED,
            'previous_quantity' => 3,
            'new_quantity' => 1,
            'stock_restored' => 2,
            'note' => '電話で確認済み',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'type' => '数量変更確定',
            'related_order_id' => $order->id,
        ]);
    }

    public function test_reduce_quantity_uses_order_time_unit_price_not_current_price(): void
    {
        $sale = $this->createSale(['price' => 500]);
        $order = $this->createOrder($sale, 3);
        // 注文後に商品価格が値上げされたケースを想定
        $sale->update(['price' => 900]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 2,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();
        // 900円ではなく、注文時単価500円で再計算されること
        $response->assertJsonPath('order.order_items.0.subtotal', 1000);
        $response->assertJsonPath('order.total_amount', 1000);
    }

    public function test_reduce_quantity_rejects_when_quantity_is_already_one(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 1);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'QUANTITY_ALREADY_MINIMUM');
    }

    public function test_reduce_quantity_rejects_increase(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 3,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_QUANTITY');
    }

    public function test_reduce_quantity_requires_buyer_confirmation_checkbox(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('confirmed_with_buyer_at');
    }

    public function test_reduce_quantity_rejects_on_delivered_order(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2, Order::STATUS_DELIVERED);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');
    }

    public function test_reduce_quantity_rejects_on_cancelled_order(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2, Order::STATUS_CANCELLED);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');
    }

    public function test_repeated_reduce_quantity_request_does_not_double_restore_stock(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 3);
        $farmer = User::factory()->farmer()->create();

        $payload = [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ];

        $first = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", $payload);
        $first->assertOk();

        // 同じ内容をもう一度送信(通信の再送・誤操作を想定)。1回目の適用後は
        // quantity=1になっているため、2回目は「これ以上減らせない」で弾かれる。
        $second = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", $payload);
        $second->assertStatus(422);

        $this->assertSame(7, $sale->fresh()->stock_quantity);
        $this->assertSame(1, OrderAdjustment::where('order_id', $order->id)->count());
        $this->assertSame(1, Notification::where('related_order_id', $order->id)->where('type', '数量変更確定')->count());
    }

    public function test_reduce_quantity_reverts_sold_out_status_to_on_sale(): void
    {
        $sale = $this->createSale(['stock_quantity' => 0, 'status' => ProductSale::STATUS_SOLD_OUT]);
        $order = $this->createOrder($sale, 3);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();
        $sale->refresh();
        $this->assertSame(2, $sale->stock_quantity);
        $this->assertSame(ProductSale::STATUS_ON_SALE, $sale->status);
    }

    // === 全キャンセル ===

    public function test_farmer_can_cancel_order(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
            'note' => '電話で確認済み',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', Order::STATUS_CANCELLED);
        // 注文時の数量・小計・合計金額は変更しない
        $response->assertJsonPath('order.order_items.0.quantity', 2);
        $response->assertJsonPath('order.order_items.0.subtotal', 1000);
        $response->assertJsonPath('order.total_amount', 1000);

        $this->assertSame(7, $sale->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_adjustments', [
            'order_id' => $order->id,
            'change_type' => OrderAdjustment::CHANGE_TYPE_CANCELLED,
            'previous_quantity' => 2,
            'new_quantity' => 0,
            'stock_restored' => 2,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'type' => '注文キャンセル',
            'related_order_id' => $order->id,
        ]);
    }

    public function test_cancel_allowed_when_quantity_is_one(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 1);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', Order::STATUS_CANCELLED);
        $this->assertSame(6, $sale->fresh()->stock_quantity);
    }

    public function test_cancel_requires_buyer_confirmation_checkbox(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 1);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('confirmed_with_buyer_at');
    }

    public function test_cancel_rejects_on_delivered_order(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 1, Order::STATUS_DELIVERED);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');
    }

    public function test_repeated_cancel_request_does_not_double_restore_stock_or_notify(): void
    {
        $sale = $this->createSale(['stock_quantity' => 5]);
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $payload = ['confirmed_with_buyer_at' => true];

        $first = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", $payload);
        $first->assertOk();

        $second = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", $payload);
        $second->assertStatus(422);
        $second->assertJsonPath('error.code', 'ORDER_NOT_ADJUSTABLE');

        $this->assertSame(7, $sale->fresh()->stock_quantity);
        $this->assertSame(1, OrderAdjustment::where('order_id', $order->id)->count());
        $this->assertSame(1, Notification::where('related_order_id', $order->id)->where('type', '注文キャンセル')->count());
    }

    public function test_cancel_reverts_sold_out_status_to_on_sale(): void
    {
        $sale = $this->createSale(['stock_quantity' => 0, 'status' => ProductSale::STATUS_SOLD_OUT]);
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();
        $sale->refresh();
        $this->assertSame(2, $sale->stock_quantity);
        $this->assertSame(ProductSale::STATUS_ON_SALE, $sale->status);
    }

    public function test_cancel_does_not_change_is_reservation_open_or_is_archived(): void
    {
        $sale = $this->createSale(['stock_quantity' => 0, 'status' => ProductSale::STATUS_SOLD_OUT, 'is_reservation_open' => false]);
        $sale->product->update(['is_archived' => true]);
        $order = $this->createOrder($sale, 2);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertOk();
        $sale->refresh();
        $this->assertFalse($sale->is_reservation_open);
        $this->assertTrue($sale->product->fresh()->is_archived);
    }

    // === 共通のアクセス制御 ===

    public function test_guest_cannot_reduce_quantity(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2);

        $response = $this->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_cancel_order(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->putJson("/api/v1/farmer/orders/{$order->id}/cancel", [
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_reduce_quantity_rejects_order_with_multiple_items(): void
    {
        $sale = $this->createSale();
        $order = $this->createOrder($sale, 2);
        // この仕組みは1注文1明細を前提とするため、想定外の2件目明細を追加してみる
        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => 1,
            'subtotal' => $sale->price,
        ]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->putJson("/api/v1/farmer/orders/{$order->id}/reduce-quantity", [
            'quantity' => 1,
            'confirmed_with_buyer_at' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'MULTIPLE_ITEMS_NOT_SUPPORTED');
    }
}
