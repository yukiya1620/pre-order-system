<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Services\Sms\LogSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SmsSenderを使いたい場所には、今はLogSmsSender(ログ表示版)を渡す。
        // 本番用のTwilio実装ができたら、ここを差し替えるだけで全体に反映される。
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SMS送信APIの連打防止。既存の「認証コード5回試行制限」(SmsVerificationService)とは別役割:
        // こちらは送信そのもの(コード発行・SmsSender呼び出し)の回数を制限する。
        // 電話番号ごと・IPごとの二段構えにし、特定の番号への迷惑SMS化と、
        // 単一IPからの全体的な連打の両方を防ぐ。
        RateLimiter::for('sms-send', function (Request $request) {
            $phoneNumber = (string) $request->input('phone_number');

            return [
                Limit::perMinutes(10, 5)->by('phone:'.$phoneNumber),
                Limit::perHour(20)->by('ip:'.$request->ip()),
            ];
        });
    }
}
