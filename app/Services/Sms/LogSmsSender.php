<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * 開発用のSMS送信クラス。
 * 実際には送信せず、ログファイルに内容を書き出すだけにする。
 * 本番運用する際は、Twilioなど実際のAPIを呼ぶクラスに差し替える。
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phoneNumber, string $message): void
    {
        Log::info('[SMS送信(開発用ログ表示)]', [
            'phone_number' => $phoneNumber,
            'message' => $message,
        ]);
    }
}
