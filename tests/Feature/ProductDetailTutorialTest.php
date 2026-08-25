<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 商品詳細(products.show)への操作チュートリアル接続(第3-A)を確認する。
 * 商品表示・注文処理そのものは既存のProductDetailPageTestがカバーしているため、
 * ここではチュートリアル関連の出し分け・data属性・接続コードの中身だけを確認する。
 */
class ProductDetailTutorialTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(): ProductSale
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);

        return ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
            'is_reservation_open' => true,
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
        ]);
    }

    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertDontSee('js/product-detail-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('js/product-detail-tutorial.js', false);
    }

    /**
     * data属性は前回確定した方針どおり、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_target_data_attributes_are_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-delivery-date"', false);
        $response->assertSee('data-demo-tutorial="buyer-quantity"', false);
    }

    public function test_login_link_has_order_button_target_attribute_for_guest(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('href="'.route('login').'" data-demo-tutorial="buyer-order-button"', false);
    }

    public function test_order_button_has_order_button_target_attribute_for_buyer(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-order-button"', false);
        // ログイン中は注文ボタン側にだけ付き、ログインリンク自体は出力されない。
        $response->assertDontSee('href="'.route('login').'"', false);
    }

    /**
     * ステップ定義・ページ横断の設計(第3-A)は、JS内で完結しているため、
     * 既存のF9パターン(file_get_contents + assertStringContainsString)で確認する。
     */
    public function test_product_detail_tutorial_js_defines_three_steps_with_correct_offsets(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-delivery-date'", $js);
        $this->assertStringContainsString("target: 'buyer-quantity'", $js);
        $this->assertStringContainsString("target: 'buyer-order-button'", $js);
        // 第3-Cで注文確認・注文完了が接続され、合計ステップ数が確定した
        // (未ログイン経由11ステップ・ログイン済み7ステップ)。
        $this->assertStringContainsString('TOTAL_STEPS_GUEST = 11', $js);
        $this->assertStringContainsString('TOTAL_STEPS_AUTHENTICATED = 7', $js);
        $this->assertStringContainsString('STEP_OFFSET_NORMAL = 1', $js);
    }

    /**
     * 第3-C: 認証を経由してこの画面へ再訪した場合(reason:'post-auth')は、
     * 配達日・数量・注文ボタンを個別に3回説明し直さず、1つのまとめステップ
     * (buyer-order-actions)にまとめることを確認する。
     */
    public function test_product_detail_tutorial_js_defines_condensed_step_for_post_auth_revisit(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-order-actions'", $js);
        $this->assertStringContainsString("progress.reason === 'post-auth'", $js);
        $this->assertStringContainsString('STEP_OFFSET_CONDENSED = 7', $js);
    }

    /**
     * まとめステップの対象(buyer-order-actions)が、配達日欄・数量欄・
     * 注文ボタンをすべて内側に含む要素であることを確認する。これにより、
     * スポットライトの範囲外がオーバーレイに覆われても、配達日・数量・
     * 注文ボタンは実際に操作可能な状態のまま保たれる。
     */
    public function test_order_actions_wrapper_contains_delivery_date_quantity_and_order_button(): void
    {
        config(['demo.enabled' => true]);
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);
        $html = $response->getContent();

        $wrapperStart = strpos($html, 'data-demo-tutorial="buyer-order-actions"');
        $deliveryDatePos = strpos($html, 'data-demo-tutorial="buyer-delivery-date"');
        $quantityPos = strpos($html, 'data-demo-tutorial="buyer-quantity"');
        $orderButtonPos = strpos($html, 'data-demo-tutorial="buyer-order-button"');

        $this->assertNotFalse($wrapperStart);
        $this->assertGreaterThan($wrapperStart, $deliveryDatePos);
        $this->assertGreaterThan($wrapperStart, $quantityPos);
        $this->assertGreaterThan($wrapperStart, $orderButtonPos);
    }

    /**
     * ログイン済みで注文ボタン(<button>)を押した場合、orders.confirm側で
     * チュートリアルを再開できるよう進行状況を保存することを確認する
     * (第3-B時点ではログインリンクの場合しか保存していなかった)。
     */
    public function test_product_detail_tutorial_js_saves_orders_confirm_progress_for_logged_in_order_button(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringContainsString("route: 'orders.confirm', flow: currentFlow", $js);
    }

    /**
     * 商品詳細(通常3ステップ・まとめ1ステップとも)はisFinalPageを渡さない
     * (購入者チュートリアル全体の最終ページではないため)ことを確認する。
     */
    public function test_product_detail_tutorial_js_does_not_pass_is_final_page(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringNotContainsString('isFinalPage', $js);
    }

    /**
     * 直接アクセス(sessionStorageに進行状態が無い、または他ページ向けの進行状態)
     * では自動開始しないことを、実装のガード条件として確認する。
     */
    public function test_product_detail_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringContainsString("loadProgress('buyer')", $js);
        $this->assertStringContainsString("progress.route !== 'products.show'", $js);
    }

    /**
     * ステップ4(注文へ進む/ログインする)の対象がクリックされたら、
     * 遷移前にDemoTutorial.close()でオーバーレイ・進行状態を片付けることを確認する。
     * 第3-Aではログイン画面・注文確認画面側での再開は行わない。
     */
    public function test_product_detail_tutorial_js_closes_before_navigating_away(): void
    {
        $js = file_get_contents(public_path('js/product-detail-tutorial.js'));

        $this->assertStringContainsString("data-demo-tutorial=\"buyer-order-button\"", $js);
        $this->assertStringContainsString('window.DemoTutorial.close()', $js);
    }

    /**
     * 商品一覧側(buyer-home-tutorial.js)が、商品カードの実クリック時に
     * products.show向けの進行状態を保存することを確認する。
     * リンクの遷移そのもの(preventDefault等)は行っていないことも合わせて確認する。
     */
    public function test_buyer_home_tutorial_js_saves_progress_on_card_link_click(): void
    {
        $js = file_get_contents(public_path('js/buyer-home-tutorial.js'));

        $this->assertStringContainsString("saveProgress('buyer', { stepIndex: 0, route: 'products.show', flow: currentFlow })", $js);
        $this->assertStringContainsString('TOTAL_STEPS_GUEST = 11', $js);
        // 実際にpreventDefault()を呼び出すコードが無いこと(遷移そのものを
        // 妨げていないこと)を確認する。設計意図を説明するコメント文中の
        // 単語とは区別するため、呼び出し構文の形で検索する。
        $this->assertStringNotContainsString('.preventDefault(', $js);
    }

    /**
     * 共通基盤(demo-tutorial.js)が、非表示要素(hidden属性等)を対象として
     * 誤検出しないための可視性判定を持つことを確認する
     * (配達予定日の選択が不要な商品では配達日欄が最初から非表示のため)。
     */
    public function test_demo_tutorial_js_has_visibility_guard_for_target_lookup(): void
    {
        $js = file_get_contents(public_path('js/demo-tutorial.js'));

        $this->assertStringContainsString('function isVisible(el)', $js);
        $this->assertStringContainsString('el && isVisible(el)', $js);
    }
}
