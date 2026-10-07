<?php

namespace App\Domain\Event;

use Ramsey\Uuid\Uuid;

class EmailVerificationEventFactory
{
    public function create(
        string $requestId,
        string $email,
        string $code
    ): EmailVerificationEvent {
        return new EmailVerificationEvent(
            eventId: Uuid::uuid4()->toString(),
            eventType: 'email.send.requested',
            eventVersion: 1,
            occurredAt: (new \DateTimeImmutable())->format(DATE_ATOM),

            traceId: $requestId,
            correlationId: $requestId,

            service: 'auth-service',

            requestId: $requestId,
            template: 'registration_verification',
            locale: 'en-US',
            verificationCode: $code,

            email: $email,

            expiresInSeconds: 60,
            resendAttemptsLeft: 0
        );
    }
}
