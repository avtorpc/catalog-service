<?php

namespace App\Infrastructure\EventPublisher;

use App\Domain\Event\SmsVerificationEvent;

class SmsEventSerializer
{
    public function toArray(SmsVerificationEvent $event): array
    {
        return [
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'event_version' => $event->eventVersion,
            'occurred_at' => $event->occurredAt,

            'trace_id' => $event->traceId,
            'correlation_id' => $event->correlationId,

            'producer' => [
                'service' => $event->service
            ],

            'callback' => '',

            'payload' => [
                'request_id' => $event->requestId,
                'template' => $event->template,
                'locale' => $event->locale,
                'verification_code' => $event->verificationCode,

                'recipient' => [
                    'phone' => $event->phoneNumber,
                ],

                'expires_in_seconds' => $event->expiresInSeconds,
                'resend_attempts_left' => $event->resendAttemptsLeft,
            ],
        ];
    }
}
