<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use Predis\Client;

final class EmailRateLimiter
{
    public function __construct(
        private Client $redis,
        private string $serviceName = 'auth-service'
    ) {}

    public function tryAcquire(string $email, int $ttl, string $action = 'login'): bool
    {
        $key = $this->buildKey($email, $action);

        $result = $this->redis->set(
            $key,
            '1',
            'NX',
            'EX',
            $ttl
        );

        return $result instanceof \Predis\Response\Status
            && (string) $result === 'OK';
    }

    private function buildKey(string $email, string $action): string
    {
        return sprintf(
            '%s:%s:email:%s',
            $this->serviceName,
            $action,
            sha1(mb_strtolower($email))
        );
    }
}
