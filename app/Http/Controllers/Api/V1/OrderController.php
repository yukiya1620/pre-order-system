<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Models\Order;
use App\Models\ProductSale;
use App\Models\User;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(private readonly OrderPlacementService $orderPlacementService)
    {
    }

    /**
     * 注文履歴。データは削除せず蓄積し続ける前提で、以下の3パターンに対応する。
     * - パラメータなし: 直近6か月分(履歴ページを開いたときの初期表示)
     * - year のみ: 指定年の全件(去年以前をプルダウンで選んだ場合)
     * - year + month: 指定年月分(今年以内を月のプルダウンで選んだ場合)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->orders()->with(['orderItems', 'pendingChangeRequest']);

        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;

        if ($year !== null && $month !== null) {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } elseif ($year !== null) {
            $query->whereYear('created_at', $year);
        } else {
            $query->where('created_at', '>=', now()->subMonths(6));
        }

        return response()->json(['orders' => $query->latest()->get()]);
    }

    /**
     * 注文詳細。自分の注文以外は見せない。
     */
    public function show(Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        return response()->json(['order' => $order->load(['orderItems', 'pendingChangeRequest'])]);
    }

    /**
     * 「前回と同じ内容で注文」。過去の注文明細と同じ商品・数量で、
     * 今の価格・在庫を使って確認画面用データを作り直す。
     */
    public function reorderPreview(Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        $orderItem = $order->orderItems->first();

        if (! $orderItem) {
            return response()->json([
                'error' => [
                    'code' => 'ORDER_ITEM_NOT_FOUND',
                    'message' => 'この注文には商品明細が存在しないため、再注文できません。',
                ],
            ], 422);
        }

        $productSale = ProductSale::with('product')->find($orderItem->product_sale_id);

        if (! $productSale) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_AVAILABLE',
                    'message' => 'この商品は現在お取り扱いしていません。',
                ],
            ], 422);
        }

        if ($error = $this->orderPlacementService->availabilityError($productSale, $orderItem->quantity)) {
            return response()->json(['error' => $error], 422);
        }

        return response()->json([
            'order_preview' => $this->buildPreview(
                $productSale,
                $orderItem->quantity,
                $order->delivery_time_slot,
                Auth::user()
            ),
            // B7(注文履歴)・注文詳細画面が /orders/confirm への遷移URLを組み立てるために使う。
            // order_previewの中の同名項目に依存させず、明示的な契約として別キーで返す。
            'reorder_params' => [
                'product_sale_id' => $productSale->id,
                'quantity' => $orderItem->quantity,
                'delivery_time_slot' => $order->delivery_time_slot,
            ],
        ]);
    }

    /**
     * 注文確認画面用。在庫を減らさずに、金額と配達予定を検証して返す。
     */
    public function preview(PlaceOrderRequest $request): JsonResponse
    {
        $productSale = ProductSale::with('product')->findOrFail($request->input('product_sale_id'));
        $quantity = (int) $request->input('quantity');

        if ($error = $this->orderPlacementService->availabilityError($productSale, $quantity)) {
            return response()->json(['error' => $error], 422);
        }

        return response()->json([
            'order_preview' => $this->buildPreview(
                $productSale,
                $quantity,
                $request->input('delivery_time_slot'),
                Auth::user()
            ),
        ]);
    }

    /**
     * 注文確定。在庫の排他制御を含む本体の処理はOrderPlacementServiceに任せる
     * (電話注文の代理入力=Farmer\OrderControllerと全く同じ処理を使うため)。
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderPlacementService->place(
                Auth::user(),
                (int) $request->input('product_sale_id'),
                (int) $request->input('quantity'),
                $request->input('delivery_time_slot'),
            );
        } catch (OrderPlacementException $exception) {
            return response()->json([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        return response()->json(['order' => $order->load('orderItems')], 201);
    }

    /**
     * ログイン中の利用者本人の注文かどうかを確認する。
     *
     * @return array<string, string>|null
     */
    private function ownershipError(Order $order): ?array
    {
        if ($order->user_id !== Auth::id()) {
            return [
                'code' => 'FORBIDDEN',
                'message' => 'この注文は閲覧できません。',
            ];
        }

        return null;
    }

    /**
     * 確認画面に表示するためのデータをまとめる。
     *
     * @return array<string, mixed>
     */
    private function buildPreview(ProductSale $productSale, int $quantity, ?string $deliveryTimeSlot, User $user): array
    {
        $subtotal = $productSale->price * $quantity;

        return [
            'product_sale_id' => $productSale->id,
            'product_name' => $productSale->product->name,
            'unit_price' => $productSale->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'delivery_address' => $user->address,
            'delivery_date' => $productSale->delivery_date,
            'delivery_time_slot' => $deliveryTimeSlot,
            'delivery_note' => $productSale->delivery_note,
        ];
    }
}
