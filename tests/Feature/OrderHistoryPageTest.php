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

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderFor(User $buyer): Order
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
        ]);

        $order = Order::create([
            'order_number' => '20260715-0001',
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

    // === B7: 注文履歴一覧 ===

    public function test_guest_is_redirected_to_login_for_index(): void
    {
        $response = $this->get('/orders');

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home_for_index(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_index(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
    }

    public function test_index_uses_common_layout(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_index_loads_javascript(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('js/order-history.js', false);
    }

    public function test_index_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('data-orders-url="'.url('/api/v1/orders').'"', false);
        $response->assertSee('data-order-detail-base-url="'.url('/orders').'"', false);
        $response->assertSee('data-order-confirm-base-url="'.url('/orders/confirm').'"', false);
    }

    public function test_index_has_back_link_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_index_has_filter_elements(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders');

        $response->assertOk();
        $response->assertSee('id="history-period-select"', false);
        $response->assertSee('id="history-month-select"', false);
    }

    // === 購入者向け注文詳細 ===

    public function test_guest_is_redirected_to_login_for_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->get('/orders/'.$order->id);

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home_for_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/orders/'.$order->id);

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_owner_can_access_show(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
    }

    public function test_other_buyer_cannot_access_show(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrderFor($owner);
        $otherBuyer = User::factory()->create();

        $response = $this->actingAs($otherBuyer)->get('/orders/'.$order->id);

        $response->assertStatus(403);
    }

    public function test_show_uses_common_layout(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_show_loads_javascript(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('js/order-detail.js', false);
    }

    public function test_show_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('data-order-url="'.url('/api/v1/orders/'.$order->id).'"', false);
        $response->assertSee('data-reorder-preview-url="'.url('/api/v1/orders/'.$order->id.'/reorder-preview').'"', false);
    }

    public function test_show_has_back_link_to_order_history(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('href="'.route('orders.index').'"', false);
    }

    public function test_show_has_reorder_button(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="order-detail-reorder-button"', false);
    }

    // === 数量変更・キャンセル相談(第2段階) ===

    public function test_show_has_change_request_section_container(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-change-request-section"', false);
    }

    public function test_show_has_quantity_change_request_button_label(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-request-quantity-change-button"', false);
        $response->assertSee('数量変更を相談する');
    }

    public function test_show_has_cancellation_request_button_label(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-request-cancellation-button"', false);
        $response->assertSee('キャンセルを相談する');
    }

    public function test_show_has_quantity_change_form_with_labelled_input(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-quantity-change-form"', false);
        $response->assertSee('for="detail-requested-quantity"', false);
        $response->assertSee('id="detail-requested-quantity"', false);
        $response->assertSee('id="detail-submit-quantity-change-button"', false);
        $response->assertSee('id="detail-cancel-quantity-change-form-button"', false);
    }

    public function test_show_has_pending_request_info_container(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-pending-request-info"', false);
        $response->assertSee('id="detail-pending-request-heading"', false);
        $response->assertSee('id="detail-pending-request-created-at"', false);
        $response->assertSee('農家からの連絡をお待ちください');
    }

    /**
     * ボタンの文言・入力欄はBladeに静的に書かれているためHTTPレスポンスで確認できるが、
     * APIパス・エラー文言はJS内で組み立てられるため、F9で確立したパターン
     * (対象JSファイルをfile_get_contentsで読み込みassertStringContainsStringで確認)を使う。
     */
    public function test_order_detail_js_contains_change_request_api_paths(): void
    {
        $js = file_get_contents(public_path('js/order-detail.js'));

        $this->assertStringContainsString('/quantity-change-requests', $js);
        $this->assertStringContainsString('/cancellation-requests', $js);
    }

    public function test_order_detail_js_contains_change_request_error_messages(): void
    {
        $js = file_get_contents(public_path('js/order-detail.js'));

        $this->assertStringContainsString('すでにこの注文について相談中です。', $js);
        $this->assertStringContainsString('この注文は現在、変更やキャンセルの相談を受け付けられません。', $js);
        $this->assertStringContainsString('数量が1点のため、数量変更はできません。キャンセル相談をご利用ください。', $js);
        $this->assertStringContainsString('現在の数量より少ない、1点以上の数量を入力してください。', $js);
        $this->assertStringContainsString('この注文は画面から変更相談できません。農家へ直接ご連絡ください。', $js);
    }

    /**
     * 購入者側の既存注文詳細は数量を「点」表記しているため、今回の相談表示・確認文も
     * 「個」ではなく「点」に揃える(農家側・他画面は今回の対象外)。
     */
    public function test_order_detail_js_uses_ten_as_quantity_unit_not_ko(): void
    {
        $js = file_get_contents(public_path('js/order-detail.js'));

        $this->assertStringContainsString('点から', $js);
        $this->assertStringContainsString('点へ変更する相談を農家へ送ります', $js);
        $this->assertStringNotContainsString('個から', $js);
        $this->assertStringNotContainsString('個へ変更する', $js);
    }

    public function test_show_change_request_messages_have_aria_live(): void
    {
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id);

        $response->assertOk();
        $response->assertSee('id="detail-change-request-message" class="message message-error" role="alert" aria-live="assertive"', false);
        $response->assertSee('id="detail-change-request-success" class="message message-success" role="status" aria-live="polite"', false);
    }

    public function test_order_detail_js_contains_confirmation_prompts(): void
    {
        $js = file_get_contents(public_path('js/order-detail.js'));

        $this->assertStringContainsString('よろしいですか?', $js);
        $this->assertStringContainsString('注文はこの時点ではキャンセルされません。', $js);
    }

    /**
     * 配達予定期間のある商品を「前回と同じ内容で注文」した場合に、前回の配達予定日
     * (reorder_params.delivery_date)がB5へのクエリ文字列に引き継がれることを確認する。
     */
    public function test_order_history_js_carries_delivery_date_when_reordering(): void
    {
        $js = file_get_contents(public_path('js/order-history.js'));

        $this->assertStringContainsString('data.reorder_params.delivery_date', $js);
        $this->assertStringContainsString("params.set('delivery_date', data.reorder_params.delivery_date)", $js);
    }

    public function test_order_detail_js_carries_delivery_date_when_reordering(): void
    {
        $js = file_get_contents(public_path('js/order-detail.js'));

        $this->assertStringContainsString('data.reorder_params.delivery_date', $js);
        $this->assertStringContainsString("params.set('delivery_date', data.reorder_params.delivery_date)", $js);
    }
}
