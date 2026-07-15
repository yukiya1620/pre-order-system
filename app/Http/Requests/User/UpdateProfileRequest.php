<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:254', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'notify_by_email' => ['sometimes', 'boolean'],
            'notify_by_sms' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'お名前を入力してください。',
            'address.required' => 'ご住所を入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'email.unique' => 'このメールアドレスはすでに使われています。',
        ];
    }

    /**
     * メール通知を有効にする場合、メールアドレスが(このリクエストで送られた値も含めて)
     * 登録されていることを確認する。email自体は任意項目なので、通常のrules()だけでは表現できない。
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('notify_by_email')) {
                return;
            }

            $effectiveEmail = $this->has('email') ? $this->input('email') : $this->user()->email;

            if (blank($effectiveEmail)) {
                $validator->errors()->add('notify_by_email', 'メール通知を利用するには、メールアドレスを登録してください。');
            }
        });
    }
}
