<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\RequestCancellationRequest;
use App\Http\Requests\Order\RequestQuantityChangeRequest;
use App\Models\Order;
use App\Services\OrderChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * 購入者からの「数量変更を相談する」「キャンセルを相談する」を扱う専用Controller。
 * 既存のOrderController(注文本体のCRUD)にはメソッドを追加しない(2026-07-18時点でユーザーと合意した方針)。
 */
class OrderChangeRequestController extends Controller
{
    public function __construct(private readonly OrderChangeRequestService $orderChangeRequestService)
    {
    }

    public function requestQuantityChange(RequestQuantityChangeRequest $request, Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        try {
            $changeRequest = $this->orderChangeRequestService->requestQuantityReduction(
                $order,
                (int) $request->input('requested_quantity'),
                Auth::user(),
            );
        } catch (OrderPlacementException $exception) {
            return $this->errorResponse($exception);
        }

        return response()->json(['order_change_request' => $changeRequest], 201);
    }

    public function requestCancellation(RequestCancellationRequest $request, Order $order): JsonResponse
    {
        if ($error = $this->ownershipError($order)) {
            return response()->json(['error' => $error], 403);
        }

        try {
            $changeRequest = $this->orderChangeRequestService->requestCancellation($order, Auth::user());
        } catch (OrderPlacementException $exception) {
            return $this->errorResponse($exception);
        }

        return response()->json(['order_change_request' => $changeRequest], 201);
    }

    private function errorResponse(OrderPlacementException $exception): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ],
        ], 422);
    }

    /**
     * ログイン中の利用者本人の注文かどうかを確認する(OrderControllerと同じ考え方)。
     *
     * @return array<string, string>|null
     */
    private function ownershipError(Order $order): ?array
    {
        if ($order->user_id !== Auth::id()) {
            return [
                'code' => 'FORBIDDEN',
                'message' => 'この注文は操作できません。',
            ];
        }

        return null;
    }
}
