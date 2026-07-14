<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerAnnouncementsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_page(): void
    {
        $response = $this->get('/farmer/announcements');

        $response->assertStatus(403);
    }

    public function test_buyer_cannot_access_page(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/farmer/announcements');

        $response->assertStatus(403);
    }

    public function test_farmer_can_access_page(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
    }

    public function test_page_uses_common_layout(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_has_back_link_to_farmer_home(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('href="'.route('farmer.home').'"', false);
    }

    public function test_page_loads_javascript(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('js/farmer-announcements.js', false);
    }

    public function test_page_shows_title(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('お知らせ投稿');
    }

    public function test_page_has_post_form_fields(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('id="announcement-title"', false);
        $response->assertSee('id="announcement-body"', false);
        $response->assertSee('id="announcement-is-published"', false);
        $response->assertSee('id="announcement-submit-button"', false);
    }

    /**
     * 定型文ボタンの実体はfarmer-announcements.jsがdocument.createElement()で
     * 実行時に生成するため、Feature Test(JS非実行)ではBlade側の
     * 「ボタンを差し込む空のコンテナ」の存在までしか確認できない。
     * 文言そのものの確認は別テスト(test_preset_button_phrases_are_defined_in_javascript)で行う。
     */
    public function test_page_has_preset_buttons_container(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('id="announcement-preset-buttons"', false);
    }

    /**
     * 定型文ボタンの3文言はBladeではなくJSファイル内の配列(PRESET_PHRASES)で
     * 管理されているため、HTTPレスポンスではなくJSファイルの中身を直接確認する。
     */
    public function test_preset_button_phrases_are_defined_in_javascript(): void
    {
        $jsPath = public_path('js/farmer-announcements.js');
        $jsContent = file_get_contents($jsPath);

        $this->assertNotFalse($jsContent, 'public/js/farmer-announcements.js を読み込めませんでした。');
        $this->assertStringContainsString('本日収穫しました', $jsContent);
        $this->assertStringContainsString('本日の販売分は売り切れました', $jsContent);
        $this->assertStringContainsString('臨時休業のお知らせ', $jsContent);
    }

    public function test_page_has_announcements_list_and_pager_elements(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/announcements');

        $response->assertOk();
        $response->assertSee('id="announcements-list"', false);
        $response->assertSee('id="announcements-loading"', false);
        $response->assertSee('id="announcements-empty"', false);
        $response->assertSee('id="announcements-pager"', false);
        $response->assertSee('id="announcements-prev-button"', false);
        $response->assertSee('id="announcements-next-button"', false);
        $response->assertSee('id="announcements-page-indicator"', false);
    }
}
