<?php

namespace App\Services;

use App\Exceptions\OrderPlacementException;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 購入者からの「数量変更を相談する」「キャンセルを相談する」を扱う。
 *
 * このサービスは注文・在庫・売上には一切触れない。相談内容を記録し、農家へ通知するだけで、
 * 実際の数量減少・キャンセルの確定は既存のOrderAdjustmentService(F4)が担う。
 * 農家が既存の確定操作を行うと、対応する未処理の相談は自動的に解消される
 * (OrderAdjustmentService側に実装)。
 *
 * ロック順序はOrderAdjustmentServiceと同じ「Order→OrderItem」を踏襲する
 * (在庫を扱わないためProductSaleのロックはない)。共通コード化はあえて行わず、
 * このクラス内に同じ内容を明示的に書く(2026-07-18時点でユーザーと合意した方針)。
 */
class OrderChangeRequestService
{
    public function requestQuantityReduction(Order $order, int $requestedQuantity, User $buyer): OrderChangeRequest
    {
        return DB::transaction(function () use ($order, $requestedQuantity, $buyer) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $orderItem = $this->lockSingleOrderItem($lockedOrder);

            $this->assertOwnership($lockedOrder, $buyer);
            $this->assertAdjustable($lockedOrder);
            $this->assertNoPendingRequest($lockedOrder);

            if ($orderItem->quantity <= 1) {
                throw new OrderPlacementException(
                    '数量が1のため、数量変更のご相談はできません。キャンセルのご相談をご利用ください。',
                    'QUANTITY_ALREADY_MINIMUM'
                );
            }

            if ($requestedQuantity >= $orderItem->quantity || $requestedQuantity < 1) {
                throw new OrderPlacementException(
                    '希望する数量は、現在の数量より少ない1以上の値にしてください。',
                    'INVALID_QUANTITY'
                );
            }

            $changeRequest = OrderChangeRequest::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $orderItem->id,
                'request_type' => OrderChangeRequest::REQUEST_TYPE_QUANTITY_REDUCTION,
                'quantity_at_request' => $orderItem->quantity,
                'requested_quantity' => $requestedQuantity,
                'requested_by' => $buyer->id,
            ]);

            $this->notifyFarmers(
                $lockedOrder,
                $buyer,
                '数量変更相談',
                "数量を{$orderItem->quantity}から{$requestedQuantity}に変更したいというご相談が届きました。"
            );

            return $changeRequest->fresh();
        });
    }

    public function requestCancellation(Order $order, User $buyer): OrderChangeRequest
    {
        return DB::transaction(function () use ($order, $buyer) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $orderItem = $this->lockSingleOrderItem($lockedOrder);

            $this->assertOwnership($lockedOrder, $buyer);
            $this->assertAdjustable($lockedOrder);
            $this->assertNoPendingRequest($lockedOrder);

            $changeRequest = OrderChangeRequest::create([
                'order_id' => $lockedOrder->id,
                'order_item_id' => $orderItem->id,
                'request_type' => OrderChangeRequest::REQUEST_TYPE_CANCELLATION,
                'quantity_at_request' => $orderItem->quantity,
                'requested_quantity' => null,
                'requested_by' => $buyer->id,
            ]);

            $this->notifyFarmers($lockedOrder, $buyer, '注文キャンセル相談', 'キャンセルのご相談が届きました。');

            return $changeRequest->fresh();
        });
    }

    /**
     * 注文・在庫・通知(購入者への「相談終了」を除く)には一切触れず、相談だけを
     * 「変更せず終了」で解消する。
     *
     * ロック順序は「Order→OrderChangeRequest」。OrderAdjustmentServiceの数量減少・
     * キャンセル確定(こちらも必ずOrder行を先にロックする)と同じ注文行を入口にすることで、
     * 双方の処理が同時に来ても必ず直列に実行される。
     */
    public function resolveWithoutChange(OrderChangeRequest $changeRequest, ?string $note, User $farmer): OrderChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $note, $farmer) {
            $lockedOrder = Order::where('id', $changeRequest->order_id)->lockForUpdate()->firstOrFail();
            $lockedRequest = OrderChangeRequest::where('id', $changeRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->order_id !== $lockedOrder->id) {
                throw new OrderPlacementException('注文情報が一致しません。', 'ORDER_MISMATCH');
            }

            if ($lockedRequest->resolved_at !== null) {
                throw new OrderPlacementException('この相談はすでに対応済みです。', 'ALREADY_RESOLVED');
            }

            $lockedRequest->update([
                'resolution_type' => OrderChangeRequest::RESOLUTION_TYPE_NO_CHANGE,
                'resolved_by' => $farmer->id,
                'resolution_note' => $note,
                'resolved_at' => now(),
            ]);

            Notification::create([
                'user_id' => $lockedOrder->user_id,
                'type' => '相談終了',
                'title' => 'ご相談への対応',
                'body' => $this->buildResolvedWithoutChangeBody($lockedOrder, $note),
                'related_order_id' => $lockedOrder->id,
                'is_read' => false,
            ]);

            return $lockedRequest->fresh();
        });
    }

    private function buildResolvedWithoutChangeBody(Order $order, ?string $note): string
    {
        $body = "注文番号 {$order->order_number} についてのご相談を確認しましたが、今回は注文内容の変更は行いません。";

        if ($note) {
            $body .= "\n".$note;
        }

        return $body;
    }

    private function assertOwnership(Order $order, User $buyer): void
    {
        if ($order->user_id !== $buyer->id) {
            throw new OrderPlacementException('この注文は操作できません。', 'FORBIDDEN');
        }
    }

    private function assertAdjustable(Order $order): void
    {
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            throw new OrderPlacementException(
                '配達完了・キャンセル済みの注文についてはご相談いただけません。',
                'ORDER_NOT_ADJUSTABLE'
            );
        }
    }

    private function assertNoPendingRequest(Order $order): void
    {
        $exists = OrderChangeRequest::where('order_id', $order->id)->whereNull('resolved_at')->exists();

        if ($exists) {
            throw new OrderPlacementException(
                'この注文には、すでに対応中のご相談があります。',
                'REQUEST_ALREADY_PENDING'
            );
        }
    }

    /**
     * この仕組みは1注文1明細を前提とする(OrderAdjustmentServiceと同じ制約)。
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

    private function notifyFarmers(Order $order, User $buyer, string $type, string $summary): void
    {
        $farmers = User::where('role', User::ROLE_FARMER)->get();

        foreach ($farmers as $farmer) {
            Notification::create([
                'user_id' => $farmer->id,
                'type' => $type,
                'title' => 'ご相談が届きました',
                'body' => "注文番号 {$order->order_number} について、{$summary}({$buyer->name}様)",
                'related_order_id' => $order->id,
                'is_read' => false,
            ]);
        }
    }
}
