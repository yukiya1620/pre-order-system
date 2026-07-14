<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
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
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/');

        $response->assertOk();
        $response->assertSee('js/buyer-home.js', false);
    }

    public function test_page_has_logout_button(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/');

        $response->assertOk();
        $response->assertSee('id="buyer-home-logout-button"', false);
    }
}
