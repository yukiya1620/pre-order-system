<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 農家ホーム(farmer.home)への販売者側操作チュートリアル接続(第5-B)を確認する。
 * 農家ホームそのものの表示・概要取得ロジックは既存のFarmerHomePageTestが
 * カバーしているため、ここではチュートリアル関連の出し分け・data属性・
 * 接続コードの中身だけを確認する。
 */
class FarmerHomeTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_start_button_is_not_shown_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertDontSee('id="farmer-home-tutorial-start-button"', false);
        $response->assertDontSee('js/farmer-home-tutorial.js', false);
    }

    public function test_tutorial_start_button_is_shown_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('id="farmer-home-tutorial-start-button"', false);
        $response->assertSee('使い方を見る');
        $response->assertSee('js/farmer-home-tutorial.js', false);
    }

    /**
     * data属性は購入者側と同じ方針どおり、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_target_data_attributes_are_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="farmer-overview"', false);
        $response->assertSee('data-demo-tutorial="farmer-menu-delivery-confirmations"', false);
        $response->assertSee('data-demo-tutorial="farmer-menu-products"', false);
        $response->assertSee('data-demo-tutorial="farmer-menu-sales"', false);
    }

    /**
     * 「予約一覧」メニューは今回のチュートリアル経路に含まれないため、
     * data-demo-tutorial属性を追加していないことを確認する
     * (監査どおり、farmer-orders.jsは原則変更不要という方針の裏付け)。
     */
    public function test_orders_menu_item_has_no_tutorial_target_attribute(): void
    {
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer');
        $html = $response->getContent();

        $this->assertStringContainsString('href="'.route('farmer.orders').'"', $html);
        // 「予約一覧」リンクにfarmer-menu-ordersのような専用属性が
        // 付いていないことを確認する(今回のチュートリアル経路に含まれないため)。
        $this->assertStringNotContainsString('data-demo-tutorial="farmer-menu-orders"', $html);
    }

    /**
     * ステップ定義(tutorialKey: 'farmer')・9ステップ固定・3つのphase
     * (normal / after-delivery-confirmations / after-products)が
     * JS内で完結していることを、既存のF9パターンで確認する。
     */
    public function test_farmer_home_tutorial_js_uses_farmer_tutorial_key_and_fixed_total_steps(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $this->assertStringContainsString("tutorialKey: 'farmer'", $js);
        $this->assertStringContainsString('TOTAL_STEPS = 9', $js);
        $this->assertStringContainsString("target: 'farmer-overview'", $js);
        $this->assertStringContainsString("target: 'farmer-menu-delivery-confirmations'", $js);
        $this->assertStringContainsString("target: 'farmer-menu-products'", $js);
        $this->assertStringContainsString("target: 'farmer-menu-sales'", $js);
    }

    /**
     * 2回目・3回目の専用案内(after-delivery-confirmations / after-products)は、
     * 購入者側のpost-auth案内と同じくreasonフィールドで区別され、
     * sessionStorageの進行状況が一致する場合だけ自動的に再開されることを確認する。
     */
    public function test_farmer_home_tutorial_js_resumes_revisit_phases_by_reason(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $this->assertStringContainsString("progress.reason === 'after-delivery-confirmations'", $js);
        $this->assertStringContainsString("progress.reason === 'after-products'", $js);
        $this->assertStringContainsString("extra = { reason: 'after-delivery-confirmations' }", $js);
        $this->assertStringContainsString("extra = { reason: 'after-products' }", $js);
    }

    /**
     * 「使い方を見る」ボタンは、auto_shown・seenの状態を一切確認せず、
     * 常に通常フェーズ(1回目)から開始することを確認する。
     */
    public function test_start_button_always_begins_normal_phase_without_checking_seen_or_auto_shown(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $clickHandlerStart = strpos($js, "startButton.addEventListener('click'");
        $clickHandlerEnd = strpos($js, '});', $clickHandlerStart);
        $clickHandlerBody = substr($js, $clickHandlerStart, $clickHandlerEnd - $clickHandlerStart);

        $this->assertStringContainsString("beginTutorial('normal')", $clickHandlerBody);
        $this->assertStringNotContainsString('hasSeenTutorial', $clickHandlerBody);
        $this->assertStringNotContainsString('hasAutoShownTutorial', $clickHandlerBody);
    }

    /**
     * 第4段階(購入者側)と同じ考え方の初回自動開始。auto_shownは
     * markAutoShownTutorial('farmer')で保存され、既にauto_shown済みなら
     * 自動開始しないことを確認する。
     */
    public function test_farmer_home_tutorial_js_auto_shows_once_using_farmer_key(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $this->assertStringContainsString("hasAutoShownTutorial('farmer')", $js);
        $this->assertStringContainsString("markAutoShownTutorial('farmer')", $js);
    }

    /**
     * メニューリンクの実クリックを検知して次ページ用の進行状況を保存する際、
     * リンクの遷移そのもの(preventDefault等)は妨げないことを確認する。
     */
    public function test_farmer_home_tutorial_js_does_not_prevent_default_navigation(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $this->assertStringNotContainsString('.preventDefault(', $js);
    }

    public function test_farmer_home_tutorial_js_resumes_on_bfcache_restore(): void
    {
        $js = file_get_contents(public_path('js/farmer-home-tutorial.js'));

        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('event.persisted', $js);
    }
}
