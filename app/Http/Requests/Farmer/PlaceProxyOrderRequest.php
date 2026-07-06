<?php

namespace App\Http\Requests\Farmer;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceProxyOrderRequest extends FormRequest
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
            'phone_number' => ['required', 'string', 'max:20'],
            // 未登録の購入者を簡易登録する場合だけ必須(コントローラー側でチェック)
            'name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'product_sale_id' => ['required', 'integer', 'exists:product_sales,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'delivery_time_slot' => ['nullable', 'string', Rule::in(['午前', '午後', '指定なし'])],
            'payment_method' => ['nullable', 'string', Rule::in([
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_CARD,
                Order::PAYMENT_METHOD_PAYPAY,
            ])],
            'payment_status' => ['nullable', 'string', Rule::in([
                Order::PAYMENT_STATUS_UNPAID,
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_REFUNDED,
            ])],
            'proxy_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => '電話番号を入力してください。',
            'product_sale_id.required' => '商品を選択してください。',
            'product_sale_id.exists' => '指定された商品が見つかりません。',
            'quantity.required' => '数量を入力してください。',
            'quantity.min' => '数量は1以上を入力してください。',
        ];
    }
}
