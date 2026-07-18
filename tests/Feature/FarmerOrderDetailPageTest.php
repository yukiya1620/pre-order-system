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

class FarmerOrderDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '検証用の説明文',
            'category_id' => $category->id,
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3),
            'status' => '販売中',
        ]);

        $buyer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'F4TEST-0001',
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 500,
            'delivery_address' => 'テスト住所',
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

    public function test_guest_cannot_access_page(): void
    {
        $order = $this->createOrder();

        $response = $this->get('/farmer/orders/'.$order->id);

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $order = $this->createOrder();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/orders/'.$order->id);

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
    }

    public function test_nonexistent_order_returns_404(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/999999');

        $response->assertStatus(404);
    }

    public function test_page_uses_common_layout(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_orders_list(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.orders').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('js/farmer-order-detail.js', false);
    }

    public function test_page_embeds_correct_order_api_url(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('data-order-url="'.url('/api/v1/farmer/orders/'.$order->id).'"', false);
    }

    public function test_page_has_required_section_headings(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('配達情報');
        $response->assertSee('購入者情報');
        $response->assertSee('商品明細');
        $response->assertSee('支払い情報');
        $response->assertSee('配達確認');
        $response->assertSee('注文の変更');
    }

    public function test_page_has_order_action_controls(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('購入者へ電話等で確認済みです');
        $response->assertSee('この数量に変更する');
        $response->assertSee('注文をキャンセルする');
        $response->assertSee('id="detail-new-quantity"', false);
        $response->assertSee('id="detail-confirmed-with-buyer"', false);
    }

    public function test_orders_list_links_to_detail_page(): void
    {
        $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('data-order-detail-base-url="'.url('/farmer/orders').'"', false);
    }

    // === 購入者からのご相談(第3段階) ===

    public function test_page_has_change_request_section_container(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-change-request-section"', false);
        $response->assertSee('購入者からのご相談');
    }

    public function test_page_has_change_request_type_quantity_and_datetime_elements(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-change-request-type"', false);
        $response->assertSee('id="detail-change-request-summary"', false);
        $response->assertSee('id="detail-change-request-created-at"', false);
    }

    public function test_page_has_change_request_note_field_with_maxlength(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('for="detail-change-request-note"', false);
        $response->assertSee('id="detail-change-request-note" maxlength="255"', false);
    }

    public function test_page_has_resolve_without_change_button(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-resolve-without-change-button"', false);
        $response->assertSee('変更せず相談を終了する');
    }

    public function test_page_has_change_request_aria_live_attributes(): void
    {
        $order = $this->createOrder();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-change-request-message" class="message message-error" role="alert" aria-live="assertive"', false);
        $response->assertSee('id="detail-change-request-success" class="message message-success" role="status" aria-live="polite"', false);
    }

    /**
     * F9で確立したパターン(JSファイルをfile_get_contentsで読み込みassertStringContainsStringで確認)。
     */
    public function test_order_detail_js_contains_resolve_without_change_api_path(): void
    {
        $js = file_get_contents(public_path('js/farmer-order-detail.js'));

        $this->assertStringContainsString('/api/v1/farmer/order-change-requests/', $js);
        $this->assertStringContainsString('/resolve-without-change', $js);
    }

    public function test_order_detail_js_contains_resolve_without_change_confirmation_text(): void
    {
        $js = file_get_contents(public_path('js/farmer-order-detail.js'));

        $this->assertStringContainsString('注文内容を変更せず', $js);
        $this->assertStringContainsString('購入者へ通知されます', $js);
    }

    public function test_order_detail_js_handles_already_resolved_error(): void
    {
        $js = file_get_contents(public_path('js/farmer-order-detail.js'));

        $this->assertStringContainsString('ALREADY_RESOLVED', $js);
        $this->assertStringContainsString('この相談はすでに対応済みです。最新の状態を再読み込みします。', $js);
    }

    public function test_order_detail_js_uses_unit_label_from_order_item_with_fallback(): void
    {
        $js = file_get_contents(public_path('js/farmer-order-detail.js'));

        $this->assertStringContainsString('unit_label', $js);
        $this->assertStringContainsString("return unit || '袋';", $js);
    }

    /**
     * 商品明細テーブルの数量表示も、相談セクションと同じunitLabelForを使うべきで、
     * 「袋」への固定連結ではないことを確認する(既存の表示不整合の修正)。
     */
    public function test_order_detail_js_item_table_uses_unit_label_not_fixed_bag(): void
    {
        $js = file_get_contents(public_path('js/farmer-order-detail.js'));

        $this->assertStringContainsString('quantityCell.textContent = item.quantity + unitLabelFor(item);', $js);
        $this->assertStringNotContainsString("item.quantity + '袋'", $js);
    }
}
