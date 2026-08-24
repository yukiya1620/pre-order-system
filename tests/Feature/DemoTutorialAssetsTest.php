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
}
