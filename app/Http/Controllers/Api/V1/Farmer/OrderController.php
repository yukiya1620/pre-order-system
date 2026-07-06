<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\PlaceProxyOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderPlacementService $orderPlacementService)
    {
    }

    /**
     * 電話注文の代理入力。電話番号で既存購入者を探し、いなければその場で簡易登録する
     * (SmsAuthController::verify()と同じ「見つからなければ登録」のパターン)。
     * 注文本体の処理は、通常注文と全く同じOrderPlacementServiceに任せる。
     */
    public function store(PlaceProxyOrderRequest $request): JsonResponse
    {
        $phoneNumber = $request->string('phone_number')->toString();
        $buyer = User::where('phone_number', $phoneNumber)->first();

        if (! $buyer) {
            if (blank($request->input('name')) || blank($request->input('address'))) {
                return response()->json([
                    'error' => [
                        'code' => 'REGISTRATION_INFO_REQUIRED',
                        'message' => '初めての購入者です。お名前とご住所を入力してください。',
                    ],
                ], 422);
            }

            $buyer = User::create([
                'role' => User::ROLE_BUYER,
                'name' => $request->input('name'),
                'phone_number' => $phoneNumber,
                'address' => $request->input('address'),
                'is_active' => true,
            ]);
        }

        try {
            $order = $this->orderPlacementService->place(
                $buyer,
                (int) $request->input('product_sale_id'),
                (int) $request->input('quantity'),
                $request->input('delivery_time_slot'),
                [
                    'is_proxy_order' => true,
                    'proxy_note' => $request->input('proxy_note'),
                    'payment_method' => $request->input('payment_method', Order::PAYMENT_METHOD_CASH),
                    'payment_status' => $request->input('payment_status', Order::PAYMENT_STATUS_UNPAID),
                ]
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
}
