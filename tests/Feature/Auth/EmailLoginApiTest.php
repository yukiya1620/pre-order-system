<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailLoginApiTest extends TestCase
{
    use RefreshDatabase;

    private function asFrontend(): static
    {
        return $this->withHeader('Referer', 'http://localhost');
    }

    public function test_farmer_can_login_with_email_and_password(): void
    {
        $farmer = User::factory()->farmer()->create([
            'email' => 'farmer-login@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->asFrontend()->postJson('/api/v1/auth/login', [
            'email' => 'farmer-login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $farmer->id);
        $this->assertAuthenticatedAs($farmer);
    }

    public function test_buyer_can_login_with_email_and_password(): void
    {
        $buyer = User::factory()->create([
            'email' => 'buyer-login@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->asFrontend()->postJson('/api/v1/auth/login', [
            'email' => 'buyer-login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $buyer->id);
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_wrong_password_returns_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->asFrontend()->postJson('/api/v1/auth/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'incorrect',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_unregistered_email_returns_invalid_credentials(): void
    {
        $response = $this->asFrontend()->postJson('/api/v1/auth/login', [
            'email' => 'notfound@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_missing_fields_return_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }
}
