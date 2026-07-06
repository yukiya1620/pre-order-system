<?php

namespace App\Http\Requests\Farmer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondDeliveryConfirmationRequest extends FormRequest
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
            'response' => ['required', Rule::in(['配達可能', '配達日変更', '数量変更', 'キャンセル相談'])],
            'new_delivery_date' => ['nullable', 'date', 'required_if:response,配達日変更'],
            'response_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.required' => '回答内容を選択してください。',
            'response.in' => '回答内容は「配達可能」「配達日変更」「数量変更」「キャンセル相談」のいずれかを選んでください。',
            'new_delivery_date.required_if' => '配達日変更の場合は、新しい配達予定日を入力してください。',
        ];
    }
}
