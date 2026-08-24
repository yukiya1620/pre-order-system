<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailPageTest extends TestCase
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

    public function test_guest_can_access_page(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
    }

    public function test_farmer_is_redirected_to_farmer_home(): void
    {
        $sale = $this->createSale();
        $farmer = User::factory()->farmer()->create();

        $response = $this->actingAs($farmer)->get('/products/'.$sale->id);

        $response->assertRedirect(route('farmer.home'));
    }

    public function test_buyer_can_access_page(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/products/'.$sale->id);

        $response->assertOk();
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $response = $this->get('/products/99999');

        $response->assertStatus(404);
    }

    public function test_page_uses_common_layout(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('css/app.css', false);
    }

    public function test_page_loads_javascript(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('js/product-detail.js', false);
    }

    public function test_page_embeds_correct_api_url(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('data-product-url="'.url('/api/v1/products/'.$sale->id).'"', false);
    }

    public function test_page_has_back_link_to_buyer_home(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('href="'.route('buyer.home').'"', false);
    }

    public function test_guest_sees_login_prompt_instead_of_order_button(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertDontSee('id="product-detail-order-button"', false);
    }

    public function test_buyer_sees_enabled_order_button(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('id="product-detail-order-button"', false);
        $response->assertSee('この内容で注文する', false);
    }

    public function test_page_embeds_order_confirm_base_url(): void
    {
        $sale = $this->createSale();
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer)->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('data-order-confirm-base-url="'.url('/orders/confirm').'"', false);
    }

    public function test_page_has_delivery_date_selection_container(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('id="product-detail-delivery-date-field"', false);
        $response->assertSee('id="product-detail-delivery-date-select"', false);
    }

    /**
     * 配達予定日の選択肢生成・URLへの引き継ぎはJS内で行うため、F9で確立したパターン
     * (file_get_contents + assertStringContainsString)で確認する。
     */
    public function test_product_detail_js_handles_delivery_date_selection(): void
    {
        $js = file_get_contents(public_path('js/product-detail.js'));

        $this->assertStringContainsString('requires_delivery_date_selection', $js);
        $this->assertStringContainsString('renderDeliveryDateOptions', $js);
        $this->assertStringContainsString("params.set('delivery_date', deliveryDateSelect.value)", $js);
    }

    /**
     * 配達予定日の選択欄は、期間の最初の日を勝手に選ばず、必ず空の選択肢
     * (value="")から始まる。未選択のまま注文へ進もうとした場合はブロックされる。
     */
    public function test_product_detail_js_defaults_delivery_date_to_blank_placeholder(): void
    {
        $js = file_get_contents(public_path('js/product-detail.js'));

        $this->assertStringContainsString("placeholder.value = ''", $js);
        $this->assertStringContainsString('配達予定日を選択してください', $js);
        $this->assertStringContainsString("deliveryDateSelect.value = ''", $js);
        $this->assertStringContainsString('sale.requires_delivery_date_selection && !deliveryDateSelect.value', $js);
    }

    public function test_page_delivery_date_select_has_required_attribute(): void
    {
        $sale = $this->createSale();

        $response = $this->get('/products/'.$sale->id);

        $response->assertOk();
        $response->assertSee('id="product-detail-delivery-date-select" required', false);
    }
}
