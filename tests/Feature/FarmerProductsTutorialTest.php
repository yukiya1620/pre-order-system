<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 商品管理(farmer.products)への販売者側操作チュートリアル接続(第5-B)を確認する。
 * 画面そのものの表示・編集導線は既存のFarmerProductsPageTest等がカバーして
 * いるため、ここではチュートリアル関連の出し分け・data属性・接続コードの
 * 中身だけを確認する。
 */
class FarmerProductsTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products');

        $response->assertOk();
        $response->assertDontSee('js/farmer-products-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products');

        $response->assertOk();
        $response->assertSee('js/farmer-products-tutorial.js', false);
    }

    public function test_back_link_target_attribute_is_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/farmer/products');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="farmer-back-link"', false);
    }

    public function test_tutorial_js_defines_two_steps_with_correct_offset(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringContainsString("target: 'farmer-product-card'", $js);
        $this->assertStringContainsString("target: 'farmer-back-link'", $js);
        $this->assertStringContainsString("tutorialKey: 'farmer'", $js);
        $this->assertStringContainsString('TOTAL_STEPS = 9', $js);
        $this->assertStringContainsString('STEP_OFFSET = 5', $js);
    }

    public function test_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringContainsString("loadProgress('farmer')", $js);
        $this->assertStringContainsString("progress.route !== 'farmer.products'", $js);
    }

    public function test_tutorial_js_waits_for_rendered_event_not_fixed_timeout(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringContainsString("addEventListener('demo-tutorial:farmer-products-rendered'", $js);
        $this->assertStringNotContainsString('window.setTimeout', $js);
    }

    /**
     * 「もどる」リンクが実際にクリックされたら、遷移前にclose()でオーバーレイを
     * 片付け、農家ホーム側で3回目の専用案内(after-products)を
     * 再開できるよう進行状況を保存することを確認する。
     */
    public function test_tutorial_js_closes_and_saves_progress_on_back_link_click(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringContainsString('window.DemoTutorial.close()', $js);
        $this->assertStringContainsString("reason: 'after-products'", $js);
    }

    /**
     * 商品管理の表示処理(farmer-products.js)は、既存の絞り込み・カード生成
     * ロジックを変えず、カード描画完了時のカスタムイベント発火と、
     * 最初のカードへのdata属性付与だけが追加されていることを確認する。
     */
    public function test_products_js_only_adds_event_and_data_attribute(): void
    {
        $js = file_get_contents(public_path('js/farmer-products.js'));

        $this->assertStringContainsString("new CustomEvent('demo-tutorial:farmer-products-rendered')", $js);
        $this->assertStringContainsString("card.dataset.demoTutorial = 'farmer-product-card'", $js);

        // 既存の絞り込みロジックがそのまま残っていることを確認する。
        $this->assertStringContainsString('filterSelect.addEventListener', $js);
    }

    /**
     * チュートリアル接続コードが、商品編集・再販売APIを一切呼んでいないことを
     * 確認する(データを変更する操作はチュートリアル側から実行しない)。
     */
    public function test_tutorial_js_never_calls_product_mutation_apis(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringNotContainsString('fetch(', $js);
    }

    /**
     * 「商品を編集」「去年の商品から再販売」リンクへ、チュートリアル側が
     * 実際にクリックする処理を追加していないことを確認する(遷移先で保存すると
     * 実データが変わるため)。
     */
    public function test_tutorial_js_does_not_click_edit_or_resell_links(): void
    {
        $js = file_get_contents(public_path('js/farmer-products-tutorial.js'));

        $this->assertStringNotContainsString('edit-link', $js);
        $this->assertStringNotContainsString('.click()', $js);
    }
}
