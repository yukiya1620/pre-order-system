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

class OrderApiTest extends TestCase
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

    // === preview ===

    public function test_guest_cannot_preview_order(): void
    {
        $sale = $this->createSale();

        $response = $this->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_farmer_cannot_preview_order(): void
    {
        $sale = $this->createSale();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_buyer_can_preview_order(): void
    {
        $sale = $this->createSale(['delivery_date_from' => now()->addDays(5)->toDateString()]);
        $buyer = User::factory()->create(['address' => '沖縄県宮古島市1-1-1']);

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 3,
            'delivery_time_slot' => '午前',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order_preview.product_name', 'トマト');
        $response->assertJsonPath('order_preview.unit_price', 500);
        $response->assertJsonPath('order_preview.quantity', 3);
        $response->assertJsonPath('order_preview.subtotal', 1500);
        $response->assertJsonPath('order_preview.total_amount', 1500);
        $response->assertJsonPath('order_preview.delivery_address', '沖縄県宮古島市1-1-1');
        $response->assertJsonPath('order_preview.delivery_time_slot', '午前');
        $response->assertJsonPath('order_preview.delivery_date', now()->addDays(5)->toDateString());

        // preview段階では在庫を減らさない
        $this->assertSame(10, $sale->fresh()->stock_quantity);
    }

    public function test_preview_not_available_when_not_on_sale(): void
    {
        $sale = $this->createSale(['status' => ProductSale::STATUS_SOLD_OUT, 'stock_quantity' => 0]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'NOT_AVAILABLE');
    }

    public function test_preview_not_available_when_product_is_archived(): void
    {
        $sale = $this->createSale();
        $sale->product->update(['is_archived' => true]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'NOT_AVAILABLE');
    }

    public function test_preview_out_of_stock(): void
    {
        $sale = $this->createSale(['stock_quantity' => 2]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/preview', [
            'product_sale_id' => $sale->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'OUT_OF_STOCK');
    }

    // === store ===

    public function test_guest_cannot_place_order(): void
    {
        $sale = $this->createSale();

        $response = $this->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_farmer_cannot_place_order(): void
    {
        $sale = $this->createSale();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_buyer_can_place_order(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.total_amount', 1000);
        $response->assertJsonPath('order.user_id', $buyer->id);
        $this->assertNotNull($response->json('order.order_number'));
        $this->assertNotNull($response->json('order.id'));
    }

    public function test_order_delivery_date_is_returned_as_plain_date_string(): void
    {
        $deliveryDate = now()->addDays(4)->toDateString();
        $sale = $this->createSale(['delivery_date_from' => $deliveryDate]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.delivery_date', $deliveryDate);
    }

    public function test_store_uses_server_side_price_ignoring_any_client_input(): void
    {
        $sale = $this->createSale(['price' => 500]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 2,
            'price' => 1, // 送っても無視されるはず(PlaceOrderRequestが受け付けていない)
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.total_amount', 1000);
    }

    public function test_stock_is_decremented_after_order(): void
    {
        $sale = $this->createSale(['stock_quantity' => 10]);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 4,
        ])->assertStatus(201);

        $this->assertSame(6, $sale->fresh()->stock_quantity);
    }

    public function test_out_of_stock_order_is_not_created(): void
    {
        $sale = $this->createSale(['stock_quantity' => 1]);
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'OUT_OF_STOCK');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $sale->fresh()->stock_quantity);
    }

    public function test_validation_error_leaves_no_order_behind(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders', [
            'product_sale_id' => $sale->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('quantity');
        $this->assertSame(0, Order::count());
    }

    // === show / ownership ===

    private int $orderCounter = 0;

    private function createOrderFor(User $buyer, ProductSale $sale): Order
    {
        $this->orderCounter++;

        $order = Order::create([
            'order_number' => sprintf('TEST-%04d', $this->orderCounter),
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

    public function test_buyer_can_view_own_order(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer, $sale);

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders/'.$order->id);

        $response->assertOk();
        $response->assertJsonPath('order.id', $order->id);
    }

    public function test_buyer_cannot_view_other_buyers_order(): void
    {
        $sale = $this->createSale();
        $owner = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $order = $this->createOrderFor($owner, $sale);

        $response = $this->actingAs($otherBuyer)->getJson('/api/v1/orders/'.$order->id);

        $response->assertStatus(403);
    }

    public function test_reorder_preview_forbidden_for_other_buyers_order(): void
    {
        $sale = $this->createSale();
        $owner = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $order = $this->createOrderFor($owner, $sale);

        $response = $this->actingAs($otherBuyer)->postJson('/api/v1/orders/'.$order->id.'/reorder-preview');

        $response->assertStatus(403);
    }

    // === index (注文履歴・年月絞り込み) ===

    public function test_guest_cannot_list_orders(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(401);
    }

    public function test_farmer_cannot_list_orders(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/orders');

        $response->assertStatus(403);
    }

    public function test_index_without_params_returns_recent_six_months_only(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $recent = $this->createOrderFor($buyer, $sale);
        $recent->forceFill(['created_at' => now()->subMonths(2)])->save();

        $old = $this->createOrderFor($buyer, $sale);
        $old->forceFill(['created_at' => now()->subMonths(8)])->save();

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders');

        $response->assertOk();
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('orders.0.id', $recent->id);
    }

    public function test_index_with_year_returns_all_orders_in_that_year(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $inYear = $this->createOrderFor($buyer, $sale);
        $inYear->forceFill(['created_at' => '2024-03-01 10:00:00'])->save();

        $outsideYear = $this->createOrderFor($buyer, $sale);
        $outsideYear->forceFill(['created_at' => '2023-12-01 10:00:00'])->save();

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders?year=2024');

        $response->assertOk();
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('orders.0.id', $inYear->id);
    }

    public function test_index_with_year_and_month_returns_only_that_month(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $target = $this->createOrderFor($buyer, $sale);
        $target->forceFill(['created_at' => '2024-03-15 10:00:00'])->save();

        $otherMonth = $this->createOrderFor($buyer, $sale);
        $otherMonth->forceFill(['created_at' => '2024-04-15 10:00:00'])->save();

        $response = $this->actingAs($buyer)->getJson('/api/v1/orders?year=2024&month=3');

        $response->assertOk();
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('orders.0.id', $target->id);
    }

    public function test_index_only_returns_own_orders(): void
    {
        $sale = $this->createSale();
        $owner = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $this->createOrderFor($owner, $sale);

        $response = $this->actingAs($otherBuyer)->getJson('/api/v1/orders');

        $response->assertOk();
        $response->assertJsonCount(0, 'orders');
    }

    // === reorder-preview: reorder_params ===

    public function test_reorder_preview_includes_explicit_reorder_params(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer, $sale);
        $order->forceFill(['delivery_time_slot' => '午後'])->save();

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/'.$order->id.'/reorder-preview');

        $response->assertOk();
        $response->assertJsonPath('reorder_params.product_sale_id', $sale->id);
        $response->assertJsonPath('reorder_params.quantity', 1);
        $response->assertJsonPath('reorder_params.delivery_time_slot', '午後');
    }

    public function test_reorder_preview_not_available_when_product_no_longer_on_sale(): void
    {
        $sale = $this->createSale(['status' => ProductSale::STATUS_ENDED]);
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer, $sale);

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/'.$order->id.'/reorder-preview');

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'NOT_AVAILABLE');
    }

    public function test_reorder_preview_out_of_stock(): void
    {
        $sale = $this->createSale(['stock_quantity' => 0]);
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer, $sale);

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/'.$order->id.'/reorder-preview');

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'OUT_OF_STOCK');
    }

    public function test_reorder_preview_when_order_has_no_items(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'TEST-NOITEM',
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 0,
            'delivery_address' => $buyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($buyer)->postJson('/api/v1/orders/'.$order->id.'/reorder-preview');

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ORDER_ITEM_NOT_FOUND');
    }
}
