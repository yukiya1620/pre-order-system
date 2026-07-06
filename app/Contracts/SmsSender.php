<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * 指定した電話番号にSMSを送信する
     */
    public function send(string $phoneNumber, string $message): void;
}
