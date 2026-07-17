<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesService;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PortfolioDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_USER_EMAILS = [
        'demo-farmer@example.com',
        'demo-buyer1@example.com',
        'demo-buyer2@example.com',
        'demo-buyer3@example.com',
        'demo-buyer4@example.com',
        'demo-buyer5@example.com',
    ];

    private const DEMO_PRODUCT_NAMES = [
        '朝採れとうもろこし',
        '完熟ミニトマト',
        'ほくほくじゃがいも',
        '採れたて新玉ねぎ',
        '甘熟紅はるか',
        '朝採れほうれん草',
    ];

    private const DEMO_ANNOUNCEMENT_TITLES = [
        '本日、朝採れ野菜を出荷しました',
        '今週末は農園直売所もオープンしています',
        'とうもろこしが今シーズンも入荷しました',
        '配達時間帯についてのお願い',
        '新玉ねぎ、まもなく販売終了です',
    ];

    private function runSeeder(): void
    {
        (new PortfolioDemoSeeder())->run();
    }

    private function demoOrderIds()
    {
        return Order::where('order_number', 'like', 'DEMO-%')->pluck('id');
    }

    public function test_seeder_creates_expected_demo_data(): void
    {
        $this->runSeeder();

        $this->assertSame(1, User::where('email', 'demo-farmer@example.com')->count());
        $this->assertSame(5, User::whereIn('email', array_slice(self::DEMO_USER_EMAILS, 1))->count());
        $this->assertSame(6, Product::whereIn('name', self::DEMO_PRODUCT_NAMES)->count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());
        $this->assertSame(5, Announcement::whereIn('title', self::DEMO_ANNOUNCEMENT_TITLES)->count());
    }

    public function test_running_seeder_twice_does_not_increase_counts(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(6, User::count());
        $this->assertSame(2, Category::count());
        $this->assertSame(6, Product::count());
        $this->assertSame(10, Order::count());
        $this->assertSame(5, Announcement::count());
    }

    public function test_seeder_does_not_delete_non_demo_data(): void
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $normalProduct = Product::create([
            'name' => '有機にんじん',
            'description' => '通常データ',
            'category_id' => $category->id,
            'unit_label' => '袋',
        ]);
        $normalFarmer = User::factory()->farmer()->create(['email' => 'farmer@example.com']);
        $normalBuyer = User::factory()->create(['email' => 'buyer@example.com']);
        $normalOrder = Order::create([
            'order_number' => '20260101-0001',
            'user_id' => $normalBuyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 100,
            'delivery_address' => '通常住所',
            'delivery_date' => now()->addDay()->toDateString(),
        ]);

        $this->runSeeder();

        $this->assertDatabaseHas('products', ['id' => $normalProduct->id]);
        $this->assertDatabaseHas('users', ['id' => $normalFarmer->id]);
        $this->assertDatabaseHas('users', ['id' => $normalBuyer->id]);
        $this->assertDatabaseHas('orders', ['id' => $normalOrder->id]);
        $this->assertSame(1, Category::where('name', '季節商品')->count());
    }

    public function test_sales_summary_matches_expected_values(): void
    {
        $this->runSeeder();

        $summary = app(SalesService::class)->summary();

        $this->assertSame(['total_amount' => 1460, 'order_count' => 2], $summary['confirmed']['today']);
        $this->assertSame(['total_amount' => 1960, 'order_count' => 3], $summary['confirmed']['this_month']);
        $this->assertSame(['total_amount' => 3710, 'order_count' => 5], $summary['confirmed']['this_year']);
        $this->assertSame(['total_amount' => 2450, 'order_count' => 4], $summary['pending']);
    }

    public function test_payment_breakdown_matches_expected_values(): void
    {
        $this->runSeeder();

        $summary = app(SalesService::class)->summary();

        $this->assertSame(['total_amount' => 1960, 'order_count' => 3], $summary['payment_status_breakdown']['paid']);
        $this->assertSame(['total_amount' => 1350, 'order_count' => 1], $summary['payment_status_breakdown']['unpaid']);
        $this->assertSame(['total_amount' => 400, 'order_count' => 1], $summary['payment_status_breakdown']['refunded']);

        $this->assertSame(['total_amount' => 960, 'order_count' => 2], $summary['payment_method_breakdown']['cash']);
        $this->assertSame(['total_amount' => 1400, 'order_count' => 2], $summary['payment_method_breakdown']['card']);
        $this->assertSame(['total_amount' => 1350, 'order_count' => 1], $summary['payment_method_breakdown']['paypay']);
    }

    public function test_all_order_statuses_are_represented(): void
    {
        $this->runSeeder();

        foreach ([
            Order::STATUS_RECEIVED,
            Order::STATUS_DELIVERY_CONFIRMED,
            Order::STATUS_DELIVERY_CHANGED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ] as $status) {
            $count = Order::where('order_number', 'like', 'DEMO-%')->where('status', $status)->count();
            $this->assertGreaterThanOrEqual(1, $count, "status={$status} の注文が存在しません");
        }
    }

    public function test_delivery_confirmations_include_unanswered_and_answered(): void
    {
        $this->runSeeder();

        $demoOrderIds = $this->demoOrderIds();

        $this->assertSame(2, DeliveryConfirmation::whereIn('order_id', $demoOrderIds)->whereNull('responded_at')->count());
        $this->assertSame(2, DeliveryConfirmation::whereIn('order_id', $demoOrderIds)->whereNotNull('responded_at')->count());
    }

    public function test_order_adjustments_include_reduction_and_cancellation(): void
    {
        $this->runSeeder();

        $demoOrderIds = $this->demoOrderIds();

        $this->assertSame(1, OrderAdjustment::whereIn('order_id', $demoOrderIds)->where('change_type', OrderAdjustment::CHANGE_TYPE_QUANTITY_REDUCED)->count());
        $this->assertSame(1, OrderAdjustment::whereIn('order_id', $demoOrderIds)->where('change_type', OrderAdjustment::CHANGE_TYPE_CANCELLED)->count());
    }

    public function test_notifications_match_expected_counts_by_type(): void
    {
        $this->runSeeder();

        $demoUserIds = User::whereIn('email', self::DEMO_USER_EMAILS)->pluck('id');
        $notifications = Notification::whereIn('user_id', $demoUserIds)->get();

        $this->assertSame(33, $notifications->count());
        $this->assertSame(10, $notifications->where('type', '注文受付')->count());
        $this->assertSame(10, $notifications->where('type', '新規注文')->count());
        $this->assertSame(4, $notifications->where('type', '配達確認依頼')->count());
        $this->assertSame(1, $notifications->where('type', '配達予定確認')->count());
        $this->assertSame(1, $notifications->where('type', '配達予定変更')->count());
        $this->assertSame(5, $notifications->where('type', '配達完了')->count());
        $this->assertSame(1, $notifications->where('type', '数量変更確定')->count());
        $this->assertSame(1, $notifications->where('type', '注文キャンセル')->count());

        $demoOrderIds = $this->demoOrderIds();
        $this->assertTrue($notifications->pluck('related_order_id')->every(fn ($id) => $demoOrderIds->contains($id)));
    }

    public function test_seeder_aborts_when_ambiguous_product_name_exists(): void
    {
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $ambiguousProduct = Product::create([
            'name' => '朝採れとうもろこし',
            'description' => 'デモ注文に紐づかない、同名の非デモ商品',
            'category_id' => $category->id,
            'unit_label' => '本',
        ]);

        $thrown = null;

        try {
            $this->runSeeder();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertStringContainsString('朝採れとうもろこし', $thrown->getMessage());

        $this->assertDatabaseHas('products', ['id' => $ambiguousProduct->id]);
        $this->assertSame(1, Product::where('name', '朝採れとうもろこし')->count());

        $this->assertDatabaseMissing('users', ['email' => 'demo-farmer@example.com']);
        $this->assertSame(0, User::whereIn('email', array_slice(self::DEMO_USER_EMAILS, 1))->count());
        $this->assertSame(0, Order::where('order_number', 'like', 'DEMO-%')->count());
    }

    public function test_seeder_refuses_to_run_in_production(): void
    {
        $originalEnvironment = app()->environment();
        $thrown = null;

        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->runSeeder();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }

        $this->assertNotNull($thrown);
        $this->assertDatabaseMissing('users', ['email' => 'demo-farmer@example.com']);
        $this->assertSame(0, Order::where('order_number', 'like', 'DEMO-%')->count());
    }
}
