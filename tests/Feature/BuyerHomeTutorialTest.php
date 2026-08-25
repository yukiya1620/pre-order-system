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

    /**
     * 第3-C: 注文確認・注文完了が接続され、合計ステップ数が確定した。
     * 認証を経由する経路(guest)は11ステップ、最初からログイン済みの経路
     * (authenticated)は7ステップと、経路によって異なる値を使う設計に
     * なっていることを確認する。
     */
    public function test_buyer_home_tutorial_js_defines_total_steps_per_flow(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString('TOTAL_STEPS_GUEST = 11', $js);
        $this->assertStringContainsString('TOTAL_STEPS_AUTHENTICATED = 7', $js);
    }

    /**
     * 第3-C: ログイン済み利用者が「使い方を見る」から開始した場合は、
     * SMS認証を案内しない短縮フロー(authenticated)になることを、
     * ログイン判定(#buyer-home-logout-button)の有無で振り分けている
     * 設計として確認する。
     */
    public function test_buyer_home_tutorial_js_switches_flow_by_login_state_on_start(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString('function isBuyerLoggedIn()', $js);
        $this->assertStringContainsString("currentFlow = isBuyerLoggedIn() ? 'authenticated' : 'guest';", $js);
    }

    /**
     * 認証成功後にbuyer.homeへ戻ったときの専用案内(「もう一度、注文したい
     * 商品を選んでみましょう」)が、通常のステップ1(使い方を見るボタンで
     * 手動開始)とは別のsessionStorage予約(route==='buyer.home' かつ
     * reason==='post-auth')で区別されていることを確認する。
     */
    public function test_buyer_home_tutorial_js_defines_separate_post_auth_step(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString('ログインできました。もう一度、注文したい商品を選んでみましょう。', $js);
        $this->assertStringContainsString("progress.route !== 'buyer.home' || progress.reason !== 'post-auth'", $js);
        $this->assertStringContainsString('stepOffset: 6', $js);
        // reason/flowはroute同様、ステップ表示のたびに上書きされないよう、
        // 基盤のextraオプション経由で保存する(第3-B監査で見つかった
        // route上書きバグの再発防止と同じ仕組みを使う)。
        $this->assertStringContainsString("extra: { reason: 'post-auth', flow: 'guest' }", $js);
    }

    /**
     * 認証直後の専用案内は、sessionStorageの予約があるだけでなく、
     * 実際にログイン中であること(#buyer-home-logout-buttonの有無)も
     * 併せて確認する設計になっていることを確認する。認証に失敗して
     * このページへ来た場合に誤って専用案内が出ないようにするための安全策。
     */
    public function test_buyer_home_tutorial_js_checks_login_state_before_showing_post_auth_step(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString("document.getElementById('buyer-home-logout-button')", $js);
    }

    /**
     * bfcacheから復元された場合に備えて、pageshowイベントで
     * 認証直後案内の再開判定をやり直す設計になっていることを確認する。
     */
    public function test_buyer_home_tutorial_js_resumes_on_bfcache_restore(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('event.persisted', $js);
    }

    /**
     * 第3-C: 認証後専用案内(ステップ7)が表示されている間に商品カードが
     * クリックされた場合も、products.show側の「まとめステップ」で続けられる
     * よう、reason:'post-auth' を引き継いだ進行状況を保存することを確認する。
     * 第3-B時点ではこのクリックリスナーが無かった(orders.confirm以降は
     * 未接続だったため)。
     */
    public function test_buyer_home_tutorial_js_saves_progress_on_card_click_during_post_auth_step(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString('if (!postAuthActive) {', $js);
        $this->assertStringContainsString("route: 'products.show',\n            reason: 'post-auth',\n            flow: 'guest'", $js);
    }

    /**
     * buyer.home(通常のステップ1・post-auth案内とも)はisFinalPageを渡さない
     * (購入者チュートリアル全体の最終ページではないため)ことを確認する。
     * これにより、ローカル最終ステップ(1要素しかないためすぐ最後になる)で
     * 「完了」ボタンが誤って表示されない(第3-Cで発見・対応)。
     */
    public function test_buyer_home_tutorial_js_does_not_pass_is_final_page(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringNotContainsString('isFinalPage', $js);
    }
}
