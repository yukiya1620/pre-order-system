<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    /**
     * 今日・今月・今年の確定売上と、配達前の予定売上、支払い状況・支払い方法別の内訳
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->salesService->summary());
    }

    /**
     * 商品別売上(期間指定、確定売上のみ)
     */
    public function byProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:today,month,year',
        ]);

        $period = $validated['period'] ?? 'month';

        return response()->json([
            'period' => $period,
            'products' => $this->salesService->salesByProduct($period),
        ]);
    }
}
