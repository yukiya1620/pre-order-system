<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 購入者トップ(商品一覧)への操作チュートリアル接続(第2段階)を確認する。
 * 商品一覧そのものの取得・表示・遷移ロジックは既存のBuyerHomePageTestが
 * カバーしているため、ここではチュートリアル関連の出し分けだけを確認する。
 */
class BuyerHomeTutorialTest extends TestCase
{
    public function test_tutorial_start_button_is_not_shown_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="buyer-home-tutorial-start-button"', false);
        $response->assertDontSee('js/buyer-home-tutorial.js', false);
    }

    public function test_tutorial_start_button_is_shown_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="buyer-home-tutorial-start-button"', false);
        $response->assertSee('使い方を見る');
        $response->assertSee('js/buyer-home-tutorial.js', false);
    }

    /**
     * 商品カードの生成はJS内(buyer-home.js)で行われるため、既存の
     * F9パターン(file_get_contents + assertStringContainsString)で、
     * 最初のカードに専用data属性が付くこと・描画完了イベントを
     * 発火することを確認する。DOM生成ロジックそのもの(商品取得・表示・
     * リンク遷移)は変更していないため、既存のBuyerHomePageTestが
     * 引き続きすべて通ることも別途確認する。
     */
    public function test_buyer_home_js_marks_first_product_card_for_tutorial_and_dispatches_ready_event(): void
    {
        $js = file_get_contents(public_path('js/buyer-home.js'));

        $this->assertStringContainsString("card.dataset.demoTutorial = 'buyer-product-card';", $js);
        $this->assertStringContainsString('index === 0', $js);
        $this->assertStringContainsString("new CustomEvent('demo-tutorial:buyer-products-rendered')", $js);
    }

    /**
     * buyer-home-tutorial.js自体は、DEMO_MODE=falseでは読み込まれない
     * (test_tutorial_start_button_is_not_shown_when_demo_mode_disabledで確認済み)。
     * ここではファイルの中身が、共通基盤(demo-tutorial.js)の想定どおり
     * data-demo-tutorial="buyer-product-card"を対象にしたステップを
     * 定義していることを確認する。
     */
    public function test_buyer_home_tutorial_js_defines_single_product_card_step(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-product-card'", $js);
        $this->assertStringContainsString("tutorialKey: 'buyer'", $js);
        $this->assertStringContainsString('demo-tutorial:buyer-products-rendered', $js);
    }
}
