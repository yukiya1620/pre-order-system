<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * / は購入者トップ(B3)。設計書・公開APIの方針に合わせ未ログインでも閲覧できる
     * (詳細な権限分岐・表示内容はBuyerHomePageTestで検証済み)。
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
