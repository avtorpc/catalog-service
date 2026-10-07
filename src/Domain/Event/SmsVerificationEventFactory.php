<?php

namespace App\Domain\Event;

use Ramsey\Uuid\Uuid;

final class SmsVerificationEventFactory
{
    public function create(
        string $requestId,
        string $phoneNumber,
        string $code
    ): SmsVerificationEvent {
        return new SmsVerificationEvent(
            eventId: Uuid::uuid4()->toString(),
            eventType: 'sms.send.requested',
            eventVersion: 1,
            occurredAt: (new \DateTimeImmutable())->format(DATE_ATOM),

            traceId: $requestId,
            correlationId: $requestId,

            service: 'auth-service',

            requestId: $requestId,
            template: 'registration_verification',
            locale: 'en-US',
            verificationCode: $code,

            phoneNumber: $phoneNumber,

            expiresInSeconds: 60,
            resendAttemptsLeft: 0
        );
    }
}
