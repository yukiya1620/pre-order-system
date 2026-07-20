<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FarmerProxyOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function createFixedSale(array $overrides = []): ProductSale
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '検証用の説明文',
            'category_id' => $category->id,
        ]);

        return ProductSale::create(array_merge([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(10),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(5)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
            'is_reservation_open' => true,
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
        ], $overrides));
    }

    private function baseProxyOrderPayload(ProductSale $sale, array $overrides = []): array
    {
        return array_merge([
            'phone_number' => '09011112222',
            'name' => '購入 太郎',
            'address' => '沖縄県宮古島市1-2-3',
            'product_sale_id' => $sale->id,
            'quantity' => 2,
        ], $overrides);
    }

    public function test_guest_cannot_place_proxy_order(): void
    {
        $sale = $this->createFixedSale();

        $response = $this->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale));

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_place_proxy_order(): void
    {
        $buyer = User::factory()->create();
        $sale = $this->createFixedSale();

        $response = $this->actingAs($buyer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale));

        $response->assertStatus(403);
    }

    public function test_farmer_can_place_order_for_existing_buyer(): void
    {
        $farmer = User::factory()->farmer()->create();
        $existingBuyer = User::factory()->create(['phone_number' => '09033334444']);
        $sale = $this->createFixedSale();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', [
            'phone_number' => '09033334444',
            'product_sale_id' => $sale->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('order.user_id', $existingBuyer->id);
        // 既存購入者が見つかった場合、新しいユーザーは作られない
        $this->assertSame(2, User::count());
    }

    public function test_unregistered_phone_number_without_registration_info_returns_registration_info_required(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', [
            'phone_number' => '09099998888',
            'product_sale_id' => $sale->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'REGISTRATION_INFO_REQUIRED');
        // 未登録購入者は作られておらず、注文も残らない
        $this->assertSame(1, User::count());
        $this->assertSame(0, Order::count());
    }

    public function test_resubmitting_with_name_and_address_creates_buyer_and_order(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale();

        $response = $this->actingAs($farmer)->postJson(
            '/api/v1/farmer/orders',
            $this->baseProxyOrderPayload($sale, ['phone_number' => '09077776666'])
        );

        $response->assertStatus(201);

        $buyer = User::where('phone_number', '09077776666')->first();
        $this->assertNotNull($buyer);
        $this->assertSame(User::ROLE_BUYER, $buyer->role);
        $this->assertSame('購入 太郎', $buyer->name);
        $this->assertSame('沖縄県宮古島市1-2-3', $buyer->address);
        $response->assertJsonPath('order.user_id', $buyer->id);
    }

    public function test_order_amount_uses_server_side_price_not_client_input(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale(['price' => 500]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale, [
            'quantity' => 3,
            // price/unit_priceのようなフィールドを混入させても無視されることを確認する
            'price' => 1,
            'unit_price' => 1,
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('order.total_amount', 1500);
        $response->assertJsonPath('order.order_items.0.unit_price', 500);
    }

    public function test_stock_is_decremented_after_proxy_order(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale(['stock_quantity' => 10, 'initial_stock' => 10]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale, ['quantity' => 4]));

        $response->assertStatus(201);
        $this->assertSame(6, $sale->fresh()->stock_quantity);
    }

    public function test_out_of_stock_order_is_not_created(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale(['stock_quantity' => 2, 'initial_stock' => 10]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale, ['quantity' => 3]));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'OUT_OF_STOCK');
        $this->assertSame(0, Order::count());
        $this->assertSame(2, $sale->fresh()->stock_quantity);
    }

    public function test_fixed_type_delivery_date_uses_delivery_date_from(): void
    {
        $farmer = User::factory()->farmer()->create();
        $deliveryDate = now()->addDays(7)->toDateString();
        $sale = $this->createFixedSale([
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            'delivery_date_from' => $deliveryDate,
        ]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale));

        $response->assertStatus(201);
        // Order.delivery_dateは'date:Y-m-d'キャストにより、JSONでも"YYYY-MM-DD"のまま返る
        // (B5/B6実装時にGET /productsのdelivery_dateと表記を揃えるため変更した)
        $response->assertJsonPath('order.delivery_date', $deliveryDate);
    }

    /**
     * 電話注文の代理入力(F10)には配達予定日を選ぶ画面が無い。配達予定期間のある商品でも
     * delivery_dateを送らずに、従来通りdelivery_date_fromへ自動的にフォールバックすることを確認する
     * (購入者自身の注文(B4/B5)とは異なり、必須エラーにはならない)。
     */
    public function test_ranged_product_falls_back_to_delivery_date_from_without_selection(): void
    {
        $farmer = User::factory()->farmer()->create();
        $from = now()->addDays(5)->toDateString();
        $sale = $this->createFixedSale([
            'delivery_date_from' => $from,
            'delivery_date_to' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale));

        $response->assertStatus(201);
        $response->assertJsonPath('order.delivery_date', $from);
    }

    public function test_auto_type_delivery_date_is_pushed_back_one_day_after_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00', 'Asia/Tokyo'));

        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale([
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_AUTO,
            'earliest_delivery_days' => 1,
            'order_deadline_time' => '18:00:00',
        ]);

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale));

        $response->assertStatus(201);
        // 締切(18:00)を過ぎているため、本来の翌日配達(7/16)がさらに1日後ろ倒しになり7/17になる
        $response->assertJsonPath('order.delivery_date', '2026-07-17');

        Carbon::setTestNow();
    }

    public function test_payment_method_status_and_proxy_note_are_saved(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale, [
            'payment_method' => Order::PAYMENT_METHOD_CARD,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'proxy_note' => '7/15 電話にて受付',
        ]));

        $response->assertStatus(201);

        $order = Order::find($response->json('order.id'));
        $this->assertTrue($order->is_proxy_order);
        $this->assertSame(Order::PAYMENT_METHOD_CARD, $order->payment_method);
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertSame('7/15 電話にて受付', $order->proxy_note);
    }

    public function test_validation_error_leaves_no_order_or_user_behind(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createFixedSale();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/orders', $this->baseProxyOrderPayload($sale, [
            'quantity' => null,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('quantity');
        $this->assertSame(1, User::count());
        $this->assertSame(0, Order::count());
    }
}
