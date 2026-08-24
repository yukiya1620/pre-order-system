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
        // 第3-Bにより、合計ステップ数はまだ確定していない旨を示す '?' 表示に
        // なった(第3-Cで注文確認・注文完了が接続されるまでの暫定仕様)。
        $this->assertStringContainsString("TOTAL_STEPS = '?'", $js);
        $this->assertStringContainsString('STEP_OFFSET = 1', $js);
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

        $this->assertStringContainsString("saveProgress('buyer', { stepIndex: 0, route: 'products.show' })", $js);
        $this->assertStringContainsString("TOTAL_STEPS = '?'", $js);
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
