<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerOrderFormPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_page(): void
    {
        $response = $this->get('/farmer/orders/create');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/orders/create');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('電話注文の代理入力');
    }

    public function test_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_orders_list(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.orders').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('js/farmer-order-form.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('data-products-url="'.url('/api/v1/products').'"', false);
        $response->assertSee('data-orders-api-url="'.url('/api/v1/farmer/orders').'"', false);
        $response->assertSee('data-order-detail-base-url="'.url('/farmer/orders').'"', false);
    }

    public function test_page_has_expected_form_fields(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('id="phone-number"', false);
        $response->assertSee('id="buyer-name"', false);
        $response->assertSee('id="buyer-address"', false);
        $response->assertSee('id="product-sale"', false);
        $response->assertSee('id="quantity"', false);
        $response->assertSee('id="delivery-time-slot"', false);
        $response->assertSee('id="payment-method"', false);
        $response->assertSee('id="payment-status"', false);
        $response->assertSee('id="proxy-note"', false);
    }

    public function test_registration_fields_are_hidden_by_default(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('id="registration-fields" hidden', false);
    }

    public function test_page_has_success_section_with_navigation_links(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/orders/create');

        $response->assertOk();
        $response->assertSee('id="order-form-success"', false);
        $response->assertSee('id="success-order-number"', false);
        $response->assertSee('id="success-delivery-date"', false);
        $response->assertSee('id="success-order-detail-link"', false);
        $response->assertSee('id="order-form-continue-button"', false);
    }
}
