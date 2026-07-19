<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * ポートフォリオ・テスター公開用の架空デモデータを作成するSeeder。
 * 通常の DatabaseSeeder とは完全に独立しており、
 * `php artisan db:seed --class=PortfolioDemoSeeder` を明示的に実行したときのみ動作する。
 * 実在の氏名・電話番号・住所・メールアドレスは一切使用しない。
 */
class PortfolioDemoSeeder extends Seeder
{
    private const DEMO_FARMER_EMAIL = 'demo-farmer@example.com';

    private const DEMO_BUYER_EMAILS = [
        'demo-buyer1@example.com',
        'demo-buyer2@example.com',
        'demo-buyer3@example.com',
        'demo-buyer4@example.com',
        'demo-buyer5@example.com',
    ];

    private const DEMO_BUYER_NAMES = ['デモ 太郎', 'デモ 花子', 'デモ 美咲', 'デモ 健太', 'デモ さくら'];

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

    /**
     * 商品名 => database/seeders/demo-assets/products/ 配下のファイル名。
     * @var array<string, string>
     */
    private const DEMO_PRODUCT_IMAGE_FILENAMES = [
        '朝採れとうもろこし' => 'corn.jpg',
        '完熟ミニトマト' => 'mini-tomato.jpg',
        'ほくほくじゃがいも' => 'potato.jpg',
        '採れたて新玉ねぎ' => 'new-onion.jpg',
        '甘熟紅はるか' => 'sweet-potato.jpg',
        '朝採れほうれん草' => 'spinach.jpg',
    ];

    public function run(): void
    {
        $this->guardAgainstProduction();

        $imagePaths = $this->prepareDemoImages();

        DB::transaction(function () use ($imagePaths) {
            $this->deleteExistingDemoData();

            $categories = $this->createCategories();
            $farmer = $this->createFarmer();
            $buyers = $this->createBuyers();
            $sales = $this->createProducts($categories, $imagePaths);

            $this->createPendingOrders($buyers, $sales, $farmer);
            $this->createDeliveredOrders($buyers, $sales, $farmer);
            $this->createCancelledOrder($buyers, $sales, $farmer);

            $this->createAnnouncements();
        });
    }

