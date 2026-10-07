<?php

namespace App\Domain\Registration\Otp;

interface OtpStorageInterface
{
    public function getOtp(string $requestId): ?array;

    public function incrementAttempts(string $requestId): int;

    public function verifyCode(string $requestId, string $code): bool;

    public function clear(string $requestId): void;
}
