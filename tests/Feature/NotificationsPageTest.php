<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/notifications');

        $response->assertRedirect(route('login'));
    }

    public function test_farmer_is_redirected_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/notifications');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
        $response->assertSee('js/notifications.js', false);
    }

    public function test_page_embeds_correct_api_urls(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
        $response->assertSee('data-notifications-url="'.url('/api/v1/notifications').'"', false);
        $response->assertSee('data-mark-all-read-url="'.url('/api/v1/notifications/read-all').'"', false);
        $response->assertSee('data-order-detail-base-url="'.url('/orders').'"', false);
    }

    public function test_page_has_back_link_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_page_has_mark_all_read_button_and_list_container(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/notifications');

        $response->assertOk();
        $response->assertSee('id="notifications-mark-all-read-button"', false);
        $response->assertSee('id="notifications-list"', false);
        $response->assertSee('id="notifications-empty"', false);
    }
}
