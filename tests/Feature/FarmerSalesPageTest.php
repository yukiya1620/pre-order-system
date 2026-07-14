<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerSalesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_page(): void
    {
        $response = $this->get('/farmer/sales');

        $response->assertStatus(403);
    }

    public function test_buyer_cannot_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/sales');

        $response->assertStatus(403);
    }

    public function test_farmer_can_access_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('売上確認');
    }

    public function test_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.home').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('js/farmer-sales.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('data-sales-summary-url="'.url('/api/v1/farmer/sales-summary').'"', false);
        $response->assertSee('data-sales-by-product-url="'.url('/api/v1/farmer/sales-by-product').'"', false);
    }

    public function test_page_shows_confirmed_sales_cards(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('確定売上');
        $response->assertSee('本日');
        $response->assertSee('今月');
        $response->assertSee('今年');
    }

    public function test_page_shows_pending_sales_section(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('予定売上(全期間)');
        $response->assertSee('未配達でキャンセルされていない注文の合計です。');
    }

    public function test_page_shows_confirmed_sales_note_about_unpaid_and_refunded(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('確定売上には、未払い・返金済みの注文も含まれます');
    }

    public function test_page_shows_payment_status_and_method_breakdown_labels(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('支払い状況の内訳');
        $response->assertSee('支払い済み');
        $response->assertSee('未払い');
        $response->assertSee('返金済み');
        $response->assertSee('支払い方法の内訳');
        $response->assertSee('現金');
        $response->assertSee('カード');
        $response->assertSee('PayPay');
    }

    public function test_page_has_period_toggle_buttons_with_month_active_by_default(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('data-period="today"', false);
        $response->assertSee('data-period="month"', false);
        $response->assertSee('data-period="year"', false);
        $response->assertSee('sales-period-button sales-period-button--active" data-period="month"', false);
    }

    public function test_page_has_empty_state_text_for_sales_by_product(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('この期間の商品別売上はありません。');
    }

    public function test_page_has_error_message_elements_for_each_section(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('id="sales-summary-message"', false);
        $response->assertSee('id="sales-by-product-message"', false);
    }
}
