<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_farmer_home(): void
    {
        $response = $this->get('/farmer');

        $response->assertStatus(403);
    }

    public function test_buyer_cannot_access_farmer_home(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer');

        $response->assertStatus(403);
    }

    public function test_farmer_can_access_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
    }

    public function test_farmer_home_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_farmer_home_shows_farmer_name(): void
    {
        $farmer = User::factory()->farmer()->create(['name' => 'テスト農園']);

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('テスト農園');
    }

    public function test_farmer_home_has_link_to_settings(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('href="'.route('settings').'"', false);
    }

    public function test_farmer_home_has_link_to_delivery_confirmations(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.delivery-confirmations').'"', false);
    }

    public function test_farmer_home_has_link_to_orders(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.orders').'"', false);
    }

    public function test_farmer_home_shows_main_menu_labels(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('注文確認');
        $response->assertSee('商品管理');
        $response->assertSee('予約一覧');
        $response->assertSee('売上確認');
        $response->assertSee('お知らせ');
        $response->assertSee('設定');
    }

    public function test_farmer_home_does_not_link_to_unimplemented_screens(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        // 未実装の業務メニューはhref="#"のような意味のないリンクにしない
        $response->assertDontSee('href="#"', false);
        // 準備中の項目が実際にリンク化されていないことも確認する
        $response->assertSee('準備中');
    }

    public function test_farmer_home_has_logout_control(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('id="farmer-home-logout-button"', false);
    }

    public function test_farmer_home_loads_farmer_home_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('js/farmer-home.js', false);
    }
}
