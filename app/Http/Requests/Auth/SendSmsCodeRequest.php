<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendSmsCodeRequest extends FormRequest
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
            // ハイフン無しの数字のみ(例: 09012345678)
            'phone_number' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => '電話番号を入力してください。',
            'phone_number.regex' => '電話番号は「0」から始まる10〜11桁の数字で入力してください(ハイフンなし)。',
        ];
    }
}
