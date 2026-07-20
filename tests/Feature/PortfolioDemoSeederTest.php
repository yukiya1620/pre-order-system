<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\DeliveryConfirmation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
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

    /**
     * 配達予定日の選択機能(3.5節)をポートフォリオ上で確認できるよう、
     * 朝採れとうもろこしだけが配達予定期間(3日間)を持つ。他の単日商品には影響しない。
     */
    public function test_corn_has_selectable_delivery_date_range_and_other_products_do_not(): void
    {
        $today = now()->startOfDay();

        $this->runSeeder();

        $corn = ProductSale::whereHas('product', function ($query) {
            $query->where('name', '朝採れとうもろこし');
        })->firstOrFail();

        $this->assertSame($today->copy()->addDays(3)->toDateString(), $corn->delivery_date_from->toDateString());
        $this->assertSame($today->copy()->addDays(5)->toDateString(), $corn->delivery_date_to->toDateString());
        $this->assertTrue($corn->requiresDeliveryDateSelection());

        $otherProductNames = array_diff(self::DEMO_PRODUCT_NAMES, ['朝採れとうもろこし']);
        $others = ProductSale::whereHas('product', function ($query) use ($otherProductNames) {
            $query->whereIn('name', $otherProductNames);
        })->get();

        $this->assertCount(5, $others);
        foreach ($others as $sale) {
            $this->assertFalse($sale->requiresDeliveryDateSelection(), $sale->product->name.'は選択不要のはず');
        }
    }

    /**
     * Seederを複数回実行しても、とうもろこしの配達予定期間が重複・不整合を起こさないこと。
     */
    public function test_running_seeder_twice_keeps_corn_delivery_date_range_consistent(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $corns = ProductSale::whereHas('product', function ($query) {
            $query->where('name', '朝採れとうもろこし');
        })->get();

        $this->assertCount(1, $corns);

        $corn = $corns->first();
        $this->assertNotNull($corn->delivery_date_to);
        $this->assertTrue($corn->delivery_date_to->gt($corn->delivery_date_from));
        $this->assertTrue($corn->requiresDeliveryDateSelection());
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

    /**
     * デモ環境でデモ注文に対して実際に相談機能等を使うと、
     * OrderChangeRequestService::notifyFarmers()が登録されている農家全員に通知するため、
     * デモユーザーではない通常の農家アカウント宛てにも「デモ注文を参照する」通知が作られる。
     * この状態でSeederを再実行しても、外部キーエラーにならず・デモ注文関連データが
     * 正しく初期状態へ戻り・通常ユーザーの無関係なデータは保持されることを確認する。
     */
    public function test_reseeding_after_real_interaction_with_demo_orders_does_not_fail(): void
    {
        // デモユーザーとは別の、通常の農家アカウント(実際の事例と同じくfarmer@example.com)
        $normalFarmer = User::factory()->farmer()->create(['email' => 'farmer@example.com']);

        $this->runSeeder();

        $demoOrder = Order::where('order_number', 'DEMO-0002')->firstOrFail();
        $demoOrderItem = $demoOrder->orderItems()->firstOrFail();

        // デモ注文に対して本物の「数量変更を相談する」を実際に使った場合と同じ状況を再現する。
        // ownershipチェックは購入者(demoOrder->user_id)本人が行った体で、
        // 通知だけが「登録されている農家全員」(=通常農家も含む)へ届く。
        $extraChangeRequest = OrderChangeRequest::create([
            'order_id' => $demoOrder->id,
            'order_item_id' => $demoOrderItem->id,
            'request_type' => OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION,
            'quantity_at_request' => $demoOrderItem->quantity,
            'requested_quantity' => 1,
            'requested_by' => $demoOrder->user_id,
        ]);

        $strayNotification = Notification::create([
            'user_id' => $normalFarmer->id,
            'type' => '数量変更相談',
            'title' => 'ご相談が届きました',
            'body' => 'デモ注文についての相談(通常農家宛て)',
            'related_order_id' => $demoOrder->id,
            'is_read' => false,
        ]);

        // 通常農家の、デモ注文とは無関係な通知(削除されてはいけない)
        $unrelatedNotification = Notification::create([
            'user_id' => $normalFarmer->id,
            'type' => '新規注文',
            'title' => '新しい注文が入りました',
            'body' => '通常農家自身の注文についての通知',
            'related_order_id' => null,
            'is_read' => false,
        ]);

        // 外部キーエラーにならずに再実行できること
        $this->runSeeder();

        // デモ注文関連データが、1回だけ実行した場合と同じ初期状態(合計2件)へ戻っていること
        $this->assertSame(2, OrderChangeRequest::count());
        $this->assertDatabaseMissing('order_change_requests', ['id' => $extraChangeRequest->id]);

        // 通常農家宛てで、デモ注文を参照していた通知は削除されている
        $this->assertDatabaseMissing('notifications', ['id' => $strayNotification->id]);

        // 通常農家の、デモ注文と無関係な通知・アカウント自体は保持されている
        $this->assertDatabaseHas('notifications', ['id' => $unrelatedNotification->id]);
        $this->assertDatabaseHas('users', ['id' => $normalFarmer->id]);

        // 冪等性: もう一度実行しても件数が変わらない
        $this->runSeeder();
        $this->assertSame(2, OrderChangeRequest::count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());
    }

    /**
     * より広い実操作パターンの再現。次の3種類の注文が同時に存在する状態を作る。
     * ①デモユーザーが本物の注文フロー(通常の注文番号、DEMO-接頭辞ではない)でデモ商品を注文
     * ②デモユーザーではない通常購入者がデモ商品を注文
     * ③通常購入者が通常商品を注文した、デモと完全に無関係な注文(対照データ)
     * ①②それぞれに通常農家宛ての通知・相談・注文調整履歴も作る。
     * この状態でSeederを再実行しても、外部キーエラーにならず、デモに関連する①②のデータは
     * すべて初期状態へ戻り、③の無関係なデータだけが保持されることを確認する。
     */
    public function test_reseeding_cleans_up_real_orders_referencing_demo_products_regardless_of_buyer(): void
    {
        $normalFarmer = User::factory()->farmer()->create(['email' => 'farmer@example.com']);
        $normalBuyer = User::factory()->create(['email' => 'buyer@example.com']);

        $this->runSeeder();

        $demoCornSale = ProductSale::whereHas('product', function ($query) {
            $query->where('name', '朝採れとうもろこし');
        })->firstOrFail();
        $demoBuyer = User::where('email', 'demo-buyer1@example.com')->firstOrFail();

        // ①デモユーザーが、本物の注文フロー(通常の注文番号)でデモ商品を注文
        $orderByDemoBuyer = Order::create([
            'order_number' => now()->format('Ymd').'-9001',
            'user_id' => $demoBuyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => $demoCornSale->price,
            'delivery_address' => $demoBuyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);
        $demoBuyerOrderItem = OrderItem::create([
            'order_id' => $orderByDemoBuyer->id,
            'product_sale_id' => $demoCornSale->id,
            'product_name' => $demoCornSale->product->name,
            'unit_price' => $demoCornSale->price,
            'quantity' => 1,
            'subtotal' => $demoCornSale->price,
        ]);
        $notificationForDemoBuyerOrder = Notification::create([
            'user_id' => $normalFarmer->id,
            'type' => '新規注文',
            'title' => '新しい注文が入りました',
            'body' => 'テスト用(デモユーザーの本物の注文)',
            'related_order_id' => $orderByDemoBuyer->id,
            'is_read' => false,
        ]);
        $adjustment = OrderAdjustment::create([
            'order_id' => $orderByDemoBuyer->id,
            'order_item_id' => $demoBuyerOrderItem->id,
            'change_type' => OrderAdjustment::CHANGE_TYPE_CANCELLED,
            'previous_status' => Order::STATUS_RECEIVED,
            'new_status' => Order::STATUS_CANCELLED,
            'previous_quantity' => 1,
            'new_quantity' => 0,
            'stock_restored' => 1,
            'confirmed_with_buyer_at' => now(),
            'changed_by' => $normalFarmer->id,
        ]);

        // ②デモユーザーではない通常購入者が、デモ商品を注文
        $orderByNormalBuyer = Order::create([
            'order_number' => now()->format('Ymd').'-9002',
            'user_id' => $normalBuyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => $demoCornSale->price,
            'delivery_address' => $normalBuyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);
        $normalBuyerOrderItem = OrderItem::create([
            'order_id' => $orderByNormalBuyer->id,
            'product_sale_id' => $demoCornSale->id,
            'product_name' => $demoCornSale->product->name,
            'unit_price' => $demoCornSale->price,
            'quantity' => 1,
            'subtotal' => $demoCornSale->price,
        ]);
        $notificationForNormalBuyerOrder = Notification::create([
            'user_id' => $normalFarmer->id,
            'type' => '新規注文',
            'title' => '新しい注文が入りました',
            'body' => 'テスト用(通常購入者がデモ商品を注文)',
            'related_order_id' => $orderByNormalBuyer->id,
            'is_read' => false,
        ]);
        $changeRequest = OrderChangeRequest::create([
            'order_id' => $orderByNormalBuyer->id,
            'order_item_id' => $normalBuyerOrderItem->id,
            'request_type' => OrderChangeRequest::REQUEST_TYPE_CANCELLATION,
            'quantity_at_request' => 1,
            'requested_quantity' => null,
            'requested_by' => $normalBuyer->id,
        ]);

        // ③通常購入者が通常商品を注文した、デモと完全に無関係な注文(対照データ)
        $normalCategory = Category::create(['name' => '通常カテゴリ', 'display_order' => 99]);
        $normalProduct = Product::create([
            'name' => '有機にんじん',
            'description' => '通常データ',
            'category_id' => $normalCategory->id,
            'unit_label' => '袋',
        ]);
        $normalSale = ProductSale::create([
            'product_id' => $normalProduct->id,
            'price' => 200,
            'stock_quantity' => 5,
            'initial_stock' => 5,
            'sale_start_date' => now()->subDays(5),
            'sale_end_date' => now()->addDays(30),
            'delivery_date_from' => now()->addDays(3)->toDateString(),
            'status' => ProductSale::STATUS_ON_SALE,
            'is_reservation_open' => true,
            'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
        ]);
        $unrelatedOrder = Order::create([
            'order_number' => now()->format('Ymd').'-9003',
            'user_id' => $normalBuyer->id,
            'status' => Order::STATUS_RECEIVED,
            'total_amount' => 200,
            'delivery_address' => $normalBuyer->address,
            'delivery_date' => now()->addDays(3)->toDateString(),
        ]);
        OrderItem::create([
            'order_id' => $unrelatedOrder->id,
            'product_sale_id' => $normalSale->id,
            'product_name' => $normalProduct->name,
            'unit_price' => 200,
            'quantity' => 1,
            'subtotal' => 200,
        ]);
        $unrelatedNotification = Notification::create([
            'user_id' => $normalFarmer->id,
            'type' => '新規注文',
            'title' => '新しい注文が入りました',
            'body' => 'デモと無関係な注文についての通知',
            'related_order_id' => $unrelatedOrder->id,
            'is_read' => false,
        ]);

        // 外部キーエラーが発生しないこと
        $this->runSeeder();

        // デモ関連の注文・通知・相談・変更履歴が初期状態へ戻ること
        $this->assertDatabaseMissing('orders', ['id' => $orderByDemoBuyer->id]);
        $this->assertDatabaseMissing('orders', ['id' => $orderByNormalBuyer->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $demoBuyerOrderItem->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $normalBuyerOrderItem->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationForDemoBuyerOrder->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationForNormalBuyerOrder->id]);
        $this->assertDatabaseMissing('order_change_requests', ['id' => $changeRequest->id]);
        $this->assertDatabaseMissing('order_adjustments', ['id' => $adjustment->id]);
        $this->assertSame(2, OrderChangeRequest::count());
        $this->assertSame(2, OrderAdjustment::count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());

        // 通常ユーザー・通常商品・それらに関する無関係な注文や通知は保持されること
        $this->assertDatabaseHas('users', ['id' => $normalFarmer->id]);
        $this->assertDatabaseHas('users', ['id' => $normalBuyer->id]);
        $this->assertDatabaseHas('products', ['id' => $normalProduct->id]);
        $this->assertDatabaseHas('product_sales', ['id' => $normalSale->id]);
        $this->assertDatabaseHas('orders', ['id' => $unrelatedOrder->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unrelatedNotification->id]);

        // さらに再実行しても件数が変化しないこと(冪等性)
        $this->runSeeder();
        $this->assertSame(2, OrderChangeRequest::count());
        $this->assertSame(2, OrderAdjustment::count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());
        $this->assertDatabaseHas('orders', ['id' => $unrelatedOrder->id]);
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
