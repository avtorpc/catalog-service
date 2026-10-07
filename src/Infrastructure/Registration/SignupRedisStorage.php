<?php

namespace App\Infrastructure\Registration;

class SignupRedisStorage
{
    public function __construct(private \Redis $redis) {}

    public function saveCode(
        string $requestId,
        string $code,
        int $ttl,
        int $attempts
    ): void {
        $key = "signup:$requestId";
        $this->redis->setex($key, $ttl, json_encode([
            'code' => $code,
            'attempts_left' => $attempts
        ]));
    }

    public function getAttempts(string $requestId): ?int
    {
        $key = "signup:$requestId";
        $data = $this->redis->get($key);
        if (!$data) return null;
        $arr = json_decode($data, true);
        return $arr['attempts_left'] ?? null;
    }
}
