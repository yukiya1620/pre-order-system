<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 注文確認(orders.confirm)への操作チュートリアル接続(第3-C)を確認する。
 * 注文確認・注文確定処理そのものは既存のOrderConfirmPageTest等がカバーしている
 * ため、ここではチュートリアル関連の出し分け・data属性・接続コードの中身だけを確認する。
 */
class OrderConfirmTutorialTest extends TestCase
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
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id='.$sale->id.'&quantity=1');

        $response->assertOk();
        $response->assertDontSee('js/order-confirm-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id='.$sale->id.'&quantity=1');

        $response->assertOk();
        $response->assertSee('js/order-confirm-tutorial.js', false);
    }

    /**
     * data属性は既存の方針どおり、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_target_data_attributes_are_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/orders/confirm?product_sale_id='.$sale->id.'&quantity=1');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-order-summary"', false);
        $response->assertSee('data-demo-tutorial="buyer-order-submit"', false);
    }

    /**
     * ステップ定義・経路ごとの合計ステップ数/開始位置は、JS内で完結しているため、
     * 既存のF9パターン(file_get_contents + assertStringContainsString)で確認する。
     */
    public function test_order_confirm_tutorial_js_defines_two_steps_with_flow_based_offsets(): void
    {
        $js = file_get_contents(public_path('js/order-confirm-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-order-summary'", $js);
        $this->assertStringContainsString("target: 'buyer-order-submit'", $js);
        $this->assertStringContainsString('TOTAL_STEPS_GUEST = 11', $js);
        $this->assertStringContainsString('TOTAL_STEPS_AUTHENTICATED = 7', $js);
        $this->assertStringContainsString('STEP_OFFSET_GUEST = 8', $js);
        $this->assertStringContainsString('STEP_OFFSET_AUTHENTICATED = 4', $js);
    }

    /**
     * 直接アクセス(sessionStorageに進行状態が無い、または他ページ向けの進行状態)
     * では自動開始しないことを、実装のガード条件として確認する。
     */
    public function test_order_confirm_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/order-confirm-tutorial.js'));

        $this->assertStringContainsString("loadProgress('buyer')", $js);
        $this->assertStringContainsString("progress.route !== 'orders.confirm'", $js);
    }

    /**
     * 「この内容で注文する」ボタンが実際にクリックされたら、遷移するかもしれない
     * ことに備えてclose()でオーバーレイを片付ける(既読フラグは立たない)ことを確認する。
     * チュートリアルが注文を自動送信するようなコード(fetch/submitButton.click()等)を
     * 独自に呼んでいないことも合わせて確認する。
     */
    public function test_order_confirm_tutorial_js_closes_on_submit_click_without_auto_submitting(): void
    {
        $js = file_get_contents(public_path('js/order-confirm-tutorial.js'));

        $this->assertStringContainsString('data-demo-tutorial="buyer-order-submit"', $js);
        $this->assertStringContainsString('window.DemoTutorial.close()', $js);
        $this->assertStringNotContainsString('.click()', $js);
        $this->assertStringNotContainsString('fetch(', $js);
    }

    /**
     * 注文確定APIが成功した場合(order-confirm.js側が発火する
     * demo-tutorial:order-submittedイベント)だけ、注文完了画面用の
     * 進行状況を保存することを確認する。失敗時はこのイベントが発火しない
     * ため保存されず、チュートリアルが勝手に完了扱いになることもない。
     */
    public function test_order_confirm_tutorial_js_saves_progress_only_after_successful_submission(): void
    {
        $js = file_get_contents(public_path('js/order-confirm-tutorial.js'));

        $this->assertStringContainsString("addEventListener('demo-tutorial:order-submitted'", $js);
        $this->assertStringContainsString("saveProgress('buyer', { stepIndex: 0, route: 'orders.complete', flow: currentFlow })", $js);
        // チュートリアルを開いていない通常の利用者が注文しただけでは
        // 保存しないためのガード。
        $this->assertStringContainsString('if (!wasActiveWhenSubmitted)', $js);
    }

    /**
     * order-confirm.jsへの変更は、既存の注文確定処理(POST /api/v1/orders、
     * location.replaceによる遷移)を変えず、カスタムイベントの発火だけに
     * 留まっていることを確認する。
     */
    public function test_order_confirm_js_dispatches_events_without_changing_business_logic(): void
    {
        $js = file_get_contents(public_path('js/order-confirm.js'));

        $this->assertStringContainsString("new CustomEvent('demo-tutorial:order-confirm-rendered')", $js);
        $this->assertStringContainsString("new CustomEvent('demo-tutorial:order-submitted'", $js);

        // 既存の注文確定処理(POST先・成功後の遷移)がそのまま残っていることを確認する。
        $this->assertStringContainsString("fetch(ordersUrl, {", $js);
        $this->assertStringContainsString('window.location.replace(orderCompleteBaseUrl', $js);
    }

    /**
     * 注文確認画面はisFinalPageを渡さない(購入者チュートリアル全体の
     * 最終ページではないため)ことを確認する。これにより、ローカル最終
     * ステップ(注文確定ボタン)で「完了」ボタンが誤って表示されず、
     * 利用者本人が実際の注文確定ボタンを押すことでのみ次へ進む
     * (第3-Cで発見・対応)。
     */
    public function test_order_confirm_tutorial_js_does_not_pass_is_final_page(): void
    {
        $js = file_get_contents(public_path('js/order-confirm-tutorial.js'));

        $this->assertStringNotContainsString('isFinalPage', $js);
    }
}
