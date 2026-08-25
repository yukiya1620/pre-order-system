<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 配達のかくにん(farmer.delivery-confirmations)への販売者側操作チュートリアル
 * 接続(第5-B)を確認する。画面そのものの表示・回答処理は既存の
 * FarmerDeliveryConfirmationsPageTest等がカバーしているため、ここでは
 * チュートリアル関連の出し分け・data属性・接続コードの中身だけを確認する。
 */
class FarmerDeliveryConfirmationsTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertDontSee('js/farmer-delivery-confirmations-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertSee('js/farmer-delivery-confirmations-tutorial.js', false);
    }

    /**
     * 「もどる」リンクのdata属性は、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_back_link_target_attribute_is_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/delivery-confirmations');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="farmer-back-link"', false);
    }

    /**
     * ステップ定義・経路上の開始位置は、JS内で完結しているため、
     * 既存のF9パターンで確認する。
     */
    public function test_tutorial_js_defines_two_steps_with_correct_offset(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));

        $this->assertStringContainsString("target: 'farmer-delivery-confirmation-card'", $js);
        $this->assertStringContainsString("target: 'farmer-back-link'", $js);
        $this->assertStringContainsString("tutorialKey: 'farmer'", $js);
        $this->assertStringContainsString('TOTAL_STEPS = 9', $js);
        $this->assertStringContainsString('STEP_OFFSET = 2', $js);
    }

    /**
     * 直接アクセス(sessionStorageに進行状態が無い、または他ページ向けの進行状態)
     * では自動開始しないことを確認する。
     */
    public function test_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));

        $this->assertStringContainsString("loadProgress('farmer')", $js);
        $this->assertStringContainsString("progress.route !== 'farmer.delivery-confirmations'", $js);
    }

    /**
     * DOM準備完了はカスタムイベント(demo-tutorial:farmer-delivery-confirmations-rendered)を
     * 待つ設計になっており、固定時間のsetTimeoutには依存しないことを確認する。
     */
    public function test_tutorial_js_waits_for_rendered_event_not_fixed_timeout(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));

        $this->assertStringContainsString("addEventListener('demo-tutorial:farmer-delivery-confirmations-rendered'", $js);
        $this->assertStringNotContainsString('window.setTimeout', $js);
    }

    /**
     * 「もどる」リンクが実際にクリックされたら、遷移前にclose()でオーバーレイを
     * 片付け、農家ホーム側で3回目の専用案内(after-delivery-confirmations)を
     * 再開できるよう進行状況を保存することを確認する。
     */
    public function test_tutorial_js_closes_and_saves_progress_on_back_link_click(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));

        $this->assertStringContainsString('window.DemoTutorial.close()', $js);
        $this->assertStringContainsString("reason: 'after-delivery-confirmations'", $js);
    }

    /**
     * 配達のかくにんの表示処理(farmer-delivery-confirmations.js)は、
     * 既存の回答処理(submitResponse・API呼び出し)を変えず、
     * カード描画完了時のカスタムイベント発火と、最初のカードへの
     * data属性付与だけが追加されていることを確認する。
     */
    public function test_delivery_confirmations_js_only_adds_event_and_data_attribute(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations.js'));

        $this->assertStringContainsString("new CustomEvent('demo-tutorial:farmer-delivery-confirmations-rendered')", $js);
        $this->assertStringContainsString("card.dataset.demoTutorial = 'farmer-delivery-confirmation-card'", $js);

        // 既存の回答送信処理(respond API呼び出し)がそのまま残っていることを確認する。
        $this->assertStringContainsString("listUrl + '/' + id + '/respond'", $js);
    }

    /**
     * チュートリアル接続コードが、回答API(respond)を一切呼んでいないことを
     * 確認する(データを変更する操作はチュートリアル側から実行しない)。
     */
    public function test_tutorial_js_never_calls_respond_api(): void
    {
        $js = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));

        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('/respond', $js);
    }
}
