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
}
