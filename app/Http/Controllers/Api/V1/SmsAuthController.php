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
        $this->smsVerificationService->sendCode($request->string('phone_number')->toString());

        return response()->json([
            'message' => '認証コードを送信しました。',
        ]);
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
