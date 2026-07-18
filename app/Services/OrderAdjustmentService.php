<?php

namespace App\Services;

use App\Exceptions\OrderPlacementException;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F4(農家向け注文詳細)から、購入者へ電話等で確認が取れたあとに確定する
 * 「数量を減らす」「注文全体をキャンセルする」を1か所にまとめる(設計書3.4.1)。
 *
 * 二重実行対策について: 専用の冪等キーは使わず、ロック取得後に注文の最新状態を
 * 読み直して条件を再検証することで、同じ操作を2回実行しても2回目は必ず
 * 条件不一致(配達完了・キャンセル済み等)で拒否されるようにしている。
 */
class OrderAdjustmentService
{
    /**
     * 数量を減らす。金額は現在の商品価格ではなく、注文時にorder_itemsへ保存した
     * unit_price(スナップショット)から再計算する。
     */
    public function reduceQuantity(Order $order, int $newQuantity, ?string $note, User $farmer): Order
    {
        return DB::transaction(function () use ($order, $newQuantity, $note, $farmer) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $orderItem = $this->lockSingleOrderItem($lockedOrder);

            if ($error = $this->notAdjustableError($lockedOrder)) {
                throw new OrderPlacementException($error['message'], $error['code']);
            }

            if ($orderItem->quantity <= 1) {
                throw new OrderPlacementException(
                    '数量が1のため、これ以上は減らせません。キャンセルをご利用ください。',
                    'QUANTITY_ALREADY_MINIMUM'
                );
            }

            if ($newQuantity >= $orderItem->quantity || $newQuantity < 1) {
                throw new OrderPlacementException(
                    '新しい数量は、現在の数量より少ない1以上の値にしてください。',
                    'INVALID_QUANTITY'
                );
            }

            $productSale = ProductSale::where('id', $orderItem->product_sale_id)->lockForUpdate()->firstOrFail();

            $diff = $orderItem->quantity - $newQuantity;
            $previousQuantity = $orderItem->quantity;
            $previousStatus = $lockedOrder->status;

            $this->restoreStock($productSale, $diff);

            $orderItem->update([
                'quantity' => $newQuantity,
                'subtotal' => $orderItem->unit_price * $newQuantity,
            ]);

            $lockedOrder->update([
                'total_amount' => $lockedOrder->orderItems()->sum('subtotal'),
            ]);

            $adjustment = OrderAdjustment::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $orderItem->id,
                'change_type' => OrderAdjustment::CHANGE_TYPE_QUANTITY_REDUCED,
                'previous_status' => $previousStatus,
                'new_status' => $lockedOrder->status,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $newQuantity,
                'stock_restored' => $diff,
                'confirmed_with_buyer_at' => now(),
                'note' => $note,
                'changed_by' => $farmer->id,
            ]);

            Notification::create([
                'user_id' => $lockedOrder->user_id,
                'type' => '数量変更確定',
                'title' => 'ご注文の数量を変更しました',
                'body' => "注文番号 {$lockedOrder->order_number} の数量を{$previousQuantity}から{$newQuantity}に変更しました。",
                'related_order_id' => $lockedOrder->id,
                'is_read' => false,
            ]);

            $this->resolvePendingChangeRequest($lockedOrder, $adjustment, $farmer);

