<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SmsVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendSmsCodeRequest;
use App\Http\Requests\Auth\VerifySmsCodeRequest;
use App\Models\User;
use App\Services\Sms\SmsVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SmsAuthController extends Controller
{
    public function __construct(private readonly SmsVerificationService $smsVerificationService)
    {
    }

    /**
     * 電話番号あてに認証コードを送信する(登録・ログイン共通)
     */
    public function send(SendSmsCodeRequest $request): JsonResponse
    {
        $phoneNumber = $request->string('phone_number')->toString();
        $verification = $this->smsVerificationService->sendCode($phoneNumber);

        $payload = [
            'message' => '認証コードを送信しました。',
        ];

        if ($this->isDemoCodeVisible($phoneNumber)) {
            $payload['demo_code'] = $verification->code;
        }

        return response()->json($payload);
    }

    /**
     * 一般公開デモ環境で、閲覧者が実際のSMSを受け取らずに認証を体験できるよう、
     * 認証コードをレスポンスへ含めてよいかどうかを判定する。
     * 以下の両方を満たす場合のみtrue(どちらか片方でもfalseなら絶対にコードを含めない)。
     * - DEMO_MODE=true(config/demo.php の 'enabled')
     * - 電話番号が DEMO_SMS_PHONE_NUMBERS のホワイトリストに含まれている
     */
    private function isDemoCodeVisible(string $phoneNumber): bool
    {
        return config('demo.enabled') === true
            && in_array($phoneNumber, config('demo.sms_phone_numbers', []), true);
    }

    /**
     * 認証コードを検証してログインする。
     * まだ登録されていない電話番号の場合は、name/addressを使ってその場で会員登録も行う。
     */
    public function verify(VerifySmsCodeRequest $request): JsonResponse
    {
        $phoneNumber = $request->string('phone_number')->toString();

        try {
            $verification = $this->smsVerificationService->verifyCode($phoneNumber, $request->string('code')->toString());
        } catch (SmsVerificationException $exception) {
            return response()->json([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        $user = User::where('phone_number', $phoneNumber)->first();

        if (! $user) {
            if (blank($request->input('name')) || blank($request->input('address'))) {
                return response()->json([
                    'error' => [
                        'code' => 'REGISTRATION_INFO_REQUIRED',
                        'message' => '初めてのご利用です。お名前とご住所を入力してください。',
                    ],
                ], 422);
            }

            $user = User::create([
                'role' => User::ROLE_BUYER,
                'name' => $request->input('name'),
                'phone_number' => $phoneNumber,
                'address' => $request->input('address'),
                'email' => $request->input('email'),
                'is_active' => true,
            ]);
        }

        $this->smsVerificationService->markAsVerified($verification);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
        ]);
    }
}
