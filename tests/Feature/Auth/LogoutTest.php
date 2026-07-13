<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LogoutTest extends TestCase
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

    /**
     * RequestGuard(sanctumガード)は解決したユーザーをリクエストの間キャッシュするため、
     * 同一テストメソッド内でログアウト直後に別リクエストを送る場合、キャッシュを破棄してから
     * 送らないと、DBやセッションの状態が変わっていても古い認証結果を返してしまう。
     */
    private function forgetAuthGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_buyer_can_logout_via_session(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->asFrontend()->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    public function test_farmer_can_logout_via_session(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->asFrontend()->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    public function test_session_logout_prevents_further_access_with_same_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->asFrontend()
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->forgetAuthGuards();

        $this->asFrontend()
            ->getJson('/api/v1/users/me')
            ->assertStatus(401);
    }

    public function test_session_logout_does_not_delete_personal_access_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('existing-token');

        $this->actingAs($user)->asFrontend()
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertNotNull(PersonalAccessToken::find($token->accessToken->id));
    }

    public function test_bearer_token_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('bearer-token');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    public function test_bearer_logout_prevents_further_use_of_same_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('bearer-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->forgetAuthGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/users/me')
            ->assertStatus(401);
    }

    public function test_bearer_logout_deletes_only_the_used_token(): void
    {
        $user = User::factory()->create();
        $usedToken = $user->createToken('used-token');
        $otherToken = $user->createToken('other-token');

        $this->withToken($usedToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertNull(PersonalAccessToken::find($usedToken->accessToken->id));
        $this->assertNotNull(PersonalAccessToken::find($otherToken->accessToken->id));
    }

    public function test_other_token_of_same_user_still_works_after_bearer_logout(): void
    {
        $user = User::factory()->create();
        $usedToken = $user->createToken('used-token');
        $otherToken = $user->createToken('other-token');

        $this->withToken($usedToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->forgetAuthGuards();

        $this->withToken($otherToken->plainTextToken)
            ->getJson('/api/v1/users/me')
            ->assertOk();
    }

    public function test_logout_response_shape(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->asFrontend()->postJson('/api/v1/auth/logout');

        $response->assertOk()->assertExactJson([
            'message' => 'ログアウトしました。',
        ]);
    }

    /**
     * 回帰テスト: セッションCookieとBearerトークンが同時に送られてきた場合、
     * Sanctumのガード(vendor/laravel/sanctum/src/Guard.php)はwebガード(セッション)を
     * 先にチェックするため、セッション認証が優先される。よってこの場合のlogout()は
     * セッション側の分岐(Auth::guard('web')->logout())に進むはずで、
     * 同時に送ったBearerトークンには一切触れてはいけない。
     */
    public function test_session_auth_takes_precedence_when_session_and_bearer_are_both_present(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('bearer-token');

        $this->actingAs($user)->asFrontend()
            ->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // 同時に送ったPersonal Access Tokenは削除されていないこと
        $this->assertNotNull(PersonalAccessToken::find($token->accessToken->id));

        $this->forgetAuthGuards();

        // withToken()で付けたAuthorizationヘッダーはdefaultHeadersに残り続けるため、
        // 「セッション・Bearerどちらも無い」状態を作るには明示的に外す必要がある。
        $this->withoutToken();

        // Webセッションからはログアウトされていること(セッション・Bearerどちらも無い状態でアクセスすると401)
        $this->asFrontend()
            ->getJson('/api/v1/users/me')
            ->assertStatus(401);

        $this->forgetAuthGuards();

        // セッションを使わず、削除されていないはずのBearerトークンだけでアクセスすると200になること
        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/users/me')
            ->assertOk();
    }
}
