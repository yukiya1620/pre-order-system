<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * ログイン中利用者自身の登録情報(設定画面表示用)
     */
    public function me(): JsonResponse
    {
        return response()->json(['user' => $this->toProfileArray(Auth::user())]);
    }

    /**
     * 登録情報の変更(名前・住所・メールアドレス・通知方法の設定)。
     * 電話番号(ログインID)・パスワード・role・is_activeはここでは変更しない。
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();

        $user->fill([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
        ]);

        // emailはnullable(任意)なので、キー自体を送らなかった場合は既存の値を消さない。
        // 明示的にemailキーを含めた場合(nullや空文字を含む)だけ上書きする。
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }

        // notify_by_email/notify_by_smsも同様に、送られた場合だけ上書きする。
        if ($request->has('notify_by_email')) {
            $user->notify_by_email = $request->boolean('notify_by_email');
        }
        if ($request->has('notify_by_sms')) {
            $user->notify_by_sms = $request->boolean('notify_by_sms');
        }

        $user->save();

        return response()->json(['user' => $this->toProfileArray($user)]);
    }

    /**
     * レスポンスに含める項目を明示的に限定する(password・created_at等は含めない)。
     *
     * @return array<string, mixed>
     */
    private function toProfileArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'address' => $user->address,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'notify_by_email' => $user->notify_by_email,
            'notify_by_sms' => $user->notify_by_sms,
        ];
    }
}
