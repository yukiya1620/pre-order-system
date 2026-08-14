<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 農家向け売上集計。
 * 「確定売上」= status=配達完了の注文のみ。「予定売上」= まだ配達完了していないがキャンセルもされていない注文。
 * どちらも delivery_date(配達日)を基準日にする(注文日ではない。生鮮食品は配達した日を売上として扱うため)。
 */
class SalesService
{
    private const PENDING_STATUSES = [
        Order::STATUS_RECEIVED,
        Order::STATUS_DELIVERY_CONFIRMED,
        Order::STATUS_DELIVERY_CHANGED,
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = now()->startOfDay();

        return [
            'confirmed' => [
                'today' => $this->aggregate(
                    $this->confirmedQuery()->whereDate('delivery_date', $today)
                ),
                'this_month' => $this->aggregate(
                    $this->confirmedQuery()->whereYear('delivery_date', $today->year)->whereMonth('delivery_date', $today->month)
                ),
                'this_year' => $this->aggregate(
                    $this->confirmedQuery()->whereYear('delivery_date', $today->year)
                ),
            ],
            'pending' => $this->aggregate($this->pendingQuery()),
            'payment_status_breakdown' => $this->paymentStatusBreakdown(),
            'payment_method_breakdown' => $this->paymentMethodBreakdown(),
        ];
    }

    /**
     * 商品別の売上金額・販売数量(確定売上のみ)。period: today / month / year
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function salesByProduct(string $period): Collection
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_sales', 'product_sales.id', '=', 'order_items.product_sale_id')
            ->join('products', 'products.id', '=', 'product_sales.product_id')
            ->where('orders.status', Order::STATUS_DELIVERED);

        $this->applyPeriod($query, $period);

        $rows = $query
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_amount')
            ->get([
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(order_items.subtotal) as total_amount'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
            ]);

        // SUM()の返り値はSQLiteではint、MySQLでは文字列型になるなど、DBエンジンによって
        // PHP側の型が変わる(aggregate()の(int)キャストと同じ理由)。APIレスポンスの型を
        // DBエンジンに依存させないよう、ここで明示的にintへ揃える。
        return $rows->map(function ($row) {
            $row->total_amount = (int) $row->total_amount;
            $row->total_quantity = (int) $row->total_quantity;

            return $row;
        });
    }

    private function confirmedQuery(): Builder
    {
        return Order::query()->where('status', Order::STATUS_DELIVERED);
    }

    private function pendingQuery(): Builder
    {
        return Order::query()->whereIn('status', self::PENDING_STATUSES);
    }

    /**
     * @return array<string, int>
     */
    private function aggregate(Builder $query): array
    {
        return [
            'total_amount' => (int) $query->sum('total_amount'),
            'order_count' => $query->count(),
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function paymentStatusBreakdown(): array
    {
        return [
            Order::PAYMENT_STATUS_PAID => $this->aggregate(
                $this->confirmedQuery()->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ),
            Order::PAYMENT_STATUS_UNPAID => $this->aggregate(
                $this->confirmedQuery()->where('payment_status', Order::PAYMENT_STATUS_UNPAID)
            ),
            Order::PAYMENT_STATUS_REFUNDED => $this->aggregate(
                $this->confirmedQuery()->where('payment_status', Order::PAYMENT_STATUS_REFUNDED)
            ),
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function paymentMethodBreakdown(): array
    {
        return [
            Order::PAYMENT_METHOD_CASH => $this->aggregate(
                $this->confirmedQuery()->where('payment_method', Order::PAYMENT_METHOD_CASH)
            ),
            Order::PAYMENT_METHOD_CARD => $this->aggregate(
                $this->confirmedQuery()->where('payment_method', Order::PAYMENT_METHOD_CARD)
            ),
            Order::PAYMENT_METHOD_PAYPAY => $this->aggregate(
                $this->confirmedQuery()->where('payment_method', Order::PAYMENT_METHOD_PAYPAY)
            ),
        ];
    }

    private function applyPeriod(Builder $query, string $period): void
    {
        $today = now()->startOfDay();

        match ($period) {
            'today' => $query->whereDate('orders.delivery_date', $today),
            'year' => $query->whereYear('orders.delivery_date', $today->year),
            default => $query->whereYear('orders.delivery_date', $today->year)->whereMonth('orders.delivery_date', $today->month),
        };
    }
}
