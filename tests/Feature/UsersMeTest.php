<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_get_returns_401(): void
    {
        $response = $this->getJson('/api/v1/users/me');

        $response->assertStatus(401);
    }

    public function test_buyer_can_get_own_profile(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/v1/users/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $buyer->id)
            ->assertJsonPath('user.role', User::ROLE_BUYER);
    }

    public function test_farmer_can_get_own_profile(): void
    {
        $farmer = User::factory()->farmer()->create();
        Sanctum::actingAs($farmer);

        $response = $this->getJson('/api/v1/users/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $farmer->id)
            ->assertJsonPath('user.role', User::ROLE_FARMER);
    }

    public function test_put_updates_name_email_and_address(): void
    {
        $user = User::factory()->create([
            'name' => '変更前太郎',
            'address' => '変更前住所',
            'email' => 'before@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => '変更後太郎',
            'email' => 'after@example.com',
            'address' => '変更後住所',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', '変更後太郎')
            ->assertJsonPath('user.email', 'after@example.com')
            ->assertJsonPath('user.address', '変更後住所');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '変更後太郎',
            'email' => 'after@example.com',
            'address' => '変更後住所',
        ]);
    }

    public function test_email_omitted_keeps_existing_value(): void
    {
        // emailキー自体を送らなかった場合に既存のメールアドレスが消えないことを確認する
        // (実装中に一度、nullで上書きされてしまうバグがあったための回帰テスト)
        $user = User::factory()->create(['email' => 'keep@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => '名前だけ変更',
            'address' => $user->address,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'keep@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'keep@example.com',
        ]);
    }

    public function test_updating_with_own_current_email_succeeds(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => 'me@example.com',
        ]);

        $response->assertOk();
    }

    public function test_email_already_used_by_another_user_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_email_format_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_non_updatable_fields_are_not_changed(): void
    {
        $user = User::factory()->create([
            'phone_number' => '09011112222',
            'role' => User::ROLE_BUYER,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => $user->email,
            'phone_number' => '08099998888',
            'role' => User::ROLE_FARMER,
            'is_active' => false,
            'password' => 'new-password',
            'id' => 9999,
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertSame('09011112222', $user->phone_number);
        $this->assertSame(User::ROLE_BUYER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotEquals(9999, $user->id);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_name_only_update_keeps_other_fields(): void
    {
        $user = User::factory()->create([
            'address' => '変更されない住所',
            'email' => '変更されない@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => '名前のみ変更後',
            'address' => $user->address,
        ]);

        $response->assertOk()->assertJsonPath('user.name', '名前のみ変更後');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '名前のみ変更後',
            'address' => '変更されない住所',
            'email' => '変更されない@example.com',
        ]);
    }

    public function test_address_only_update_keeps_other_fields(): void
    {
        $user = User::factory()->create([
            'name' => '変更されない名前',
            'email' => '変更されない2@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => '住所のみ変更後',
        ]);

        $response->assertOk()->assertJsonPath('user.address', '住所のみ変更後');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '変更されない名前',
            'address' => '住所のみ変更後',
            'email' => '変更されない2@example.com',
        ]);
    }

    public function test_profile_response_only_contains_expected_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone_number', 'address', 'role', 'is_active', 'notify_by_email', 'notify_by_sms'],
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.created_at')
            ->assertJsonMissingPath('user.updated_at');
    }

    // === 通知方法の設定(B9) ===

    public function test_notify_by_email_can_be_enabled_when_email_is_registered(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'notify_by_email' => false]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => 'me@example.com',
            'notify_by_email' => true,
        ]);

        $response->assertOk()->assertJsonPath('user.notify_by_email', true);
        $this->assertTrue($user->fresh()->notify_by_email);
    }

    public function test_notify_by_sms_can_be_enabled_independently_of_email(): void
    {
        $user = User::factory()->create(['email' => null, 'notify_by_sms' => false]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'notify_by_sms' => true,
        ]);

        $response->assertOk()->assertJsonPath('user.notify_by_sms', true);
        $this->assertTrue($user->fresh()->notify_by_sms);
    }

    public function test_enabling_notify_by_email_without_registered_email_returns_422(): void
    {
        $user = User::factory()->create(['email' => null]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'notify_by_email' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('notify_by_email');
        $this->assertFalse($user->fresh()->notify_by_email);
    }

    public function test_enabling_notify_by_email_while_clearing_email_in_same_request_returns_422(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => $user->name,
            'address' => $user->address,
            'email' => '',
            'notify_by_email' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('notify_by_email');
    }

    public function test_notify_fields_omitted_keep_existing_value(): void
    {
        $user = User::factory()->create(['notify_by_email' => false, 'notify_by_sms' => true]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/users/me', [
            'name' => '通知設定は送らない更新',
            'address' => $user->address,
        ]);

        $response->assertOk();
        $this->assertFalse($user->fresh()->notify_by_email);
        $this->assertTrue($user->fresh()->notify_by_sms);
    }

    public function test_unauthenticated_put_returns_401(): void
    {
        $response = $this->putJson('/api/v1/users/me', [
            'name' => 'テスト',
            'address' => 'テスト住所',
        ]);

        $response->assertStatus(401);
    }
}
