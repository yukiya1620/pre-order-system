<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GenerateDeliveryConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // バッチの実行時刻(毎朝7:00)を想定して「今日」を固定し、日数条件を実行日に依存させない。
        $this->travelTo(Carbon::parse('2026-07-15 07:00:00', 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name = 'トマト'): Product
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);

        return Product::create([
            'name' => $name,
            'description' => '説明',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
    }

    private function createSale(Product $product, array $overrides = []): ProductSale
    {
        return ProductSale::create(array_merge([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 100,
            'initial_stock' => 100,
            'sale_start_date' => now()->subDays(30),
            'sale_end_date' => now()->addDays(60),
            'delivery_date_from' => now()->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
            'is_reservation_open' => true,
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            'requires_delivery_confirmation' => true,
        ], $overrides));
    }

    private function createOrder(ProductSale $sale, array $overrides = []): Order
    {
        $buyer = User::factory()->create();
        $quantity = $overrides['quantity'] ?? 1;
        $subtotal = $sale->price * $quantity;

        $order = Order::create(array_merge([
            'order_number' => 'TEST-'.random_int(100000, 999999),
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => $subtotal,
            'delivery_address' => 'テスト住所',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ], collect($overrides)->except('quantity')->all()));

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ]);

        return $order;
    }

    // === 対象抽出条件 ===

    public function test_creates_confirmation_for_order_delivered_in_three_days(): void
    {
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations')
            ->assertExitCode(0)
            ->expectsOutputToContain('1件の配達確認を作成しました。');

        $this->assertDatabaseHas('delivery_confirmations', ['order_id' => $order->id]);
    }

    public function test_returns_zero_when_no_target_orders(): void
    {
        $this->artisan('orders:generate-delivery-confirmations')
            ->assertExitCode(0)
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_order_delivered_in_two_days_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['delivery_date' => now()->addDays(2)->toDateString()]);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_order_delivered_in_four_days_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['delivery_date' => now()->addDays(4)->toDateString()]);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_order_with_requires_delivery_confirmation_false_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct(), ['requires_delivery_confirmation' => false]);
        $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_order_with_auto_delivery_date_type_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct(), ['delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_AUTO]);
        $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_cancelled_order_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_CANCELLED]);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_delivered_order_is_not_targeted(): void
    {
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED]);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 0);
    }

    public function test_order_with_existing_confirmation_is_not_targeted_again(): void
    {
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);
        DeliveryConfirmation::create(['order_id' => $order->id, 'notified_at' => now()]);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 1);
    }

    // === 冪等性 ===

    public function test_running_command_twice_creates_confirmation_only_once(): void
    {
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('1件の配達確認を作成しました。');
        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('0件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 1);
        $this->assertDatabaseHas('delivery_confirmations', ['order_id' => $order->id]);
    }

    // === 作成データの内容 ===

    public function test_notified_at_matches_command_execution_time(): void
    {
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations');

        $confirmation = DeliveryConfirmation::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($confirmation->notified_at->equalTo(now()));
    }

    public function test_notification_is_created_for_every_farmer(): void
    {
        $farmerA = User::factory()->farmer()->create();
        $farmerB = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations');

        $this->assertSame(2, Notification::where('related_order_id', $order->id)->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmerA->id,
            'related_order_id' => $order->id,
            'type' => '配達確認依頼',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmerB->id,
            'related_order_id' => $order->id,
            'type' => '配達確認依頼',
        ]);
    }

    public function test_notification_body_contains_delivery_date_and_order_reference(): void
    {
        User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $order = $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations');

        $notification = Notification::where('related_order_id', $order->id)->firstOrFail();
        $this->assertSame('配達確認依頼', $notification->type);
        $this->assertSame($order->id, $notification->related_order_id);
        $this->assertStringContainsString($order->delivery_date->format('n月j日'), $notification->body);
    }

    // === 複数対象注文 ===

    public function test_multiple_target_orders_all_get_confirmations(): void
    {
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale);
        $this->createOrder($sale);

        $this->artisan('orders:generate-delivery-confirmations')
            ->expectsOutputToContain('2件の配達確認を作成しました。');

        $this->assertDatabaseCount('delivery_confirmations', 2);
    }
}
