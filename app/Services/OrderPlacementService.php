<?php

namespace App\Services;

use App\Exceptions\OrderPlacementException;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 注文確定処理(在庫の排他制御込み)を1か所にまとめる。
 * 通常注文(OrderController)・電話注文の代理入力(Farmer\OrderController)の
 * どちらから呼んでも、在庫チェック・行ロックの仕組みが食い違わないようにするための共通クラス。
 */
class OrderPlacementService
{
    public function __construct(private readonly DeliveryConfirmationService $deliveryConfirmationService)
    {
    }

    /**
     * 設計書3.4の手順(行ロック→在庫確認→減算→注文作成→通知作成)通りに、
     * 1つのDBトランザクションの中で注文を確定する。
     *
     * @param  string|null  $deliveryDate  配達予定期間から購入者が選んだ日付(YYYY-MM-DD)。選択不要な商品では無視される
     * @param  array<string, mixed>  $orderAttributes  is_proxy_order/proxy_note/payment_method/payment_status など、呼び出し元ごとに異なる項目の上書き
     * @param  bool  $requireDeliveryDateSelection  選択必須の商品で$deliveryDateが未指定のときにエラーにするか。
     *                                               電話注文の代理入力(F10)は日付選択UIを持たないため、falseを渡して
     *                                               従来通り自動決定(delivery_date_from)にフォールバックさせる
     */
    public function place(User $buyer, int $productSaleId, int $quantity, ?string $deliveryTimeSlot, ?string $deliveryDate = null, array $orderAttributes = [], bool $requireDeliveryDateSelection = true): Order
    {
        return DB::transaction(function () use ($buyer, $productSaleId, $quantity, $deliveryTimeSlot, $deliveryDate, $orderAttributes, $requireDeliveryDateSelection) {
            // lockForUpdate()で対象行に「行ロック」をかける。
            // このロックが外れるまで、他のリクエストは同じ行の続きの処理を待たされるため、
            // 同時に注文しても在庫がマイナスになることはない。
            $productSale = ProductSale::where('id', $productSaleId)
                ->lockForUpdate()
                ->with('product')
                ->firstOrFail();

            if ($error = $this->availabilityError($productSale, $quantity)) {
                throw new OrderPlacementException($error['message'], $error['code']);
            }

            // ロック済みの最新データに対して検証するため、画面の値を改ざんされても
            // 期間外の日付・不正な形式では注文できない(在庫チェックと同じ考え方)。
            $resolvedDeliveryDate = $this->resolveOrderDeliveryDate($productSale, $deliveryDate, $requireDeliveryDateSelection);

            $productSale->decrement('stock_quantity', $quantity);

            if ($productSale->stock_quantity === 0) {
                $productSale->update(['status' => ProductSale::STATUS_SOLD_OUT]);
            }

            $subtotal = $productSale->price * $quantity;

            $order = Order::create(array_merge([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $buyer->id,
                'status' => Order::STATUS_RECEIVED,
                'total_amount' => $subtotal,
                'delivery_address' => $buyer->address,
                'delivery_date' => $resolvedDeliveryDate,
                'delivery_time_slot' => $deliveryTimeSlot,
                'is_proxy_order' => false,
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            ], $orderAttributes));

            $order->orderItems()->create([
                'product_sale_id' => $productSale->id,
                'product_name' => $productSale->product->name,
                'unit_price' => $productSale->price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            $this->createOrderNotifications($order, $buyer);

            // 設計書3.5: 当日・翌日配達(auto)は3日前バッチでは間に合わないため、注文確定直後に配達確認を作る
            if ($productSale->requires_delivery_confirmation && $productSale->delivery_date_type === ProductSale::DELIVERY_DATE_TYPE_AUTO) {
                $this->deliveryConfirmationService->createForOrder($order);
            }

            return $order;
        });
    }

    /**
     * 在庫が足りているか、販売中かどうかを確認する(読み取り専用。行ロックは呼び出し側の責務)。
     *
     * @return array<string, string>|null
     */
    public function availabilityError(ProductSale $productSale, int $quantity): ?array
    {
        if ($productSale->product->is_archived) {
            return [
                'code' => 'NOT_AVAILABLE',
                'message' => '現在この商品は注文を受け付けていません。',
            ];
        }

        if ($productSale->status !== ProductSale::STATUS_ON_SALE || ! $productSale->is_reservation_open) {
            return [
                'code' => 'NOT_AVAILABLE',
                'message' => '現在この商品は注文を受け付けていません。',
            ];
        }

        if ($productSale->stock_quantity < $quantity) {
            return [
                'code' => 'OUT_OF_STOCK',
                'message' => '申し訳ありません。売り切れました。',
            ];
        }

        return null;
    }

    /**
     * 実際に注文へ設定する配達予定日を決定する(preview・store共通の唯一の判定箇所)。
     * 選択不要な商品(単日配達・当日/翌日配達)では、渡された$deliveryDateを一切見ずに
     * 常にresolveDeliveryDate()を返す(=既存の挙動に一切影響を与えない)。
     * 選択必須な商品では、形式(YYYY-MM-DD)・必須・期間内であることをすべてここで検証する。
     *
     * @throws OrderPlacementException
     */
    public function resolveOrderDeliveryDate(ProductSale $productSale, ?string $deliveryDate, bool $requireSelection = true): \Illuminate\Support\Carbon
    {
        if (! $productSale->requiresDeliveryDateSelection()) {
            return $productSale->resolveDeliveryDate();
        }

        if (blank($deliveryDate)) {
            if (! $requireSelection) {
                return $productSale->resolveDeliveryDate();
            }

            throw new OrderPlacementException('配達予定日を選択してください。', 'DELIVERY_DATE_REQUIRED');
        }

        // date_format:Y-m-dと同じ考え方で、桁数まで厳密に一致する形式だけを受け付ける
        // (createFromFormatは"2026-2-3"のような桁不足も緩く解釈してしまうため、先に正規表現で弾く)。
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate)) {
            throw new OrderPlacementException('配達予定日の形式が正しくありません。', 'INVALID_DELIVERY_DATE');
        }

        try {
            $parsedDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $deliveryDate)->startOfDay();
        } catch (\Throwable $exception) {
            throw new OrderPlacementException('配達予定日の形式が正しくありません。', 'INVALID_DELIVERY_DATE');
        }

