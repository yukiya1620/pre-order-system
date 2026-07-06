<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
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
            'product_sale_id' => ['required', 'integer', 'exists:product_sales,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'delivery_time_slot' => ['nullable', 'string', Rule::in(['午前', '午後', '指定なし'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_sale_id.required' => '商品を選択してください。',
            'product_sale_id.exists' => '指定された商品が見つかりません。',
            'quantity.required' => '数量を入力してください。',
            'quantity.min' => '数量は1以上を入力してください。',
        ];
    }
}
