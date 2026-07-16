<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FarmerSalesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今日」を2026-07-15(月央・年央)に固定し、today/month/yearの判定を実行日に依存させない。
        $this->travelTo(Carbon::parse('2026-07-15 10:00:00', 'Asia/Tokyo'));
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
            'delivery_date' => now()->toDateString(),
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

    // === アクセス制御 ===

    public function test_guest_cannot_view_sales_summary(): void
    {
        $response = $this->getJson('/api/v1/farmer/sales-summary');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_view_sales_summary(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_sales_by_product(): void
    {
        $response = $this->getJson('/api/v1/farmer/sales-by-product');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_view_sales_by_product(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/sales-by-product');

        $response->assertStatus(403);
    }

    // === confirmed: today/this_month/this_year ===

    public function test_confirmed_today_includes_order_delivered_today(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, [
            'status' => Order::STATUS_DELIVERED,
            'delivery_date' => '2026-07-15',
        ]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.today'));
    }

    public function test_confirmed_today_excludes_yesterday_and_tomorrow(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-14']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-16']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.today'));
    }

    public function test_confirmed_this_month_excludes_previous_month_same_day(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-10']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-06-10']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.this_month'));
    }

    public function test_confirmed_this_year_excludes_previous_year_same_day(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-10']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2025-07-10']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.this_year'));
    }

    public function test_confirmed_today_and_this_month_at_month_boundary(): void
    {
        // 「今日」を月初(7/1)に固定し、前月末日(6/30)配達完了分がtoday/this_monthどちらにも
        // 混ざらないことを確認する。
        $this->travelTo(Carbon::parse('2026-07-01 09:00:00', 'Asia/Tokyo'));

        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-06-30']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-01']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.today'));
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('confirmed.this_month'));
    }

    public function test_non_delivered_statuses_are_excluded_from_confirmed(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_RECEIVED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERY_CONFIRMED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERY_CHANGED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_CANCELLED, 'delivery_date' => '2026-07-15']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 0, 'order_count' => 0], $response->json('confirmed.today'));
    }

    // === pending ===

    public function test_pending_combines_three_statuses_regardless_of_delivery_date(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_RECEIVED, 'delivery_date' => '2025-01-01']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERY_CONFIRMED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERY_CHANGED, 'delivery_date' => '2026-12-31']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 1500, 'order_count' => 3], $response->json('pending'));
    }

    public function test_pending_excludes_cancelled_and_delivered(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_CANCELLED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-15']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 0, 'order_count' => 0], $response->json('pending'));
    }

    // === payment_status_breakdown / payment_method_breakdown ===

    public function test_payment_status_breakdown_counts_confirmed_orders_by_status(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_STATUS_PAID]);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_STATUS_UNPAID]);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_STATUS_REFUNDED]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_status_breakdown.paid'));
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_status_breakdown.unpaid'));
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_status_breakdown.refunded'));
    }

    public function test_payment_method_breakdown_counts_confirmed_orders_by_method(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_method' => Order::PAYMENT_METHOD_CASH]);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_method' => Order::PAYMENT_METHOD_CARD]);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'payment_method' => Order::PAYMENT_METHOD_PAYPAY]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_method_breakdown.cash'));
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_method_breakdown.card'));
        $this->assertSame(['total_amount' => 500, 'order_count' => 1], $response->json('payment_method_breakdown.paypay'));
    }

    public function test_unconfirmed_order_is_excluded_from_payment_status_breakdown(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_RECEIVED, 'payment_status' => Order::PAYMENT_STATUS_PAID]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-summary');

        $response->assertOk();
        $this->assertSame(['total_amount' => 0, 'order_count' => 0], $response->json('payment_status_breakdown.paid'));
    }

    // === sales-by-product ===

    public function test_sales_by_product_only_counts_delivered_orders(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct();
        $sale = $this->createSale($product);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'quantity' => 2]);
        $this->createOrder($sale, ['status' => Order::STATUS_RECEIVED, 'quantity' => 5]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=month');

        $response->assertOk();
        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertSame($product->id, $products[0]['product_id']);
        $this->assertSame(1000, $products[0]['total_amount']);
        $this->assertSame(2, $products[0]['total_quantity']);
    }

    public function test_sales_by_product_merges_multiple_seasons_of_same_product(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct();
        $saleA = $this->createSale($product);
        $saleB = $this->createSale($product);
        $this->createOrder($saleA, ['status' => Order::STATUS_DELIVERED, 'quantity' => 2]);
        $this->createOrder($saleB, ['status' => Order::STATUS_DELIVERED, 'quantity' => 3]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=month');

        $response->assertOk();
        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertSame($product->id, $products[0]['product_id']);
        $this->assertSame(2500, $products[0]['total_amount']);
        $this->assertSame(5, $products[0]['total_quantity']);
    }

    public function test_sales_by_product_is_ordered_by_amount_descending(): void
    {
        $farmer = User::factory()->farmer()->create();
        $productA = $this->createProduct('トマト');
        $productB = $this->createProduct('きゅうり');
        $this->createOrder($this->createSale($productA), ['status' => Order::STATUS_DELIVERED, 'quantity' => 1]);
        $this->createOrder($this->createSale($productB), ['status' => Order::STATUS_DELIVERED, 'quantity' => 10]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=month');

        $response->assertOk();
        $products = $response->json('products');
        $this->assertSame($productB->id, $products[0]['product_id']);
        $this->assertSame(5000, $products[0]['total_amount']);
        $this->assertSame($productA->id, $products[1]['product_id']);
        $this->assertSame(500, $products[1]['total_amount']);
    }

    public function test_sales_by_product_period_today_excludes_earlier_this_month(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-05']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=today');

        $response->assertOk();
        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertSame(500, $products[0]['total_amount']);
        $this->assertSame(1, $products[0]['total_quantity']);
    }

    public function test_sales_by_product_period_year_includes_whole_year(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale($this->createProduct());
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-15']);
        $this->createOrder($sale, ['status' => Order::STATUS_DELIVERED, 'delivery_date' => '2026-07-05']);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=year');

        $response->assertOk();
        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertSame(1000, $products[0]['total_amount']);
        $this->assertSame(2, $products[0]['total_quantity']);
    }

    public function test_sales_by_product_defaults_to_month_when_period_omitted(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product');

        $response->assertOk();
        $this->assertSame('month', $response->json('period'));
    }

    public function test_sales_by_product_invalid_period_returns_validation_error(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('period');
    }

    public function test_sales_by_product_returns_empty_array_when_no_data(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/sales-by-product?period=month');

        $response->assertOk();
        $this->assertSame([], $response->json('products'));
    }
}