        // createFromFormatは"2026-02-30"のような暦上存在しない日付を3月2日などへ繰り上げて
        // 解釈してしまう(例外を投げない)ため、フォーマットし直して元の文字列と一致するかで弾く。
        if ($parsedDate->format('Y-m-d') !== $deliveryDate) {
            throw new OrderPlacementException('配達予定日の形式が正しくありません。', 'INVALID_DELIVERY_DATE');
        }

        if (! $productSale->isDeliveryDateWithinRange($parsedDate)) {
            throw new OrderPlacementException('配達予定日は配達予定期間内から選択してください。', 'DELIVERY_DATE_OUT_OF_RANGE');
        }

        return $parsedDate;
    }

    /**
     * 注文番号を発番する(例: 20260704-0012)。当日の注文件数+1を4桁で採番する。
     */
    private function generateOrderNumber(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = Order::whereDate('created_at', today())->count() + 1;

        return $datePart.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 注文受付を購入者へ、新規注文を農家へ通知する。
     */
    private function createOrderNotifications(Order $order, User $buyer): void
    {
        Notification::create([
            'user_id' => $buyer->id,
            'type' => '注文受付',
            'title' => 'ご注文を受け付けました',
            'body' => "注文番号 {$order->order_number} を受け付けました。配達予定日は".$order->delivery_date->format('n月j日')."です。",
            'related_order_id' => $order->id,
            'is_read' => false,
        ]);

        $farmers = User::where('role', User::ROLE_FARMER)->get();

        foreach ($farmers as $farmer) {
            Notification::create([
                'user_id' => $farmer->id,
                'type' => '新規注文',
                'title' => '新しい注文が入りました',
                'body' => "注文番号 {$order->order_number}({$buyer->name}様)",
                'related_order_id' => $order->id,
                'is_read' => false,
            ]);
        }
    }
}
