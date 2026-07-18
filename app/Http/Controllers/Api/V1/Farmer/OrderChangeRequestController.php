<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\ResolveOrderChangeRequestWithoutChangeRequest;
use App\Models\OrderChangeRequest;
use App\Services\OrderChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderChangeRequestController extends Controller
{
    public function __construct(private readonly OrderChangeRequestService $orderChangeRequestService)
    {
    }

    /**
     * 未処理の相談件数。F1ホームの「要対応(変更相談)」表示用。
     * 一覧を取得して.lengthを数えるのではなく、件数だけをCOUNT(*)で返す。
     */
    public function count(): JsonResponse
    {
        return response()->json([
            'count' => OrderChangeRequest::whereNull('resolved_at')->count(),
        ]);
    }

    /**
     * 相談を「変更せず終了する」。注文・在庫・売上には一切触れない。
     */
    public function resolveWithoutChange(ResolveOrderChangeRequestWithoutChangeRequest $request, OrderChangeRequest $orderChangeRequest): JsonResponse
    {
        try {
            $changeRequest = $this->orderChangeRequestService->resolveWithoutChange(
                $orderChangeRequest,
                $request->input('note'),
                Auth::user(),
            );
        } catch (OrderPlacementException $exception) {
            return response()->json([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        return response()->json(['order_change_request' => $changeRequest]);
    }
}
