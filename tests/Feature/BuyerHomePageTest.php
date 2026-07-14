<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_farmer_is_redirected_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('js/buyer-home.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-products-url="'.url('/api/v1/products').'"', false);
        $response->assertSee('data-announcements-url="'.url('/api/v1/announcements').'"', false);
        $response->assertSee('data-product-detail-base-url="'.url('/products').'"', false);
    }

    public function test_authenticated_buyer_sees_logout_button_and_settings_link(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/');

        $response->assertOk();
        $response->assertSee('id="buyer-home-logout-button"', false);
        $response->assertSee('href="'.route('settings').'"', false);
    }

    public function test_guest_sees_login_and_register_links(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
        $response->assertDontSee('id="buyer-home-logout-button"', false);
    }

    public function test_page_has_category_tabs_container(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="category-tabs"', false);
    }

    public function test_page_has_announcements_and_products_containers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="announcements-list"', false);
        $response->assertSee('id="products-list"', false);
        $response->assertSee('id="products-empty"', false);
    }
}
