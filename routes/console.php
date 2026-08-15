<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 設計書4.10のバッチ処理
// 毎朝7:00: 配達予定日が3日後の注文の配達確認を作る
Schedule::command('orders:generate-delivery-confirmations')->dailyAt('07:00');
// 毎日0:05: 販売シーズンの状態(準備中/販売中/販売終了)を日付に基づいて更新する
Schedule::command('orders:update-product-sale-statuses')->dailyAt('00:05');
// 毎夕17:00: まだ回答されていない配達確認について、農家へ再通知する
Schedule::command('orders:remind-unanswered-delivery-confirmations')->dailyAt('17:00');

// 一般公開デモ環境
// 毎日4:00: 公開デモの操作結果を初期状態へ戻す
Schedule::command('demo:reset')
    ->dailyAt('04:00')
    ->environments(['production']);
