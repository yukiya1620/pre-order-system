<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\SmsVerificationException;
use App\Models\SmsVerification;

class SmsVerificationService
{
    // 認証コードの有効期限(分)。設計書3.3.2の「発行から5分」
    private const CODE_EXPIRES_MINUTES = 5;

    // 何回間違えたら無効化するか。設計書3.3.2の「5回で無効化」
    private const MAX_ATTEMPT_COUNT = 5;

    // 認証コードの桁数
    private const CODE_DIGITS = 6;

    public function __construct(private readonly SmsSender $smsSender)
    {
    }

    /**
     * 電話番号あてに6桁の認証コードを送信する。
     * 戻り値の SmsVerification は、一般公開デモ環境でホワイトリスト登録された
     * 電話番号にだけ認証コードをレスポンスへ含める(SmsAuthController参照)ために使う。
     */
    public function sendCode(string $phoneNumber): SmsVerification
    {
        $code = (string) random_int(10 ** (self::CODE_DIGITS - 1), (10 ** self::CODE_DIGITS) - 1);

        $verification = SmsVerification::create([
            'phone_number' => $phoneNumber,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_EXPIRES_MINUTES),
            'attempt_count' => 0,
        ]);

        $this->smsSender->send(
            $phoneNumber,
            '【予約注文システム】認証コードは '.$code.' です。'.self::CODE_EXPIRES_MINUTES.'分以内にご入力ください。'
        );

        return $verification;
    }

    /**
     * 認証コードが正しいかどうかだけを確認する。
     * ここではまだ「使用済み」にはしない(呼び出し側の処理が最後まで成功してから
     * markAsVerified()を呼ぶことで、途中で失敗しても同じコードを再利用できるようにする)。
     *
     * @throws SmsVerificationException コードが無い・期限切れ・不一致・試行回数オーバーの場合
     */
    public function verifyCode(string $phoneNumber, string $code): SmsVerification
    {
        $verification = SmsVerification::where('phone_number', $phoneNumber)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verification) {
            throw new SmsVerificationException(
                '認証コードが見つかりません。もう一度送信してください。',
                'SMS_CODE_NOT_FOUND'
            );
        }

        if ($verification->attempt_count >= self::MAX_ATTEMPT_COUNT) {
            throw new SmsVerificationException(
                '入力回数の上限に達しました。もう一度コードを送信し直してください。',
                'SMS_CODE_LOCKED'
            );
        }

        if ($verification->expires_at->isPast()) {
            throw new SmsVerificationException(
                '認証コードの有効期限が切れています。もう一度送信してください。',
                'SMS_CODE_EXPIRED'
            );
        }

        if (! hash_equals($verification->code, $code)) {
            $verification->increment('attempt_count');

            throw new SmsVerificationException(
                '認証コードが正しくありません。',
                'SMS_CODE_MISMATCH'
            );
        }

        return $verification;
    }

    /**
     * 一連の処理(ログイン・会員登録)が最後まで成功した後に呼び、コードを使用済みにする
     */
    public function markAsVerified(SmsVerification $verification): void
    {
        $verification->update(['verified_at' => now()]);
    }
}
