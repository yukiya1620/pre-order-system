<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerDeliveryConfirmationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_page(): void
    {
        $response = $this->get('/farmer/delivery-confirmations');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_cannot_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/delivery-confirmations');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_farmer_can_access_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.home').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertSee('js/farmer-delivery-confirmations.js', false);
    }
}
