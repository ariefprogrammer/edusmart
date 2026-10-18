<?php

namespace App\Exceptions;

use RuntimeException;

class OtpThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Terlalu sering meminta kode. Coba lagi dalam {$retryAfterSeconds} detik.");
    }
}