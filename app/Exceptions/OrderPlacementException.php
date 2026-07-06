<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 注文確定処理中に問題が起きたときに投げる例外(在庫不足・販売停止など)。
 * errorCode()の値は、API設計書のエラー形式({"error":{"code":...}})にそのまま使う。
 */
class OrderPlacementException extends RuntimeException
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
