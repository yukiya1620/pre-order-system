<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifySmsCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * name/addressは「初めての利用者」の場合にだけ必要になる。
     * (電話番号が既存ユーザーのものかはコントローラー側でDBを見て判断するため、
     *  ここでは形式だけをチェックする)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
            'code' => ['required', 'digits:6'],
            'name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:254'],
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
            'code.required' => '認証コードを入力してください。',
            'code.digits' => '認証コードは6桁の数字で入力してください。',
            'name.max' => 'お名前は100文字以内で入力してください。',
            'address.max' => 'ご住所は255文字以内で入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
        ];
    }
}
