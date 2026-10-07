<?php
namespace App\Infrastructure\Registration;

class VerificationCodeRedisStorage
{
    public function __construct(private \Redis $redis) {}

    /**
     * Сохраняем количество попыток для requestId
     */
    public function saveAttempts(string $requestId, int $attempts, int $ttl): void
    {
        $key = $this->getKey($requestId);
        $this->redis->setex($key, $ttl, $attempts);
    }

    /**
     * Получаем оставшиеся попытки
     */
    public function getAttemptsLeft(string $requestId): int
    {
        $key = $this->getKey($requestId);
        $attempts = $this->redis->get($key);

        return $attempts !== false ? (int)$attempts : 0;
    }

    /**
     * Атомарно уменьшаем попытки на 1
     */
    public function decrementAttempts(string $requestId): int
    {
        $key = $this->getKey($requestId);
        $attemptsLeft = $this->redis->decr($key);

        if ($attemptsLeft < 0) {
            $this->redis->set($key, 0);
            $attemptsLeft = 0;
        }

        return $attemptsLeft;
    }

    private function getKey(string $requestId): string
    {
        return "verification_attempts:$requestId";
    }
}
