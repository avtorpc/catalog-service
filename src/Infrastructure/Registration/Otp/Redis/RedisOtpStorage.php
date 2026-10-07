<?php

namespace App\Infrastructure\Registration\Otp\Redis;

use App\Domain\Registration\Otp\OtpStorageInterface;
use Predis\Client;

class RedisOtpStorage implements OtpStorageInterface
{
    private const PREFIX = 'otp:';

    public function __construct(private Client $redis) {}

    public function saveOtp(
        string $requestId,
        string $code,
        int $ttl,
        int $maxAttempts
    ): void {
        $key = self::PREFIX . $requestId;

        $this->redis->del($key);

        $this->redis->hMSet($key, [
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
        ]);

        $this->redis->expire($key, $ttl);
    }

    public function getOtp(string $requestId): ?array
    {
        $key = self::PREFIX . $requestId;

        $data = $this->redis->hGetAll($key);

        if (!$data || empty($data['code_hash'])) {
            return null;
        }

        return [
            'code_hash' => $data['code_hash'],
            'attempts' => (int)($data['attempts'] ?? 0),
            'max_attempts' => (int)($data['max_attempts'] ?? 0),
        ];
    }

    public function incrementAttempts(string $requestId): int
    {
        $key = self::PREFIX . $requestId;

        return (int) $this->redis->hIncrBy($key, 'attempts', 1);
    }

    public function verifyCode(string $requestId, string $code): bool
    {
        $stored = $this->getOtp($requestId);

        if (!$stored) {
            return false;
        }

        return hash_equals(
            $stored['code_hash'],
            hash('sha256', $code)
        );
    }

    public function isBlocked(string $requestId): bool
    {
        $stored = $this->getOtp($requestId);

        if (!$stored) {
            return true;
        }

        return $stored['attempts'] >= $stored['max_attempts'];
    }

    public function clear(string $requestId): void
    {
        $this->redis->del(self::PREFIX . $requestId);
    }
}
