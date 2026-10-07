<?php

namespace App\Shared\Time;

final class SystemClock implements ClockInterface
{
    private readonly \DateTimeZone $timezone;

    public function __construct(string $timezone)
    {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }

    public function nowFormatted(): string
    {
        return $this->now()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Для DBAL / PostgreSQL timestamp columns
     */
    public function format(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i:s');
    }

    /**
     * Удобно для API response
     */
    public function nowIso(): string
    {
        return $this->now()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     *  всегда UTC epoch
     */
    public function nowTimestamp(): int
    {
        return time();
    }
}
