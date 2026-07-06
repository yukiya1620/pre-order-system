<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * SMS認証コードの送信・検証で問題が起きたときに投げる例外。
 * errorCode()の値は、API設計書のエラー形式({"error":{"code":...}})にそのまま使う。
 */
class SmsVerificationException extends RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
