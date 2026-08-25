<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 売上確認(farmer.sales)への販売者側操作チュートリアル接続(第5-B、
 * 販売者側チュートリアルの最終ステップ)を確認する。画面そのものの
 * 集計処理は既存のFarmerSalesPageTest・FarmerSalesApiTest等がカバーして
 * いるため、ここではチュートリアル関連の出し分け・data属性・接続コードの
 * 中身だけを確認する。
 */
class FarmerSalesTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertDontSee('js/farmer-sales-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('js/farmer-sales-tutorial.js', false);
    }

    public function test_target_data_attribute_is_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/sales');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="farmer-sales-summary"', false);
    }

    public function test_tutorial_js_defines_final_step_with_correct_offset(): void
    {
        $js = file_get_contents(public_path('js/farmer-sales-tutorial.js'));

        $this->assertStringContainsString("target: 'farmer-sales-summary'", $js);
        $this->assertStringContainsString("tutorialKey: 'farmer'", $js);
        $this->assertStringContainsString('TOTAL_STEPS = 9', $js);
        $this->assertStringContainsString('STEP_OFFSET = 8', $js);
    }

    /**
     * 販売者側チュートリアル全体で、isFinalPage: trueを渡すのは
     * この売上確認だけであることを確認する
     * (最終完了時だけfarmer_seenを保存する設計の裏付け)。
     */
    public function test_only_sales_tutorial_js_passes_is_final_page_true(): void
    {
        $salesJs = file_get_contents(public_path('js/farmer-sales-tutorial.js'));
        $homeJs = file_get_contents(public_path('js/farmer-home-tutorial.js'));
        $deliveryJs = file_get_contents(public_path('js/farmer-delivery-confirmations-tutorial.js'));
        $productsJs = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringContainsString('isFinalPage: true', $salesJs);
        $this->assertStringNotContainsString('isFinalPage', $homeJs);
        $this->assertStringNotContainsString('isFinalPage', $deliveryJs);
        $this->assertStringNotContainsString('isFinalPage', $productsJs);
    }

    public function test_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/farmer-sales-tutorial.js'));

        $this->assertStringContainsString("loadProgress('farmer')", $js);
        $this->assertStringContainsString("progress.route !== 'farmer.sales'", $js);
    }

    /**
     * 売上サマリーAPIの取得に失敗した場合、対象要素が見つからないまま
     * 最終ステップが誤って完了扱い(finish()呼び出し)にならないよう、
     * カスタムイベント(demo-tutorial:farmer-sales-rendered)の発火を
     * 待ってから開始する設計になっていることを確認する。
     */
    public function test_tutorial_js_waits_for_rendered_event_before_starting(): void
    {
        $js = file_get_contents(public_path('js/farmer-sales-tutorial.js'));

        $this->assertStringContainsString("addEventListener('demo-tutorial:farmer-sales-rendered'", $js);
        $this->assertStringContainsString('pendingResume', $js);
    }

    /**
     * 売上サマリー表示処理(farmer-sales.js)は、既存の集計取得ロジックを
     * 変えず、表示成功時のカスタムイベント発火だけが追加されていることを
     * 確認する。取得失敗時(catch節)にはこのイベントを発火しないことも
     * 合わせて確認する(動的DOM取得失敗時にチュートリアルが誤って
     * 完了扱いにならないための設計)。
     */
    public function test_sales_js_only_adds_event_on_success(): void
    {
        $js = file_get_contents(public_path('js/farmer-sales.js'));

        $eventPos = strpos($js, "new CustomEvent('demo-tutorial:farmer-sales-rendered')");
        $this->assertNotFalse($eventPos);

        $catchPos = strpos($js, '}).catch(function () {', $eventPos - 200);
        $this->assertNotFalse($catchPos);
        $this->assertLessThan($catchPos, $eventPos);

        // 既存の集計取得処理(summaryUrlへのGET)がそのまま残っていることを確認する。
        $this->assertStringContainsString('fetch(summaryUrl,', $js);
    }

    /**
     * チュートリアル接続コードが、売上に関するAPIを一切呼んでいないことを
     * 確認する(データを変更する操作はチュートリアル側から実行しない。
     * 売上確認自体は閲覧専用の画面だが、念のため確認する)。
     */
    public function test_tutorial_js_never_calls_apis_directly(): void
    {
        $js = file_get_contents(public_path('js/farmer-sales-tutorial.js'));

        $this->assertStringNotContainsString('fetch(', $js);
    }
}
