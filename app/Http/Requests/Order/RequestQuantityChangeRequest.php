<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class RequestQuantityChangeRequest extends FormRequest
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
            'requested_quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'requested_quantity.required' => '希望する数量を入力してください。',
            'requested_quantity.integer' => '希望する数量は数字で入力してください。',
            'requested_quantity.min' => '希望する数量は1以上にしてください。',
        ];
    }
}
