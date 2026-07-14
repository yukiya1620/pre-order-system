<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFormPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_register_page(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('data-mode="register"', false);
    }

    public function test_guest_can_access_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('data-mode="login"', false);
    }

    public function test_authenticated_farmer_visiting_login_is_redirected_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/login');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_authenticated_farmer_visiting_register_is_redirected_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/register');

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_authenticated_buyer_visiting_login_is_redirected_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/login');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_authenticated_buyer_visiting_register_is_redirected_to_buyer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/register');

        $response->assertRedirect(route('buyer.home'));
    }

    public function test_page_uses_common_layout(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('js/auth-form.js', false);
    }

    public function test_register_page_has_all_wizard_steps(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('data-step-name="name"', false);
        $response->assertSee('data-step-name="phone"', false);
        $response->assertSee('data-step-name="address"', false);
        $response->assertSee('data-step-name="email"', false);
        $response->assertSee('data-step-name="code"', false);
    }

    public function test_login_page_has_phone_and_code_steps(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('data-step-name="phone"', false);
        $response->assertSee('data-step-name="code"', false);
    }

    public function test_page_has_resend_button_and_countdown_element(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('id="auth-resend-button"', false);
        $response->assertSee('id="auth-resend-countdown"', false);
    }

    public function test_login_page_has_email_password_switch_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('id="auth-switch-to-email-button"', false);
        $response->assertSee('id="email-login-form"', false);
    }

    public function test_register_page_has_link_to_login(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('href="'.route('login').'"', false);
    }

    public function test_login_page_has_link_to_register(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('href="'.route('register').'"', false);
    }
}
