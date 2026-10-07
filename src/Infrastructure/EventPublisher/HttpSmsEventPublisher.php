<?php

namespace App\Infrastructure\EventPublisher;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;
use App\Shared\Exception\IntegrationException;
use App\Shared\Exception\ErrorCode;

final class HttpSmsEventPublisher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private SmsEventSerializer $serializer,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $smsSendPath
    ) {}

    private function buildUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($this->smsSendPath, '/');
    }

    public function publish(object $event): void
    {
        $url = $this->buildUrl();
        $payload = $this->serializer->toArray($event);

        $start = microtime(true);

        $this->logger->info('SMS publish started', [
            'event_type' => $event->eventType ?? null,
            'event_id' => $event->eventId ?? null,
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 3,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);

            $durationMs = (microtime(true) - $start) * 1000;

            $this->logger->info('SMS gateway response received', [
                'status' => $status,
                'duration_ms' => $durationMs,
            ]);

            /**
             * ❗ Any non-2xx is treated as INTEGRATION error
             */
            if ($status >= 300) {
                throw new IntegrationException(
                    message: 'SMS gateway returned error response',
                    errorCode: ErrorCode::AUTH_SMS_INTEGRATION_ERROR,
                    context: [
                        'status' => $status,
                        'response_body' => $body,
                        'url' => $url,
                        'payload' => $payload,
                    ]
                );
            }

            $this->logger->info('SMS published successfully', [
                'event_id' => $event->eventId ?? null,
            ]);

        } catch (TransportExceptionInterface $e) {
            throw new IntegrationException(
                message: 'SMS gateway transport failure',
                errorCode: ErrorCode::AUTH_SMS_INTEGRATION_ERROR,
                context: [
                    'url' => $url,
                    'reason' => $e->getMessage(),
                ],
                previous: $e
            );
        } catch (IntegrationException $e) {
            throw $e; // already normalized
        } catch (\Throwable $e) {
            throw new IntegrationException(
                message: 'Unexpected SMS gateway failure',
                errorCode: ErrorCode::AUTH_SMS_INTEGRATION_ERROR,
                context: [
                    'url' => $url,
                ],
                previous: $e
            );
        }
    }
}
