<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 一般公開デモ専用の操作チュートリアル(第1段階: 共通基盤)が、
 * DEMO_MODE の設定に応じて正しく出し分けられることを確認する。
 *
 * ここではまだ実際の画面(商品一覧など)へチュートリアルを接続していないため、
 * 「共通レイアウトが、DEMO_MODEの値に応じてCSS/JSの読み込みタグを
 * 正しく出し分けているか」だけを確認する。
 */
class DemoTutorialAssetsTest extends TestCase
{
    public function test_demo_tutorial_assets_are_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('css/demo-tutorial.css', false);
        $response->assertDontSee('js/demo-tutorial.js', false);
    }

    public function test_demo_tutorial_assets_are_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('css/demo-tutorial.css', false);
        $response->assertSee('js/demo-tutorial.js', false);
    }

    /**
     * 購入者トップ以外の画面(共通layoutを使う別画面の例としてログイン画面)でも
     * 同じ出し分けが効くことを確認する。共通レイアウト側で制御しているため、
     * 画面ごとに個別対応が要らないことの裏付けにもなる。
     */
    public function test_demo_tutorial_assets_are_not_loaded_on_other_pages_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('css/demo-tutorial.css', false);
        $response->assertDontSee('js/demo-tutorial.js', false);
    }

    public function test_demo_tutorial_assets_are_loaded_on_other_pages_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('css/demo-tutorial.css', false);
        $response->assertSee('js/demo-tutorial.js', false);
    }

    /**
     * DEMO_MODE=falseの場合、チュートリアル用ファイル自体が存在していても
     * (削除まではしない設計のため)、HTMLからは参照されないことを重ねて確認する。
     */
    public function test_demo_tutorial_files_exist_on_disk_regardless_of_demo_mode(): void
    {
        $this->assertFileExists(public_path('css/demo-tutorial.css'));
        $this->assertFileExists(public_path('js/demo-tutorial.js'));
    }

    /**
     * 第3-B: bfcache(ブラウザの戻る操作でページを再読み込みせず復元する仕組み)
     * から復元された場合、開いたままのオーバーレイ・スポットライト・吹き出しを
     * そのまま表示し続けず、いったん片付ける設計になっていることを確認する。
     */
    public function test_demo_tutorial_js_resets_ui_on_bfcache_restore(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString("addEventListener('pageshow'", $js);
        $this->assertStringContainsString('event.persisted', $js);
        $this->assertStringContainsString('teardown()', $js);
    }

    /**
     * 総ステップ数がまだ確定していないことを示す '?' 表示を受け付ける
     * (数値をtotalStepsへ決め打ちしなくてよい)設計になっていることを確認する。
     */
    public function test_demo_tutorial_js_accepts_undetermined_total_steps(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString("displayTotal = state.totalSteps || localTotal", $js);
    }

    /**
     * 第3-B監査中に発見した不具合の再発防止テスト。
     *
     * 以前は、ステップが切り替わるたびに実行するsaveProgressが
     * `route: step.route || null` を見ていたため、各ステップのstep定義に
     * routeを設定していない場合、1ステップでも表示された時点で
     * sessionStorageのrouteがnullに上書きされてしまい、ブラウザの
     * 「戻る→進む」で元のページに戻ってきても再開できなくなっていた。
     *
     * 修正後は、start()呼び出し時にoptions.routeとして渡された値
     * (state.route)をツアー全体で保持し、ステップが変わってもroute自体は
     * 上書きされない設計になっている。
     */
    public function test_demo_tutorial_js_preserves_route_across_step_changes(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString('state.route = options.route || null;', $js);
        $this->assertStringContainsString('route: state.route', $js);
        // 修正前のバグの原因だった書き方が残っていないことも確認する。
        $this->assertStringNotContainsString('route: step.route', $js);
    }

    /**
     * routeだけでは表現しきれない追加の目印(reason等)も、同様にステップが
     * 変わっても保持され続けることを確認する(認証成功後のbuyer.home専用案内で使用)。
     */
    public function test_demo_tutorial_js_preserves_extra_data_across_step_changes(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString('state.extra = options.extra || null;', $js);
        $this->assertStringContainsString('progressToSave[extraKey] = state.extra[extraKey];', $js);
    }

    /**
     * 第4段階: 「最後まで完了したか」(seen)とは別のキーで、「このブラウザで
     * 初回自動表示を一度でも実行したか」(auto_shown)を管理する関数
     * (hasAutoShownTutorial/markAutoShownTutorial)が、公開API
     * (window.DemoTutorial)に含まれていることを確認する。
     */
    public function test_demo_tutorial_js_exposes_auto_shown_functions(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString('function hasAutoShownTutorial(tutorialKey)', $js);
        $this->assertStringContainsString('function markAutoShownTutorial(tutorialKey)', $js);
        $this->assertStringContainsString('hasAutoShownTutorial: hasAutoShownTutorial,', $js);
        $this->assertStringContainsString('markAutoShownTutorial: markAutoShownTutorial,', $js);
    }

    /**
     * auto_shownはseenとは別のlocalStorageキー(接尾辞が異なる)で管理される
     * ことを確認する。同じキーを共有してしまうと、「完了していないのに
     * 自動表示だけ済んだ」状態を区別できなくなってしまう。
     */
    public function test_auto_shown_key_is_distinct_from_seen_key(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString("SEEN_KEY_SUFFIX = '_seen_v1'", $js);
        $this->assertStringContainsString("AUTO_SHOWN_KEY_SUFFIX = '_auto_shown_v1'", $js);
    }

    /**
     * hasAutoShownTutorial/markAutoShownTutorialも、既存のhasSeenTutorial等と
     * 同様にsafeGetItem/safeSetItem経由でlocalStorageへアクセスすることを
     * 確認する。これにより、プライベートブラウジング等でlocalStorageが
     * 使えない環境でも例外を投げず、通常画面を壊さない
     * (常にfalse相当を返し、手動操作は引き続き行える)。
     */
    public function test_auto_shown_functions_use_safe_storage_access(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $hasFnStart = strpos($js, 'function hasAutoShownTutorial(tutorialKey)');
        $markFnStart = strpos($js, 'function markAutoShownTutorial(tutorialKey)');
        $markFnEnd = strpos($js, '}', $markFnStart);
        $body = substr($js, $hasFnStart, $markFnEnd - $hasFnStart);

        $this->assertStringContainsString('safeGetItem(window.localStorage,', $body);
        $this->assertStringContainsString('safeSetItem(window.localStorage,', $body);
    }

    /**
     * 第5-B: 購入者(buyer)・販売者(farmer)のストレージキーが、
     * tutorialKey引数だけで完全に分離されることを確認する。
     * SEEN_KEY_PREFIX + tutorialKey + SEEN_KEY_SUFFIX という組み立て方が
     * 共通基盤側にあるため、呼び出し側がtutorialKeyに'buyer'/'farmer'を
     * 渡すだけで、kazeyui_demo_tutorial_buyer_seen_v1 /
     * kazeyui_demo_tutorial_farmer_seen_v1 のように自動的に分かれる
     * (共通基盤側にbuyer/farmer専用のコードは一切無い)。
     */
    public function test_seen_and_progress_keys_are_built_from_tutorial_key_argument(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString(
            "safeGetItem(window.localStorage, SEEN_KEY_PREFIX + tutorialKey + SEEN_KEY_SUFFIX) === '1';",
            $js
        );
        $this->assertStringContainsString(
            "safeSetItem(window.sessionStorage, PROGRESS_KEY_PREFIX + tutorialKey + PROGRESS_KEY_SUFFIX, JSON.stringify(progress));",
            $js
        );
        // tutorialKeyの値によって処理を分岐するコード(=buyer/farmer専用の
        // 特別扱い)が基盤側に無いことを確認する(tutorialKeyを渡すのは
        // 呼び出し側の責務であることの裏付け)。コメント中の例示
        // (「'buyer'や'farmer'など」)とは区別するため、条件分岐の構文で検索する。
        $this->assertStringNotContainsString("tutorialKey === 'buyer'", $js);
        $this->assertStringNotContainsString("tutorialKey === 'farmer'", $js);
        $this->assertStringNotContainsString("tutorialKey == 'buyer'", $js);
        $this->assertStringNotContainsString("tutorialKey == 'farmer'", $js);
    }

    /**
     * 販売者側の接続コード群(farmer-*-tutorial.js)がすべて
     * tutorialKey: 'farmer' を使い、購入者側('buyer')とは異なる値を
     * 渡していることを確認する。
     */
    public function test_farmer_tutorial_files_use_farmer_tutorial_key(): void
    {
        $files = [
            'farmer-home-tutorial.js',
            'farmer-delivery-confirmations-tutorial.js',
            'farmer-products-tutorial.js',
            'farmer-sales-tutorial.js',
        ];

        foreach ($files as $file) {
            $js = file_get_contents(public_path('js/'.$file));
            $this->assertStringContainsString("tutorialKey: 'farmer'", $js, $file.' should use tutorialKey: farmer');
            $this->assertStringNotContainsString("tutorialKey: 'buyer'", $js, $file.' should not reference buyer tutorialKey');
        }
    }
}
