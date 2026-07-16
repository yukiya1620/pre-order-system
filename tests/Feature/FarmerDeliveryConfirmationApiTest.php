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

class FarmerDeliveryConfirmationApiTest extends TestCase
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

    private function createOrder(ProductSale $sale, string $status = Order::STATUS_RECEIVED): Order
    {
        $buyer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'TEST-'.random_int(10000, 99999),
            'user_id' => $buyer->id,
            'status' => $status,
            'total_amount' => $sale->price,
            'delivery_address' => 'テスト住所',
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => 1,
            'subtotal' => $sale->price,
        ]);

        return $order;
    }

    private function createConfirmation(Order $order, ?string $notifiedAt = null): DeliveryConfirmation
    {
        return DeliveryConfirmation::create([
            'order_id' => $order->id,
            'notified_at' => $notifiedAt ?? now(),
        ]);
    }

    // === index ===

    public function test_guest_cannot_list_delivery_confirmations(): void
    {
        $response = $this->getJson('/api/v1/farmer/delivery-confirmations');

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_list_delivery_confirmations(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->getJson('/api/v1/farmer/delivery-confirmations');

        $response->assertStatus(403);
    }

    public function test_index_returns_only_unresponded_ordered_by_notified_at(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();

        $older = $this->createConfirmation($this->createOrder($sale), '2026-07-10 09:00:00');
        $newer = $this->createConfirmation($this->createOrder($sale), '2026-07-12 09:00:00');

        $responded = $this->createConfirmation($this->createOrder($sale), '2026-07-11 09:00:00');
        $responded->update([
            'response' => '配達可能',
            'responded_at' => now(),
            'buyer_notified_at' => now(),
        ]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertJsonCount(2, 'delivery_confirmations');
        $response->assertJsonPath('delivery_confirmations.0.id', $older->id);
        $response->assertJsonPath('delivery_confirmations.1.id', $newer->id);
    }

    // === respond: アクセス制御 ===

    public function test_guest_cannot_respond(): void
    {
        $sale = $this->createSale();
        $confirmation = $this->createConfirmation($this->createOrder($sale));

        $response = $this->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達可能',
        ]);

        $response->assertStatus(401);
    }

    public function test_buyer_cannot_respond(): void
    {
        $buyer = User::factory()->create();
        $sale = $this->createSale();
        $confirmation = $this->createConfirmation($this->createOrder($sale));

        $response = $this->actingAs($buyer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達可能',
        ]);

        $response->assertStatus(403);
    }

    // === respond: 正常系 ===

    public function test_farmer_can_respond_delivery_possible(): void
    {
        $this->travelTo(Carbon::parse('2026-07-14 08:00:00', 'Asia/Tokyo'));

        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $order = $this->createOrder($sale);
        $confirmation = $this->createConfirmation($order);

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達可能',
        ]);

        $response->assertOk();

        $confirmation->refresh();
        $this->assertSame('配達可能', $confirmation->response);
        $this->assertTrue($confirmation->responded_at->equalTo(now()));
        $this->assertTrue($confirmation->buyer_notified_at->equalTo(now()));

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERY_CONFIRMED, $order->status);

        $notification = Notification::where('related_order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame($order->user_id, $notification->user_id);
        $this->assertSame('配達予定確認', $notification->type);
        $this->assertStringContainsString('予定通り', $notification->body);
    }

    public function test_farmer_can_respond_delivery_date_changed(): void
    {
        $this->travelTo(Carbon::parse('2026-07-14 08:00:00', 'Asia/Tokyo'));

        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $order = $this->createOrder($sale);
        $confirmation = $this->createConfirmation($order);
        $newDate = '2026-07-20';

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達日変更',
            'new_delivery_date' => $newDate,
        ]);

        $response->assertOk();

        $confirmation->refresh();
        $this->assertSame('配達日変更', $confirmation->response);
        $this->assertSame($newDate, $confirmation->new_delivery_date->toDateString());

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERY_CHANGED, $order->status);
        $this->assertSame($newDate, $order->delivery_date->toDateString());

        $notification = Notification::where('related_order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame($order->user_id, $notification->user_id);
        $this->assertSame('配達予定変更', $notification->type);
        $this->assertStringContainsString('変更になりました', $notification->body);
    }

    /**
     * 数量変更・キャンセル相談は「農家が電話等で確認後、別途OrderAdjustmentServiceで
     * 数量減少/キャンセルを確定する」前提のため、respond時点ではorders.statusを変えない。
     */
    public function test_farmer_can_respond_quantity_change_without_altering_order_status(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $order = $this->createOrder($sale, Order::STATUS_RECEIVED);
        $confirmation = $this->createConfirmation($order);

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '数量変更',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);

        $notification = Notification::where('related_order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame($order->user_id, $notification->user_id);
        $this->assertSame('配達予定確認', $notification->type);
        $this->assertStringContainsString('数量のご相談', $notification->body);
    }

    public function test_farmer_can_respond_cancellation_consultation_without_altering_order_status(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $order = $this->createOrder($sale, Order::STATUS_RECEIVED);
        $confirmation = $this->createConfirmation($order);

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => 'キャンセル相談',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_RECEIVED, $order->status);

        $notification = Notification::where('related_order_id', $order->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame($order->user_id, $notification->user_id);
        $this->assertSame('配達予定確認', $notification->type);
        $this->assertStringContainsString('ご相談があります', $notification->body);
    }

    // === respond: 二重回答拒否 ===

    public function test_second_response_to_same_confirmation_is_rejected(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $order = $this->createOrder($sale);
        $confirmation = $this->createConfirmation($order);

        $first = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達可能',
        ]);
        $first->assertOk();

        $second = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達日変更',
            'new_delivery_date' => now()->addDays(10)->toDateString(),
        ]);

        $second->assertStatus(422);
        $second->assertJsonPath('error.code', 'ALREADY_RESPONDED');

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERY_CONFIRMED, $order->status);
        $this->assertSame(1, Notification::where('related_order_id', $order->id)->count());
    }

    // === respond: バリデーション ===

    public function test_invalid_response_value_returns_validation_error(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $confirmation = $this->createConfirmation($this->createOrder($sale));

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '不明',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('response');

        $confirmation->refresh();
        $this->assertNull($confirmation->responded_at);
    }

    public function test_delivery_date_changed_without_new_date_returns_validation_error(): void
    {
        $farmer = User::factory()->farmer()->create();
        $sale = $this->createSale();
        $confirmation = $this->createConfirmation($this->createOrder($sale));

        $response = $this->actingAs($farmer)->postJson("/api/v1/farmer/delivery-confirmations/{$confirmation->id}/respond", [
            'response' => '配達日変更',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('new_delivery_date');

        $confirmation->refresh();
        $this->assertNull($confirmation->responded_at);
    }

    // === respond: 存在しない確認 ===

    public function test_responding_to_nonexistent_confirmation_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->postJson('/api/v1/farmer/delivery-confirmations/99999/respond', [
            'response' => '配達可能',
        ]);

        $response->assertStatus(404);
    }
}
