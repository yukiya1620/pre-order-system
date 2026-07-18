<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerOrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_page(): void
    {
        $response = $this->get('/farmer/orders');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/orders');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.home').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('js/farmer-orders.js', false);
    }

    public function test_status_filter_options_are_present(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('未完了');
        $response->assertSee('すべて');
        $response->assertSee('受付済');
        $response->assertSee('配達確認済');
        $response->assertSee('配達日変更');
        $response->assertSee('配達完了');
        $response->assertSee('キャンセル');
    }

    public function test_pager_controls_are_present(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('id="orders-prev-button"', false);
        $response->assertSee('id="orders-next-button"', false);
        $response->assertSee('id="orders-page-indicator"', false);
    }

    public function test_phone_order_links_to_order_form(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('電話注文');
        $response->assertSee('href="'.route('farmer.orders.create').'"', false);
        $response->assertDontSee('href="#"', false);
    }

    // === 購入者からの変更相談(第3段階) ===

    public function test_status_filter_has_pending_change_request_option(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders');

        $response->assertOk();
        $response->assertSee('value="pending_change_request"', false);
        $response->assertSee('相談あり');
    }

    public function test_page_js_reads_filter_query_string_with_url_search_params(): void
    {
        $js = file_get_contents(public_path('js/farmer-orders.js'));

        $this->assertStringContainsString('new URLSearchParams(window.location.search)', $js);
        $this->assertStringContainsString("params.get('filter') === 'pending_change_request'", $js);
    }

    public function test_page_js_sends_has_pending_change_request_param(): void
    {
        $js = file_get_contents(public_path('js/farmer-orders.js'));

        $this->assertStringContainsString("params.set('has_pending_change_request', '1')", $js);
    }

    public function test_page_js_builds_change_request_badge_only_when_present(): void
    {
        $js = file_get_contents(public_path('js/farmer-orders.js'));

        $this->assertStringContainsString('order-card__change-request-badge', $js);
        $this->assertStringContainsString('相談あり', $js);
        $this->assertStringContainsString('if (order.pending_change_request)', $js);
    }

    /**
     * カードの数量表示は商品ごとのunit_label(本・パックなど)を使うべきで、
     * 「袋」への固定連結ではないことを確認する(既存の表示不整合の修正)。
     */
    public function test_page_js_uses_product_unit_label_for_card_quantity(): void
    {
        $js = file_get_contents(public_path('js/farmer-orders.js'));

        $this->assertStringContainsString('function unitLabelFor', $js);
        $this->assertStringContainsString('unit_label', $js);
        $this->assertStringNotContainsString("item.quantity + '袋'", $js);
    }
}
