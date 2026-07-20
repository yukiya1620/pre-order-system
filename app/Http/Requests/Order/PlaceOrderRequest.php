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
            // 配達予定期間からの選択が必須かどうか(選択不要な商品での必須判定)は
            // 商品ごとに異なるためここでは判定できず、OrderPlacementService::resolveOrderDeliveryDate()
            // が唯一の判定・検証箇所となる。ここでは形式だけを確認する。
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
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
            'delivery_date.date_format' => '配達予定日の形式が正しくありません。',
        ];
    }
}
