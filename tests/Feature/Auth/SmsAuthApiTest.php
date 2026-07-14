<?php

namespace Tests\Feature\Auth;

use App\Models\SmsVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SmsAuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ブラウザからの同一オリジンリクエストとして扱われるよう、
     * Sanctumのstateful判定(Referer/Origin)に一致するヘッダーを付ける。
     */
    private function asFrontend(): static
    {
        return $this->withHeader('Referer', 'http://localhost');
    }

    private function sendAndGetCode(string $phoneNumber): string
    {
        $this->asFrontend()->postJson('/api/v1/auth/sms/send', ['phone_number' => $phoneNumber]);

        return SmsVerification::where('phone_number', $phoneNumber)->latest('id')->first()->code;
    }

    public function test_send_returns_success_message_and_creates_verification_record(): void
    {
        $response = $this->postJson('/api/v1/auth/sms/send', ['phone_number' => '09011112222']);

        $response->assertOk();
        $response->assertJson(['message' => '認証コードを送信しました。']);
        $this->assertSame(1, SmsVerification::where('phone_number', '09011112222')->count());
    }

    public function test_send_with_invalid_phone_number_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/sms/send', ['phone_number' => '090-1111-2222']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
    }

    public function test_verify_logs_in_existing_buyer_and_establishes_session(): void
    {
        $buyer = User::factory()->create(['phone_number' => '09033334444']);
        $code = $this->sendAndGetCode('09033334444');

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09033334444',
            'code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $buyer->id);
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_verify_with_new_phone_number_and_no_name_or_address_requires_registration_info(): void
    {
        $code = $this->sendAndGetCode('09055556666');

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09055556666',
            'code' => $code,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'REGISTRATION_INFO_REQUIRED');
        $this->assertSame(0, User::where('phone_number', '09055556666')->count());
    }

    public function test_verify_with_new_phone_number_and_registration_info_creates_buyer_and_logs_in(): void
    {
        $code = $this->sendAndGetCode('09077778888');

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09077778888',
            'code' => $code,
            'name' => '新規 太郎',
            'address' => '沖縄県宮古島市4-5-6',
            'email' => 'shinki@example.com',
        ]);

        $response->assertOk();

        $user = User::where('phone_number', '09077778888')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_BUYER, $user->role);
        $this->assertSame('新規 太郎', $user->name);
        $this->assertSame('沖縄県宮古島市4-5-6', $user->address);
        $this->assertSame('shinki@example.com', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_reuses_same_code_after_registration_info_required_failure(): void
    {
        $code = $this->sendAndGetCode('09099990000');

        // 1回目: 名前・住所が無いので登録情報が必要
        $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09099990000',
            'code' => $code,
        ])->assertStatus(422);

        // 2回目: 同じコードのまま名前・住所を追加して再送信すると成功する
        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09099990000',
            'code' => $code,
            'name' => '再送 花子',
            'address' => '沖縄県宮古島市7-8-9',
        ]);

        $response->assertOk();
        $this->assertSame(1, User::where('phone_number', '09099990000')->count());
    }

    public function test_verify_with_unsent_phone_number_returns_sms_code_not_found(): void
    {
        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09010101010',
            'code' => '123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SMS_CODE_NOT_FOUND');
    }

    public function test_verify_with_wrong_code_returns_sms_code_mismatch_and_increments_attempt_count(): void
    {
        $this->sendAndGetCode('09020202020');
        $verification = SmsVerification::where('phone_number', '09020202020')->latest('id')->first();

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09020202020',
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SMS_CODE_MISMATCH');
        $this->assertSame(1, $verification->fresh()->attempt_count);
    }

    public function test_verify_with_expired_code_returns_sms_code_expired(): void
    {
        $code = $this->sendAndGetCode('09030303030');

        Carbon::setTestNow(now()->addMinutes(10));

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09030303030',
            'code' => $code,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SMS_CODE_EXPIRED');

        Carbon::setTestNow();
    }

    public function test_verify_locks_after_five_failed_attempts(): void
    {
        $this->sendAndGetCode('09040404040');

        for ($i = 0; $i < 5; $i++) {
            $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
                'phone_number' => '09040404040',
                'code' => '000000',
            ])->assertJsonPath('error.code', 'SMS_CODE_MISMATCH');
        }

        $response = $this->asFrontend()->postJson('/api/v1/auth/sms/verify', [
            'phone_number' => '09040404040',
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SMS_CODE_LOCKED');
    }
}
