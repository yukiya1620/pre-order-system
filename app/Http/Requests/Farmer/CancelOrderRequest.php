<?php

namespace App\Http\Requests\Farmer;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
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
            'confirmed_with_buyer_at' => ['required', 'accepted'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed_with_buyer_at.required' => '購入者へ電話等で確認済みであることをチェックしてください。',
            'confirmed_with_buyer_at.accepted' => '購入者へ電話等で確認済みであることをチェックしてください。',
            'note.max' => 'メモは255文字以内で入力してください。',
        ];
    }
}