            return $lockedOrder->fresh(['orderItems', 'user']);
        });
    }

    /**
     * 注文全体をキャンセルする。注文時の数量・小計・合計金額(order_items/total_amount)は
     * 変更しない。実際にキャンセルした数量・在庫復元量はorder_adjustmentsに記録する。
     */
    public function cancel(Order $order, ?string $note, User $farmer): Order
    {
        return DB::transaction(function () use ($order, $note, $farmer) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $orderItem = $this->lockSingleOrderItem($lockedOrder);

            if ($error = $this->notAdjustableError($lockedOrder)) {
                throw new OrderPlacementException($error['message'], $error['code']);
            }

            $productSale = ProductSale::where('id', $orderItem->product_sale_id)->lockForUpdate()->firstOrFail();

            $previousStatus = $lockedOrder->status;
            $quantity = $orderItem->quantity;

            $this->restoreStock($productSale, $quantity);

            $lockedOrder->update(['status' => Order::STATUS_CANCELLED]);

            $adjustment = OrderAdjustment::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $orderItem->id,
                'change_type' => OrderAdjustment::CHANGE_TYPE_CANCELLED,
                'previous_status' => $previousStatus,
                'new_status' => Order::STATUS_CANCELLED,
                'previous_quantity' => $quantity,
                'new_quantity' => 0,
                'stock_restored' => $quantity,
                'confirmed_with_buyer_at' => now(),
                'note' => $note,
                'changed_by' => $farmer->id,
            ]);

            Notification::create([
                'user_id' => $lockedOrder->user_id,
                'type' => '注文キャンセル',
                'title' => 'ご注文をキャンセルしました',
                'body' => "注文番号 {$lockedOrder->order_number} をキャンセルしました。",
                'related_order_id' => $lockedOrder->id,
                'is_read' => false,
            ]);

            $this->resolvePendingChangeRequest($lockedOrder, $adjustment, $farmer);

            return $lockedOrder->fresh(['orderItems', 'user']);
        });
    }

    /**
     * この仕組みは1注文1明細を前提とする(現行のB4/F10ともに複数商品の注文は作られない)。
     * 想定外に複数明細がある注文には安全のため対応しない。
     */
    private function lockSingleOrderItem(Order $order): OrderItem
    {
        $items = OrderItem::where('order_id', $order->id)->lockForUpdate()->get();

        if ($items->count() !== 1) {
            throw new OrderPlacementException(
                '複数の商品を含む注文には対応していません。',
                'MULTIPLE_ITEMS_NOT_SUPPORTED'
            );
        }

        return $items->first();
    }

    /**
     * @return array<string, string>|null
     */
    private function notAdjustableError(Order $order): ?array
    {
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            return [
                'code' => 'ORDER_NOT_ADJUSTABLE',
                'message' => '配達完了・キャンセル済みの注文は変更できません。',
            ];
        }

        return null;
    }

    /**
     * 在庫を戻す。0から1以上に戻り、かつ現在「売り切れ」の場合に限り「販売中」に戻す
     * (準備中・販売終了からは戻さない)。is_reservation_open・is_archivedは変更しない。
     */
    private function restoreStock(ProductSale $productSale, int $quantity): void
    {
        $productSale->increment('stock_quantity', $quantity);

        if ($productSale->stock_quantity > 0 && $productSale->status === ProductSale::STATUS_SOLD_OUT) {
            $productSale->update(['status' => ProductSale::STATUS_ON_SALE]);
        }
    }

    /**
     * この注文に購入者からの未処理相談(OrderChangeRequest)があれば、
     * 今回の数量減少・キャンセル確定によって自動的に解消する。
     * 相談の種別(数量変更/キャンセル)と実際の確定内容が一致しなくても解消扱いにする
     * (例: 数量変更を相談されたが全キャンセルで対応した場合も、相談自体は解消済みとする)。
     *
     * 呼び出し元(reduceQuantity/cancel)が既にOrder行をlockForUpdate()済みのため、
     * このメソッドはさらにOrderChangeRequest行をlockForUpdate()するだけでよい。
     * 購入者側のresolveWithoutChange()も必ずOrder行を先にロックするため、
     * 双方の処理が同時に来ても直列に実行される。
     */
    private function resolvePendingChangeRequest(Order $order, OrderAdjustment $adjustment, User $farmer): void
    {
        $pendingRequest = OrderChangeRequest::where('order_id', $order->id)
            ->whereNull('resolved_at')
            ->lockForUpdate()
            ->first();

        if (! $pendingRequest) {
            return;
        }

        $pendingRequest->update([
            'resolution_type' => OrderChangeRequest::RESOLUTION_TYPE_ADJUSTMENT_APPLIED,
            'resolved_by' => $farmer->id,
            'resolved_order_adjustment_id' => $adjustment->id,
            'resolved_at' => now(),
        ]);
    }
}
