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
     * @param  array<string, mixed>  $orderAttributes  is_proxy_order/proxy_note/payment_method/payment_status など、呼び出し元ごとに異なる項目の上書き
     */
    public function place(User $buyer, int $productSaleId, int $quantity, ?string $deliveryTimeSlot, array $orderAttributes = []): Order
    {
        return DB::transaction(function () use ($buyer, $productSaleId, $quantity, $deliveryTimeSlot, $orderAttributes) {
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
                'delivery_date' => $productSale->resolveDeliveryDate(),
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
