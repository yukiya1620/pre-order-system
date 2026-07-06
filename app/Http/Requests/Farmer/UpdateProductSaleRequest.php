<?php

namespace App\Http\Requests\Farmer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 編集は「価格・在庫・期間・予約受付可否」だけを対象にする(設計書4.4)。
     * すべて省略可能で、送られてきた項目だけを更新する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'integer', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'sale_start_date' => ['sometimes', 'date'],
            'sale_end_date' => ['sometimes', 'date', 'after_or_equal:sale_start_date'],
            'delivery_date_from' => ['sometimes', 'date'],
            'delivery_date_to' => ['nullable', 'date', 'after_or_equal:delivery_date_from'],
            'delivery_note' => ['sometimes', 'string', 'max:255'],
            'is_reservation_open' => ['sometimes', 'boolean'],
        ];
    }
}
