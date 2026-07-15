<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect(route('login'));
    }

    public function test_buyer_can_access_settings_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/settings');

        $response->assertOk();
    }

    public function test_farmer_can_access_settings_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/settings');

        $response->assertOk();
    }

    public function test_settings_page_has_required_form_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('id="name"', false);
        $response->assertSee('id="email"', false);
        $response->assertSee('id="address"', false);
        $response->assertSee('id="save-button"', false);
    }

    public function test_phone_number_and_role_are_not_editable_inputs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertDontSee('name="phone_number"', false);
        $response->assertDontSee('name="role"', false);
    }

    public function test_notify_settings_checkboxes_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('id="notify_by_email"', false);
        $response->assertSee('id="notify_by_sms"', false);
    }

    public function test_buyer_back_link_points_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/settings');

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_farmer_back_link_points_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/settings');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.home').'"', false);
    }

    public function test_common_stylesheet_is_loaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('css/app.css', false);
    }

    public function test_settings_javascript_is_loaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('js/settings.js', false);
    }

    public function test_csrf_meta_tag_is_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
    }
}
