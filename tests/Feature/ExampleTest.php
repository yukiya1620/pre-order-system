<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * / は購入者トップ(B3の暫定版)。未認証アクセスは/loginへ誘導される仕様になったため、
     * 200ではなくリダイレクトを確認する(詳細な権限分岐はBuyerHomePageTestで検証済み)。
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
