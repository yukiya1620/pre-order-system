<?php

namespace App\Http\Requests\Farmer;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'string', Rule::in([
                Order::PAYMENT_STATUS_UNPAID,
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_REFUNDED,
            ])],
            'payment_method' => ['nullable', 'string', Rule::in([
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_CARD,
                Order::PAYMENT_METHOD_PAYPAY,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_status.required' => '支払い状態を選択してください。',
            'payment_status.in' => '支払い状態は「unpaid」「paid」「refunded」のいずれかを指定してください。',
            'payment_method.in' => '支払い方法は「cash」「card」「paypay」のいずれかを指定してください。',
        ];
    }
}
