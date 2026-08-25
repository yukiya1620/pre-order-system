<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 注文完了(orders.complete)への操作チュートリアル接続(第3-C、購入者
 * チュートリアルの最終ステップ)を確認する。注文完了の表示処理そのものは
 * 既存のOrderCompletePageTest等がカバーしているため、ここではチュートリアル
 * 関連の出し分け・data属性・接続コードの中身だけを確認する。
 */
class OrderCompleteTutorialTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderFor(User $buyer): Order
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $product = Product::create([
            'name' => 'トマト',
            'description' => '甘いトマト',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
        $sale = ProductSale::create([
            'product_id' => $product->id,
            'price' => 500,
            'stock_quantity' => 10,
            'initial_stock' => 10,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
        ]);

        $order = Order::create([
            'order_number' => '20260715-0001',
            'user_id' => $buyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 500,
            'delivery_address' => $buyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => 'トマト',
            'unit_price' => 500,
            'quantity' => 1,
            'subtotal' => 500,
        ]);

        return $order;
    }

    public function test_tutorial_js_is_not_loaded_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertDontSee('js/order-complete-tutorial.js', false);
    }

    public function test_tutorial_js_is_loaded_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('js/order-complete-tutorial.js', false);
    }

    /**
     * data属性は既存の方針どおり、DEMO_MODEの値に関わらず常時出力する。
     */
    public function test_target_data_attribute_is_present_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $buyer = User::factory()->create();
        $order = $this->createOrderFor($buyer);

        $response = $this->actingAs($buyer)->get('/orders/'.$order->id.'/complete');

        $response->assertOk();
        $response->assertSee('data-demo-tutorial="buyer-order-complete"', false);
    }

    /**
     * 最終ステップの定義・経路ごとの合計ステップ数/開始位置は、JS内で
     * 完結しているため、既存のF9パターンで確認する。
     */
    public function test_order_complete_tutorial_js_defines_final_step_with_flow_based_offsets(): void
    {
        $js = file_get_contents(public_path('js/order-complete-tutorial.js'));

        $this->assertStringContainsString("target: 'buyer-order-complete'", $js);
        $this->assertStringContainsString('TOTAL_STEPS_GUEST = 11', $js);
        $this->assertStringContainsString('TOTAL_STEPS_AUTHENTICATED = 7', $js);
        $this->assertStringContainsString('STEP_OFFSET_GUEST = 10', $js);
        $this->assertStringContainsString('STEP_OFFSET_AUTHENTICATED = 6', $js);
    }

    /**
     * 直接アクセス(sessionStorageに進行状態が無い、または他ページ向けの進行状態)
     * では自動開始しないことを確認する。
     */
    public function test_order_complete_tutorial_js_only_resumes_with_matching_route_progress(): void
    {
        $js = file_get_contents(public_path('js/order-complete-tutorial.js'));

        $this->assertStringContainsString("loadProgress('buyer')", $js);
        $this->assertStringContainsString("progress.route !== 'orders.complete'", $js);
    }

    /**
     * 既読フラグ(markTutorialSeen)は、この接続コードから直接呼ばれていない
     * ことを確認する。最終ステップの「完了」を利用者本人が押したときだけ、
     * 共通基盤(demo-tutorial.js)のfinish()に委譲される設計であることの裏付け。
     */
    public function test_order_complete_tutorial_js_does_not_call_mark_seen_directly(): void
    {
        $js = file_get_contents(public_path('js/order-complete-tutorial.js'));

        $this->assertStringNotContainsString('markTutorialSeen', $js);
        $this->assertStringNotContainsString('.finish()', $js);
    }

    /**
     * orders.completeは、購入者チュートリアル全体で本当に最後のページなので、
     * isFinalPage: trueを渡すことを確認する。これにより共通基盤側で、
     * ローカル最終ステップ(ここでは唯一のステップ)が「完了」ボタンとして
     * 表示され、finish()(既読フラグ保存)へ到達できる。
     */
    public function test_order_complete_tutorial_js_passes_is_final_page_true(): void
    {
        $js = file_get_contents(public_path('js/order-complete-tutorial.js'));

        $this->assertStringContainsString('isFinalPage: true', $js);
    }

    /**
     * order-complete.jsへの変更は、既存の注文完了表示処理(GET /api/v1/orders/{id})を
     * 変えず、カスタムイベントの発火だけに留まっていることを確認する。
     */
    public function test_order_complete_js_dispatches_event_without_changing_business_logic(): void
    {
        $js = file_get_contents(public_path('js/order-complete.js'));

        $this->assertStringContainsString("new CustomEvent('demo-tutorial:order-complete-rendered')", $js);
        $this->assertStringContainsString("method: 'GET'", $js);
    }
}
