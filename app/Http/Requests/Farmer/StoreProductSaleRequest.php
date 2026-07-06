<?php

namespace App\Http\Requests\Farmer;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductSaleRequest extends FormRequest
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
            'price' => ['required', 'integer', 'min:0'],
            // 販売開始時点の在庫数。この値がinitial_stockとstock_quantityの両方の初期値になる
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'sale_start_date' => ['required', 'date'],
            'sale_end_date' => ['required', 'date', 'after_or_equal:sale_start_date'],
            'delivery_date_from' => ['required', 'date'],
            'delivery_date_to' => ['nullable', 'date', 'after_or_equal:delivery_date_from'],
            'delivery_note' => ['nullable', 'string', 'max:255'],
            'is_reservation_open' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.required' => '価格を入力してください。',
            'stock_quantity.required' => '在庫数を入力してください。',
            'sale_start_date.required' => '販売開始日を入力してください。',
            'sale_end_date.required' => '販売終了日を入力してください。',
            'sale_end_date.after_or_equal' => '販売終了日は販売開始日より後の日付にしてください。',
            'delivery_date_from.required' => '配達予定日を入力してください。',
            'delivery_date_to.after_or_equal' => '配達予定日(終了)は配達予定日(開始)より後の日付にしてください。',
        ];
    }
}
