<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ログイン画面(login)への操作チュートリアル接続(第3-B)を確認する。
 * ログイン画面そのものの認証処理は既存のAuthFormPageTest・SmsAuthApiTest等が
 * カバーしているため、ここではチュートリアル関連の出し分け・data属性・
 * デモ電話番号案内・接続コードの中身だけを確認する。
 */
class AuthFormTutorialTest extends TestCase
{
    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('js/auth-form-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('js/auth-form-tutorial.js', false);
    }

    /**
     * data属性は前回確定した方針どおり、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_target_data_attributes_are_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-login-phone"', false);
        $response->assertSee('data-demo-tutorial="buyer-login-code"', false);
        // 第3-C: 認証コード入力欄だけでなく「認証する」ボタンまで含む要素にも
        // 専用のdata属性が付いていることを確認する(次へボタンを表示しない
        // 設計にしたため、認証するボタンがスポットライト内で操作できる必要がある)。
        $response->assertSee('data-demo-tutorial="buyer-login-code-actions"', false);
    }

    /**
     * register画面にも同じdata属性が出る(共有Blade/JSのため)が、
     * auth-form-tutorial.js自体はmode==='login'のときだけ動作する
     * (JS内のガード条件で確認する。data属性そのものは常時出力でよい)。
     */
    public function test_register_page_also_has_data_attributes_but_tutorial_js_only_activates_for_login_mode(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-login-phone"', false);
        $response->assertSee('js/auth-form-tutorial.js', false);

        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));
        $this->assertStringContainsString("container.dataset.mode !== 'login'", $js);
    }

    /**
     * デモ用電話番号の案内は、DEMO_MODE=trueかつconfig('demo.tutorial_buyer_phone')
     * が設定されている場合だけ出力され、値そのものは常に config 経由で取得する
     * (Blade・JSへのハードコードがないことも合わせて確認する)。
     */
    public function test_demo_tutorial_buyer_phone_is_shown_only_when_configured_and_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true, 'demo.tutorial_buyer_phone' => '00000000001']);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial-buyer-phone="00000000001"', false);
    }

    public function test_demo_tutorial_buyer_phone_is_not_shown_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false, 'demo.tutorial_buyer_phone' => '00000000001']);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('data-demo-tutorial-buyer-phone', false);
    }

    public function test_demo_tutorial_buyer_phone_is_not_shown_when_not_configured(): void
    {
        config(['demo.enabled' => true, 'demo.tutorial_buyer_phone' => null]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('data-demo-tutorial-buyer-phone', false);
    }

    /**
     * ステップ定義・ページ横断の設計は、JS内で完結しているため、
     * 既存のF9パターン(file_get_contents + assertStringContainsString)で確認する。
     */
    public function test_auth_form_tutorial_js_defines_two_steps_with_correct_offset(): void
    {
        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-login-phone'", $js);
        // 第3-C: 認証コードステップの対象は、認証するボタンまで含む
        // buyer-login-code-actionsに変更された(次へボタンを表示しない設計にしたため)。
        $this->assertStringContainsString("target: 'buyer-login-code-actions'", $js);
        // 第3-Cにより、ログイン画面を経由するのは常に未ログイン(guest)経路
        // のみであることが確定したため、合計ステップ数は固定の11になった。
        $this->assertStringContainsString('TOTAL_STEPS = 11', $js);
        $this->assertStringContainsString('STEP_OFFSET = 4', $js);
    }

    /**
     * ログイン画面はisFinalPageを渡していない(購入者チュートリアル全体の
     * 最終ページではないため)ことを確認する。
     */
    public function test_auth_form_tutorial_js_does_not_pass_is_final_page(): void
    {
        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));

        $this->assertStringNotContainsString('isFinalPage', $js);
    }

    /**
     * 直接アクセス(sessionStorageに進行状態が無い、または他ページ向けの進行状態)
     * では自動開始しないことを、実装のガード条件として確認する。
     */
    public function test_auth_form_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));

        $this->assertStringContainsString("loadProgress('buyer')", $js);
        $this->assertStringContainsString("progress.route !== 'login'", $js);
    }

    /**
     * 認証処理(auth-form.js・SmsAuthController等)は一切変更していないことを
     * 前提に、実際の画面のステップ切り替え(hidden属性)をMutationObserverで
     * 外部から観測し、チュートリアルを自動的に追従させる設計になっていることを確認する。
     */
    public function test_auth_form_tutorial_js_follows_screen_step_changes_without_modifying_auth_form_js(): void
    {
        $tutorialJs = file_get_contents(public_path('js/auth-form-tutorial.js'));
        $authFormJs = file_get_contents(public_path('js/auth-form.js'));

        $this->assertStringContainsString('MutationObserver', $tutorialJs);
        $this->assertStringContainsString("dataset.stepName === 'code'", $tutorialJs);
        $this->assertStringContainsString('currentStepIndex()', $tutorialJs);

        // auth-form.js側にチュートリアル関連のコードが混ざっていないことも確認する。
        $this->assertStringNotContainsString('DemoTutorial', $authFormJs);
        $this->assertStringNotContainsString('MutationObserver', $authFormJs);
    }

    /**
     * 「認証する」ボタンが押された時点で、buyer.home側の専用案内
     * (認証直後の再開案内)を予約することを確認する。認証成功後の
     * リダイレクト先(redirectAfterAuth)自体には触れていないことも確認する。
     */
    public function test_auth_form_tutorial_js_reserves_post_auth_progress_on_verify_click(): void
    {
        $tutorialJs = file_get_contents(public_path('js/auth-form-tutorial.js'));
        $authFormJs = file_get_contents(public_path('js/auth-form.js'));

        $this->assertStringContainsString('auth-verify-button', $tutorialJs);
        $this->assertStringContainsString("saveProgress('buyer', { stepIndex: 0, route: 'buyer.home', reason: 'post-auth', flow: 'guest' })", $tutorialJs);

        // 認証成功後のリダイレクト処理(redirectAfterAuth)は変更していないことを確認する。
        $this->assertStringContainsString('function redirectAfterAuth(user)', $authFormJs);
        $this->assertStringContainsString('window.location.href = (user.role === \'farmer\') ? farmerHomeUrl : buyerHomeUrl;', $authFormJs);
    }

    /**
     * bfcache(ブラウザの戻る操作でページを再読み込みせず復元する仕組み)から
     * 復元された場合に備えて、pageshowイベントで再開判定をやり直す設計に
     * なっていることを確認する。
     */
    public function test_auth_form_tutorial_js_resumes_on_bfcache_restore(): void
    {
        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));

        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('event.persisted', $js);
    }

    /**
     * 第5-B: 販売者デモを試す方向けの案内(farmer-demo-fill-button)は、
     * DEMO_MODE=trueかつ販売者デモ用のメール・パスワードが両方設定されている
     * 場合だけ、ログイン画面(mode==='login')に表示されることを確認する。
     */
    public function test_farmer_demo_hint_is_shown_when_demo_mode_enabled_and_credentials_configured(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_farmer_email' => 'demo-farmer@example.com',
            'demo.tutorial_farmer_password' => 'password',
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('id="farmer-demo-fill-button"', false);
        $response->assertSee('data-demo-tutorial-farmer-email="demo-farmer@example.com"', false);
        $response->assertSee('data-demo-tutorial-farmer-password="password"', false);
    }

    public function test_farmer_demo_hint_is_not_shown_when_demo_mode_disabled(): void
    {
        config([
            'demo.enabled' => false,
            'demo.tutorial_farmer_email' => 'demo-farmer@example.com',
            'demo.tutorial_farmer_password' => 'password',
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('farmer-demo-fill-button', false);
        $response->assertDontSee('demo-farmer@example.com', false);
        $response->assertDontSee('data-demo-tutorial-farmer-password', false);
    }

    /**
     * メール・パスワードのどちらか一方でも未設定なら、公開デモ資格情報を
     * 画面へ一切出力しないことを確認する(中途半端な案内を出さないため)。
     */
    public function test_farmer_demo_hint_is_not_shown_when_email_is_missing(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_farmer_email' => null,
            'demo.tutorial_farmer_password' => 'password',
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('farmer-demo-fill-button', false);
    }

    public function test_farmer_demo_hint_is_not_shown_when_password_is_missing(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_farmer_email' => 'demo-farmer@example.com',
            'demo.tutorial_farmer_password' => null,
        ]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('farmer-demo-fill-button', false);
    }

    /**
     * 会員登録画面(register)には、電話番号→メールへの切り替え導線自体が
     * 存在しないため、販売者デモ案内も表示しないことを確認する。
     */
    public function test_farmer_demo_hint_is_not_shown_on_register_page(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_farmer_email' => 'demo-farmer@example.com',
            'demo.tutorial_farmer_password' => 'password',
        ]);

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertDontSee('farmer-demo-fill-button', false);
    }

    /**
     * 案内ボタンは、既存の「メールアドレス+パスワードでログインする」への
     * 切り替えボタンを実際にクリックし、入力欄へ値をセットするだけで、
     * ログインAPIを一切呼ばないことを確認する(ワンクリック認証バイパスを
     * 行わない設計であることの裏付け)。
     */
    public function test_farmer_demo_fill_button_only_switches_and_fills_without_calling_login_api(): void
    {
        $js = file_get_contents(public_path('js/auth-form-tutorial.js'));

        $farmerSectionStart = strpos($js, "farmer-demo-fill-button');");
        $this->assertNotFalse($farmerSectionStart);
        $farmerSectionBody = substr($js, $farmerSectionStart);

        $this->assertStringContainsString('switchButton.click()', $farmerSectionBody);
        $this->assertStringContainsString("emailInput.value = email", $farmerSectionBody);
        $this->assertStringContainsString("passwordInput.value = password", $farmerSectionBody);
        $this->assertStringNotContainsString('fetch(', $farmerSectionBody);
        $this->assertStringNotContainsString('email-login-submit-button\').click()', $farmerSectionBody);
    }
}
