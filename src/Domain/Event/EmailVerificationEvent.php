<?php

namespace App\Domain\Event;

/**
 * DTO
 */
class EmailVerificationEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly string $occurredAt,

        public readonly string $traceId,
        public readonly string $correlationId,

        public readonly string $service,

        public readonly string $requestId,
        public readonly string $template,
        public readonly string $locale,
        public readonly string $verificationCode,

        public readonly string $email,

        public readonly int $expiresInSeconds,
        public readonly int $resendAttemptsLeft,
    ) {}
}
