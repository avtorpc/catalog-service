<?php

namespace App\Infrastructure\EventPublisher;

use App\Domain\Event\EmailVerificationEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpEmailEventPublisher implements EmailEventPublisherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EmailEventSerializer $serializer,
        private string $baseUrl,
        private string $mailSendPath
    ) {}

    private function buildUrl(): string
    {
        $url = rtrim($this->baseUrl, '/')
            . '/'
            . ltrim($this->mailSendPath, '/');

        error_log('[EVENT][BUILD] baseUrl=' . $this->baseUrl);
        error_log('[EVENT][BUILD] mailSendPath=' . $this->mailSendPath);
        error_log('[EVENT][BUILD] finalUrl=' . $url);

        return $url;
    }

    public function publish(EmailVerificationEvent $event): void
    {
        $payload = $this->serializer->toArray($event);
        $url = $this->buildUrl();

        error_log('[EVENT][REQUEST] START');
        error_log('[EVENT][REQUEST] url=' . $url);
        error_log('[EVENT][REQUEST] method=POST');
        error_log('[EVENT][REQUEST] payload=' . json_encode($payload));

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 3,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);

            error_log('[EVENT][RESPONSE] status=' . $status);
            error_log('[EVENT][RESPONSE] body=' . $body);
            error_log('[EVENT][TIME] ms=' . ((microtime(true) - $start) * 1000));

            if ($status >= 300) {
                error_log('[EVENT][ERROR] gateway_status=' . $status);
                error_log('[EVENT][ERROR] gateway_body=' . $body);

                throw new \RuntimeException(
                    'Event gateway error (HTTP ' . $status . ')'
                );
            }

            error_log('[EVENT][REQUEST] SUCCESS');

        } catch (\Throwable $e) {
            error_log('[EVENT][EXCEPTION] class=' . get_class($e));
            error_log('[EVENT][EXCEPTION] message=' . $e->getMessage());
            error_log('[EVENT][EXCEPTION] url=' . $url);

            throw $e;
        }
    }
}