    private function guardAgainstProduction(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PortfolioDemoSeederは本番環境(production)では実行できません。');

            throw new RuntimeException('PortfolioDemoSeeder cannot run in the production environment.');
        }
    }

    /**
     * デモ商品画像の複製先ディレクトリ(publicディスク内)。
     * 実際のアップロード機能が使う products/ 直下とは分け、デモデータであることを
     * ディレクトリ名からも明確にする(実運用でアップロードされた画像と混ざらないように)。
     */
    private const DEMO_IMAGE_DIRECTORY = 'products/portfolio-demo';

    /**
     * デモ商品画像を storage/app/public/products/portfolio-demo/ へ複製する。
     * DBトランザクションの外側で先に完結させる(ファイルコピーはロールバック対象外のため)。
     * 6枚すべての存在・読み込み可否を先に検証してから、まとめてコピーする
     * (途中まで複製してから不足に気づく、という状態にはしない)。
     *
     * @return array<string, string> 商品名 => products.image に保存する相対パス
     */
    private function prepareDemoImages(): array
    {
        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $filename) {
            $sourcePath = database_path('seeders/demo-assets/products/'.$filename);

            if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
                throw new RuntimeException(
                    "デモ商品画像が見つからないか読み込めません: {$filename}(database/seeders/demo-assets/products/ に配置してください)"
                );
            }
        }

        $paths = [];

        foreach (self::DEMO_PRODUCT_IMAGE_FILENAMES as $productName => $filename) {
            $sourcePath = database_path('seeders/demo-assets/products/'.$filename);
            $targetPath = self::DEMO_IMAGE_DIRECTORY.'/'.$filename;
            $stored = Storage::disk('public')->put($targetPath, file_get_contents($sourcePath));

            if (! $stored) {
                throw new RuntimeException("デモ商品画像のコピーに失敗しました: {$filename}");
            }

            $paths[$productName] = $targetPath;
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function allDemoEmails(): array
    {
        return [self::DEMO_FARMER_EMAIL, ...self::DEMO_BUYER_EMAILS];
    }

    /**
     * 固定メールアドレス(デモユーザー)と "DEMO-" 接頭辞(注文番号)だけを手がかりに
     * デモデータを特定し、外部キーの依存順に削除する。商品名の一致だけでは
     * 削除対象を決めず、実際にデモ注文で使われた商品IDだけを削除する。
     */
    private function deleteExistingDemoData(): void
    {
        $demoUserIds = User::whereIn('email', $this->allDemoEmails())->pluck('id');

        $mismatched = Order::where('order_number', 'like', 'DEMO-%')
            ->whereNotIn('user_id', $demoUserIds)
            ->exists();

        if ($mismatched) {
            throw new RuntimeException(
                'DEMO-接頭辞の注文が、デモユーザー以外に紐づいています。安全のため削除を中断しました。'
            );
        }

        $demoOrderIds = Order::where('order_number', 'like', 'DEMO-%')
            ->whereIn('user_id', $demoUserIds)
            ->pluck('id');

        $demoProductIds = $this->resolveDemoProductIds($demoOrderIds);

        // order_change_requests.resolved_order_adjustment_id が order_adjustments を、
        // order_id/order_item_id が orders/order_items をそれぞれrestrictOnDeleteで参照しているため、
        // 両方より先に削除する。
        OrderChangeRequest::whereIn('order_id', $demoOrderIds)->delete();
        OrderAdjustment::whereIn('order_id', $demoOrderIds)->delete();
        DeliveryConfirmation::whereIn('order_id', $demoOrderIds)->delete();
        // notifications.related_order_id が orders を外部キー参照しているため、
        // 注文本体を消す前に紐づく通知を先に削除しておく必要がある。
        Notification::whereIn('user_id', $demoUserIds)->delete();
        OrderItem::whereIn('order_id', $demoOrderIds)->delete();
        Order::whereIn('id', $demoOrderIds)->delete();
        Announcement::whereIn('title', self::DEMO_ANNOUNCEMENT_TITLES)->delete();
        ProductSale::whereIn('product_id', $demoProductIds)->delete();
        Product::whereIn('id', $demoProductIds)->delete();
        User::whereIn('id', $demoUserIds)->delete();
    }

    /**
     * デモ注文の明細からたどれる商品IDだけを安全な削除対象とする。
     * デモ商品名と一致するのにデモ注文からたどれない商品が見つかった場合は、
     * 無関係な商品を誤って消さないよう例外で中断する(初回実行時はデモ注文が
     * 存在しないため、この時点では削除対象は空になるのが正しい)。
     *
     * @param  Collection<int, int>  $demoOrderIds
     * @return Collection<int, int>
     */
    private function resolveDemoProductIds(Collection $demoOrderIds): Collection
    {
        $productSaleIds = OrderItem::whereIn('order_id', $demoOrderIds)
            ->pluck('product_sale_id')
            ->unique();

        $productIdsFromOrders = ProductSale::whereIn('id', $productSaleIds)
            ->pluck('product_id')
            ->unique();

        $productIdsFromNames = Product::whereIn('name', self::DEMO_PRODUCT_NAMES)->pluck('id');

        $unresolved = $productIdsFromNames->diff($productIdsFromOrders);

        if ($unresolved->isNotEmpty()) {
            $unresolvedNames = Product::whereIn('id', $unresolved)->pluck('name')->implode('、');

            throw new RuntimeException(
                "デモ商品名と一致するがデモ注文に紐づかない商品が見つかりました({$unresolvedNames})。安全のため削除を中断しました。"
            );
        }

        return $productIdsFromOrders;
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(): array
    {
        return [
            '季節商品' => Category::firstOrCreate(['name' => '季節商品'], ['display_order' => 1]),
            '定番商品' => Category::firstOrCreate(['name' => '定番商品'], ['display_order' => 2]),
        ];
    }

    private function createFarmer(): User
    {
        return User::create([
            'role' => User::ROLE_FARMER,
            'name' => 'デモ農園 ひなたファーム',
            'phone_number' => '00000090000',
            'address' => '愛媛県西条市架空町9-9-9',
            'email' => self::DEMO_FARMER_EMAIL,
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function createBuyers(): Collection
    {
        return collect(self::DEMO_BUYER_EMAILS)->values()->map(function (string $email, int $index) {
            $number = $index + 1;

            return User::create([
                'role' => User::ROLE_BUYER,
                'name' => self::DEMO_BUYER_NAMES[$index],
                'phone_number' => str_pad((string) $number, 11, '0', STR_PAD_LEFT),
                'address' => "愛媛県西条市架空町1-2-{$number}",
                'email' => $email,
                'password' => bcrypt('password'),
            ]);
        });
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, string>  $imagePaths  商品名 => products.image に保存する相対パス
     * @return array<string, ProductSale>
     */
    private function createProducts(array $categories, array $imagePaths): array
    {
        $today = now()->startOfDay();

        $definitions = [
            '朝採れとうもろこし' => [
                'category' => '季節商品', 'unit_label' => '本',
                'description' => '朝一番に収穫した、甘みたっぷりの黄金色のとうもろこし。茹でても焼いても絶品です。',
                'price' => 400, 'stock_quantity' => 15, 'initial_stock' => 19,
                'status' => ProductSale::STATUS_ON_SALE, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            ],
            '完熟ミニトマト' => [
                'category' => '季節商品', 'unit_label' => 'パック',
                'description' => '太陽をたっぷり浴びて育った完熟ミニトマト。皮が薄く、噛むとジュワッと甘みが広がります。',
                'price' => 350, 'stock_quantity' => 2, 'initial_stock' => 6,
                'status' => ProductSale::STATUS_ON_SALE, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            ],
            'ほくほくじゃがいも' => [
                'category' => '定番商品', 'unit_label' => '袋',
                'description' => '土の中でじっくり育てた、ほくほく食感が自慢のじゃがいも。煮物にも揚げ物にもおすすめです。',
                'price' => 300, 'stock_quantity' => 30, 'initial_stock' => 38,
                'status' => ProductSale::STATUS_ON_SALE, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            ],
            '採れたて新玉ねぎ' => [
                'category' => '定番商品', 'unit_label' => '袋',
                'description' => 'みずみずしく辛みが少ない新玉ねぎ。生のままサラダでも、丸ごと煮ても甘みが引き立ちます。',
                'price' => 250, 'stock_quantity' => 0, 'initial_stock' => 2,
                'status' => ProductSale::STATUS_SOLD_OUT, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            ],
            '甘熟紅はるか' => [
                'category' => '季節商品', 'unit_label' => '袋',
                'description' => 'じっくり熟成させた、しっとり甘い紅はるか。焼き芋にすると蜜があふれるほどの甘さです。',
                'price' => 450, 'stock_quantity' => 0, 'initial_stock' => 3,
                'status' => ProductSale::STATUS_ENDED, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_FIXED,
            ],
            '朝採れほうれん草' => [
                'category' => '定番商品', 'unit_label' => '束',
                'description' => 'アクが少なく、栄養満点の朝採れほうれん草。おひたしや炒め物に幅広く使えます。',
                'price' => 280, 'stock_quantity' => 10, 'initial_stock' => 12,
                'status' => ProductSale::STATUS_ON_SALE, 'delivery_date_type' => ProductSale::DELIVERY_DATE_TYPE_AUTO,
            ],
        ];

        $sales = [];

        foreach ($definitions as $name => $definition) {
            $product = Product::create([
                'name' => $name,
                'description' => $definition['description'],
                'category_id' => $categories[$definition['category']]->id,
                'unit_label' => $definition['unit_label'],
                'image' => $imagePaths[$name],
            ]);

            $isEnded = $definition['status'] === ProductSale::STATUS_ENDED;
            $isAuto = $definition['delivery_date_type'] === ProductSale::DELIVERY_DATE_TYPE_AUTO;

            $sales[$name] = ProductSale::create([
                'product_id' => $product->id,
                'price' => $definition['price'],
                'stock_quantity' => $definition['stock_quantity'],
                'initial_stock' => $definition['initial_stock'],
                'sale_start_date' => $today->copy()->subDays(30),
                'sale_end_date' => $isEnded ? $today->copy()->subDays(10) : $today->copy()->addDays(60),
                'delivery_date_from' => $today->copy()->addDays(3)->toDateString(),
                'status' => $definition['status'],
                'is_reservation_open' => ! $isEnded,
                'delivery_date_type' => $definition['delivery_date_type'],
                'earliest_delivery_days' => $isAuto ? 0 : null,
                'order_deadline_time' => $isAuto ? '15:00:00' : null,
                'requires_delivery_confirmation' => true,
            ]);
        }

        return $sales;
    }

    private function createOrder(
        User $buyer,
        ProductSale $sale,
        int $quantity,
        string $orderNumber,
        string $status,
        Carbon $deliveryDate,
        string $paymentStatus,
        string $paymentMethod
    ): Order {
        $subtotal = $sale->price * $quantity;

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $buyer->id,
            'status' => $status,
            'total_amount' => $subtotal,
            'delivery_address' => $buyer->address,
            'delivery_date' => $deliveryDate->toDateString(),
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_sale_id' => $sale->id,
            'product_name' => $sale->product->name,
            'unit_price' => $sale->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ]);

        return $order;
    }

    /**
     * 注文受付(購入者)+新規注文(農家)。OrderPlacementService::createOrderNotifications()と同じ文言。
     */
    private function notifyOrderPlaced(Order $order, User $buyer, User $farmer): void
    {
        Notification::create([
            'user_id' => $buyer->id,
            'type' => '注文受付',
            'title' => 'ご注文を受け付けました',
            'body' => "注文番号 {$order->order_number} を受け付けました。配達予定日は".$order->delivery_date->format('n月j日')."です。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $farmer->id,
            'type' => '新規注文',
            'title' => '新しい注文が入りました',
            'body' => "注文番号 {$order->order_number}({$buyer->name}様)",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    private function createDeliveryConfirmation(Order $order): DeliveryConfirmation
    {
        return DeliveryConfirmation::create([
            'order_id' => $order->id,
            'notified_at' => now(),
        ]);
    }

    /**
     * 配達確認依頼(農家)。DeliveryConfirmationService::createForOrder()と同じ文言。
     */
    private function notifyDeliveryConfirmationRequested(Order $order, User $farmer): void
    {
        Notification::create([
            'user_id' => $farmer->id,
            'type' => '配達確認依頼',
            'title' => '配達予定の確認をお願いします',
            'body' => $order->delivery_date->format('n月j日')."に配達予定の商品があります。内容を確認してください。(注文番号 {$order->order_number})",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 配達確認への回答(配達可能/配達日変更のみ扱う。デモデータでは数量変更/キャンセル相談は使わない)。
     * DeliveryConfirmationController::respond()と同じ更新内容・通知文言。
     */
    private function respondDeliveryConfirmation(DeliveryConfirmation $confirmation, Order $order, string $response, ?string $newDeliveryDate = null): void
    {
        $confirmation->update([
            'response' => $response,
            'new_delivery_date' => $newDeliveryDate,
            'responded_at' => now(),
            'buyer_notified_at' => now(),
        ]);

        if ($response === '配達可能') {
            $order->update(['status' => Order::STATUS_DELIVERY_CONFIRMED]);
        } elseif ($response === '配達日変更') {
            $order->update([
                'delivery_date' => $newDeliveryDate,
                'status' => Order::STATUS_DELIVERY_CHANGED,
            ]);
        }

        $type = $response === '配達日変更' ? '配達予定変更' : '配達予定確認';
        $body = $response === '配達可能'
            ? "注文番号 {$order->order_number} は予定通り".$order->delivery_date->format('n月j日').'に配達予定です。'
            : "注文番号 {$order->order_number} の配達予定日が".$order->delivery_date->format('n月j日').'に変更になりました。';

        Notification::create([
            'user_id' => $order->user_id,
            'type' => $type,
            'title' => $type === '配達予定変更' ? '配達予定日が変更になりました' : '配達予定についてのお知らせ',
            'body' => $body,
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 配達完了。FarmerOrderController::complete()と同じ通知文言。
     */
    private function completeOrder(Order $order): void
    {
        $order->update(['status' => Order::STATUS_DELIVERED]);

        Notification::create([
            'user_id' => $order->user_id,
            'type' => '配達完了',
            'title' => '配達が完了しました',
            'body' => "注文番号 {$order->order_number} の配達が完了しました。ご利用ありがとうございました。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 数量減少。OrderAdjustmentService::reduceQuantity()と同じ更新内容・通知文言。
     */
    private function reduceOrderQuantity(Order $order, User $farmer, int $newQuantity): void
    {
        $item = $order->orderItems()->firstOrFail();
        $previousQuantity = $item->quantity;
        $newSubtotal = $item->unit_price * $newQuantity;

        $item->update(['quantity' => $newQuantity, 'subtotal' => $newSubtotal]);
        $order->update(['total_amount' => $newSubtotal]);

        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'change_type' => OrderAdjustment::CHANGE_TYPE_QUANTITY_REDUCED,
            'previous_status' => $order->status,
            'new_status' => $order->status,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'stock_restored' => $previousQuantity - $newQuantity,
            'confirmed_with_buyer_at' => now(),
            'note' => 'デモデータ:購入者へ電話で確認済み',
            'changed_by' => $farmer->id,
        ]);

        Notification::create([
            'user_id' => $order->user_id,
            'type' => '数量変更確定',
            'title' => 'ご注文の数量を変更しました',
            'body' => "注文番号 {$order->order_number} の数量を{$previousQuantity}から{$newQuantity}に変更しました。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 全キャンセル。OrderAdjustmentService::cancel()と同じ更新内容・通知文言。
     */
    private function cancelOrder(Order $order, User $farmer): void
    {
        $item = $order->orderItems()->firstOrFail();

        $order->update(['status' => Order::STATUS_CANCELLED]);

        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'change_type' => OrderAdjustment::CHANGE_TYPE_CANCELLED,
            'previous_status' => Order::STATUS_RECEIVED,
            'new_status' => Order::STATUS_CANCELLED,
            'previous_quantity' => $item->quantity,
            'new_quantity' => 0,
            'stock_restored' => $item->quantity,
            'confirmed_with_buyer_at' => now(),
            'note' => 'デモデータ:購入者へ電話で確認済み',
            'changed_by' => $farmer->id,
        ]);

        Notification::create([
            'user_id' => $order->user_id,
            'type' => '注文キャンセル',
            'title' => 'ご注文をキャンセルしました',
            'body' => "注文番号 {$order->order_number} をキャンセルしました。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 数量変更相談(未処理)。OrderChangeRequestService::requestQuantityReduction()と同じ記録内容・通知文言。
     */
    private function requestQuantityReductionConsultation(Order $order, User $buyer, User $farmer, int $requestedQuantity): void
    {
        $item = $order->orderItems()->firstOrFail();

        OrderChangeRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'request_type' => OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION,
            'quantity_at_request' => $item->quantity,
            'requested_quantity' => $requestedQuantity,
            'requested_by' => $buyer->id,
        ]);

        $this->notifyChangeRequestConsultation(
            $order,
            $buyer,
            $farmer,
            '数量変更相談',
            "数量を{$item->quantity}から{$requestedQuantity}に変更したいというご相談が届きました。"
        );
    }

    /**
     * キャンセル相談(未処理)。OrderChangeRequestService::requestCancellation()と同じ記録内容・通知文言。
     */
    private function requestCancellationConsultation(Order $order, User $buyer, User $farmer): void
    {
        $item = $order->orderItems()->firstOrFail();

        OrderChangeRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'request_type' => OrderChangeRequest::REQUEST_TYPE_CANCELLATION,
            'quantity_at_request' => $item->quantity,
            'requested_quantity' => null,
            'requested_by' => $buyer->id,
        ]);

        $this->notifyChangeRequestConsultation($order, $buyer, $farmer, '注文キャンセル相談', 'キャンセルのご相談が届きました。');
    }

    /**
     * 相談を受けた農家への通知。OrderChangeRequestService::notifyFarmers()と同じ文言
     * (デモデータでは農家が1件のみのため、通知も1件だけ作成する)。
     */
    private function notifyChangeRequestConsultation(Order $order, User $buyer, User $farmer, string $type, string $summary): void
    {
        Notification::create([
            'user_id' => $farmer->id,
            'type' => $type,
            'title' => 'ご相談が届きました',
            'body' => "注文番号 {$order->order_number} について、{$summary}({$buyer->name}様)",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);
    }

    /**
     * 受付済のまま配達確認待ちの注文4件(O1,O2,O3,O10)。
     * O1・O10は未回答のまま、O2・O3は回答済みにする。
     * さらにO1には未処理の数量変更相談、O10には未処理のキャンセル相談を1件ずつ付ける
     * (F1「要対応(変更相談)」・F3「相談あり」絞り込み・F4「購入者からのご相談」欄の確認用)。
     *
     * @param  Collection<int, User>  $buyers
     * @param  array<string, ProductSale>  $sales
     */
    private function createPendingOrders(Collection $buyers, array $sales, User $farmer): void
    {
        $today = now()->startOfDay();

        // O1: 受付済、未回答の配達確認 + 未処理の数量変更相談(2→1)
        $o1 = $this->createOrder($buyers[0], $sales['朝採れとうもろこし'], 2, 'DEMO-0001', Order::STATUS_RECEIVED, $today->copy()->addDays(3), Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o1, $buyers[0], $farmer);
        $this->createDeliveryConfirmation($o1);
        $this->notifyDeliveryConfirmationRequested($o1, $farmer);
        $this->requestQuantityReductionConsultation($o1, $buyers[0], $farmer, 1);

        // O2: 配達確認済(回答済み・配達可能)
        $o2 = $this->createOrder($buyers[1], $sales['ほくほくじゃがいも'], 3, 'DEMO-0002', Order::STATUS_RECEIVED, $today->copy()->addDays(2), Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o2, $buyers[1], $farmer);
        $confirmation2 = $this->createDeliveryConfirmation($o2);
        $this->notifyDeliveryConfirmationRequested($o2, $farmer);
        $this->respondDeliveryConfirmation($confirmation2, $o2, '配達可能');

        // O3: 配達日変更(回答済み)
        $o3 = $this->createOrder($buyers[2], $sales['完熟ミニトマト'], 1, 'DEMO-0003', Order::STATUS_RECEIVED, $today->copy()->addDays(2), Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o3, $buyers[2], $farmer);
        $confirmation3 = $this->createDeliveryConfirmation($o3);
        $this->notifyDeliveryConfirmationRequested($o3, $farmer);
        $this->respondDeliveryConfirmation($confirmation3, $o3, '配達日変更', $today->copy()->addDays(6)->toDateString());

        // O10: 受付済、未回答の配達確認 + 未処理のキャンセル相談
        $o10 = $this->createOrder($buyers[4], $sales['朝採れとうもろこし'], 1, 'DEMO-0010', Order::STATUS_RECEIVED, $today->copy()->addDays(3), Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o10, $buyers[4], $farmer);
        $this->createDeliveryConfirmation($o10);
        $this->notifyDeliveryConfirmationRequested($o10, $farmer);
        $this->requestCancellationConsultation($o10, $buyers[4], $farmer);
    }

    /**
     * 配達完了済みの注文5件(O4〜O8)。今日・今月・今年それぞれに実績を作る。
     * O8のみ、配達完了前に数量減少(5→3)の履歴を挟む。
     *
     * @param  Collection<int, User>  $buyers
     * @param  array<string, ProductSale>  $sales
     */
    private function createDeliveredOrders(Collection $buyers, array $sales, User $farmer): void
    {
        $today = now()->startOfDay();
        $thisMonthOtherDay = $today->day === 1 ? $today->copy()->day(2) : $today->copy()->day(1);
        $thisYearOtherMonth = $today->month === 1
            ? $today->copy()->setDate($today->year, 2, 15)
            : $today->copy()->setDate($today->year, 1, 15);

        // O4: 今日、paid/cash
        $o4 = $this->createOrder($buyers[3], $sales['朝採れほうれん草'], 2, 'DEMO-0004', Order::STATUS_RECEIVED, $today, Order::PAYMENT_STATUS_PAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o4, $buyers[3], $farmer);
        $this->completeOrder($o4);

        // O5: 今月(今日以外)、paid/card
        $o5 = $this->createOrder($buyers[4], $sales['採れたて新玉ねぎ'], 2, 'DEMO-0005', Order::STATUS_RECEIVED, $thisMonthOtherDay, Order::PAYMENT_STATUS_PAID, Order::PAYMENT_METHOD_CARD);
        $this->notifyOrderPlaced($o5, $buyers[4], $farmer);
        $this->completeOrder($o5);

        // O6: 今年・別月、unpaid/paypay
        $o6 = $this->createOrder($buyers[0], $sales['甘熟紅はるか'], 3, 'DEMO-0006', Order::STATUS_RECEIVED, $thisYearOtherMonth, Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_PAYPAY);
        $this->notifyOrderPlaced($o6, $buyers[0], $farmer);
        $this->completeOrder($o6);

        // O7: 今年・別月、refunded/cash
        $o7 = $this->createOrder($buyers[1], $sales['朝採れとうもろこし'], 1, 'DEMO-0007', Order::STATUS_RECEIVED, $thisYearOtherMonth, Order::PAYMENT_STATUS_REFUNDED, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o7, $buyers[1], $farmer);
        $this->completeOrder($o7);

        // O8: 今日、数量減少(5→3)後に配達完了、paid/card
        $o8 = $this->createOrder($buyers[2], $sales['ほくほくじゃがいも'], 5, 'DEMO-0008', Order::STATUS_RECEIVED, $today, Order::PAYMENT_STATUS_PAID, Order::PAYMENT_METHOD_CARD);
        $this->notifyOrderPlaced($o8, $buyers[2], $farmer);
        $this->reduceOrderQuantity($o8, $farmer, 3);
        $this->completeOrder($o8);
    }

    /**
     * キャンセル済みの注文1件(O9)。
     *
     * @param  Collection<int, User>  $buyers
     * @param  array<string, ProductSale>  $sales
     */
    private function createCancelledOrder(Collection $buyers, array $sales, User $farmer): void
    {
        $today = now()->startOfDay();

        $o9 = $this->createOrder($buyers[3], $sales['完熟ミニトマト'], 1, 'DEMO-0009', Order::STATUS_RECEIVED, $today, Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_METHOD_CASH);
        $this->notifyOrderPlaced($o9, $buyers[3], $farmer);
        $this->cancelOrder($o9, $farmer);
    }

    private function createAnnouncements(): void
    {
        $today = now();

        $announcements = [
            ['title' => '本日、朝採れ野菜を出荷しました', 'body' => '本日収穫した野菜を出荷しました。とうもろこし・ミニトマトが特におすすめです。', 'offsetDays' => 0],
            ['title' => '今週末は農園直売所もオープンしています', 'body' => '土日は農園の直売所も営業しています。ぜひお立ち寄りください。', 'offsetDays' => 1],
            ['title' => 'とうもろこしが今シーズンも入荷しました', 'body' => '甘みたっぷりの朝採れとうもろこしが入荷しました。数量限定です。', 'offsetDays' => 3],
            ['title' => '配達時間帯についてのお願い', 'body' => '配達時間帯のご希望がある場合は、注文時の備考欄にご記入ください。', 'offsetDays' => 5],
            ['title' => '新玉ねぎ、まもなく販売終了です', 'body' => '大変ご好評いただいた新玉ねぎですが、まもなく今シーズンの販売を終了します。', 'offsetDays' => 7],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create([
                'title' => $announcement['title'],
                'body' => $announcement['body'],
                'is_published' => true,
                'published_at' => $today->copy()->subDays($announcement['offsetDays']),
            ]);
        }
    }
}
