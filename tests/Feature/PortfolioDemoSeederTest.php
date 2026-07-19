<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderChangeRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesService;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PortfolioDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 画像コピー処理(prepareDemoImages())が実際のstorage/app/publicへ書き込まないよう、
        // すべてのテストで共通してpublicディスクを偽装する。1テスト内で複数回runSeeder()を
        // 呼ぶケースがあるため、setUp()で1回だけ偽装し、runSeeder()側では呼び直さない。
        Storage::fake('public');
    }

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

    private const DEMO_PRODUCT_IMAGE_FILENAMES = [
        '朝採れとうもろこし' => 'corn.jpg',
        '完熟ミニトマト' => 'mini-tomato.jpg',
        'ほくほくじゃがいも' => 'potato.jpg',
        '採れたて新玉ねぎ' => 'new-onion.jpg',
        '甘熟紅はるか' => 'sweet-potato.jpg',
        '朝採れほうれん草' => 'spinach.jpg',
    ];

    private const DEMO_IMAGE_DIRECTORY = 'products/portfolio-demo';

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
        $this->assertSame(2, OrderChangeRequest::count());
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

    public function test_change_requests_include_pending_quantity_reduction_and_cancellation(): void
    {
        $this->runSeeder();

        $this->assertSame(1, OrderChangeRequest::where('request_type', OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION)->count());
        $this->assertSame(1, OrderChangeRequest::where('request_type', OrderChangeRequest::REQUEST_TYPE_CANCELLATION)->count());
        $this->assertSame(2, OrderChangeRequest::whereNull('resolved_at')->count());
        $this->assertSame(0, OrderChangeRequest::whereNotNull('resolved_at')->count());
    }

    public function test_change_requests_are_linked_to_expected_orders(): void
    {
        $this->runSeeder();

        $quantityReductionOrder = Order::where('order_number', 'DEMO-0001')->firstOrFail();
        $quantityReductionRequest = OrderChangeRequest::where('request_type', OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION)->firstOrFail();

        $this->assertSame($quantityReductionOrder->id, $quantityReductionRequest->order_id);
        $this->assertSame(2, $quantityReductionRequest->quantity_at_request);
        $this->assertSame(1, $quantityReductionRequest->requested_quantity);

        $cancellationOrder = Order::where('order_number', 'DEMO-0010')->firstOrFail();
        $cancellationRequest = OrderChangeRequest::where('request_type', OrderChangeRequest::REQUEST_TYPE_CANCELLATION)->firstOrFail();

        $this->assertSame($cancellationOrder->id, $cancellationRequest->order_id);
        $this->assertSame(1, $cancellationRequest->quantity_at_request);
        $this->assertNull($cancellationRequest->requested_quantity);
    }

    public function test_notifications_match_expected_counts_by_type(): void
    {
        $this->runSeeder();

        $demoUserIds = User::whereIn('email', self::DEMO_USER_EMAILS)->pluck('id');
        $notifications = Notification::whereIn('user_id', $demoUserIds)->get();

        $this->assertSame(35, $notifications->count());
        $this->assertSame(10, $notifications->where('type', '注文受付')->count());
        $this->assertSame(10, $notifications->where('type', '新規注文')->count());
        $this->assertSame(4, $notifications->where('type', '配達確認依頼')->count());
        $this->assertSame(1, $notifications->where('type', '配達予定確認')->count());
        $this->assertSame(1, $notifications->where('type', '配達予定変更')->count());
        $this->assertSame(5, $notifications->where('type', '配達完了')->count());
        $this->assertSame(1, $notifications->where('type', '数量変更確定')->count());
        $this->assertSame(1, $notifications->where('type', '注文キャンセル')->count());
        $this->assertSame(1, $notifications->where('type', '数量変更相談')->count());
        $this->assertSame(1, $notifications->where('type', '注文キャンセル相談')->count());

        $demoOrderIds = $this->demoOrderIds();
        $this->assertTrue($notifications->pluck('related_order_id')->every(fn ($id) => $demoOrderIds->contains($id)));
    }

    public function test_product_image_paths_are_set_as_expected(): void
    {
        $this->runSeeder();

        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $productName => $filename) {
            $this->assertDatabaseHas('products', [
                'name' => $productName,
                'image' => self::DEMO_IMAGE_DIRECTORY.'/'.$filename,
            ]);
        }
    }

    public function test_product_images_are_copied_to_public_disk(): void
    {
        $this->runSeeder();

        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $filename) {
            Storage::disk('public')->assertExists(self::DEMO_IMAGE_DIRECTORY.'/'.$filename);
        }
    }

    public function test_copied_images_match_source_bytes_exactly(): void
    {
        $this->runSeeder();

        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $filename) {
            $sourcePath = database_path('seeders/demo-assets/products/'.$filename);
            $expected = file_get_contents($sourcePath);
            $actual = Storage::disk('public')->get(self::DEMO_IMAGE_DIRECTORY.'/'.$filename);

            $this->assertSame($expected, $actual, "{$filename} の内容が元画像と一致しません");
        }
    }

    public function test_running_seeder_twice_overwrites_images_without_duplicates(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $demoImages = Storage::disk('public')->allFiles(self::DEMO_IMAGE_DIRECTORY);

        $this->assertCount(6, $demoImages);

        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $filename) {
            $sourcePath = database_path('seeders/demo-assets/products/'.$filename);
            $expected = file_get_contents($sourcePath);
            $actual = Storage::disk('public')->get(self::DEMO_IMAGE_DIRECTORY.'/'.$filename);

            $this->assertSame($expected, $actual);
        }
    }

    public function test_seeder_does_not_affect_images_outside_demo_directory(): void
    {
        Storage::disk('public')->put('products/real-farmer-photo.jpg', 'not-a-demo-image');

        $this->runSeeder();

        // 実運用でアップロードされた画像(products/直下)はそのまま残る
        Storage::disk('public')->assertExists('products/real-farmer-photo.jpg');
        $this->assertSame('not-a-demo-image', Storage::disk('public')->get('products/real-farmer-photo.jpg'));

        // デモ画像は products/ 直下ではなく products/portfolio-demo/ にのみ作られる
        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $filename) {
            Storage::disk('public')->assertMissing('products/'.$filename);
        }

        // products/ 直下には、事前に置いた1枚だけが存在する(デモ画像が紛れ込んでいない)
        $this->assertSame(['products/real-farmer-photo.jpg'], Storage::disk('public')->files('products'));
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
