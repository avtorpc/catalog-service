<?php

declare(strict_types=1);

namespace App\Infrastructure\Dictionary;

use App\Api\Dictionaries\DTO\ApiResponseDto;
use App\Api\Dictionaries\DTO\ApiResponseDtoInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Провайдер справочников через HTTP-запросы к mp-backend.
 *
 * Формат ожидаемого ответа:
 * GET /api/dictionaries/{key}?client_id={clientId}
 * {
 *   "data": { ... справочник ... }
 * }
 *.
 */
final class HttpDictionaryProvider
{
    /** @var array<string, mixed> */
    private array $cacheSettings = [];

    /** @var array<string, array> */
    private array $cacheDictionaries = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {}

    public function getDictionaries(string $dictionary): array
    {
        // 1. cache hit
        if (array_key_exists($dictionary, $this->cacheDictionaries)) {
            return $this->cacheDictionaries[$dictionary];
        }

        // 2. HTTP request
        $response = $this->httpClient->request(
            'GET',
            rtrim($this->baseUrl, '/') . "/" . $dictionary,
            [
                'timeout' => 2.0,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        $raw = $response->toArray();

        // 3. DTO
        $dto = $this->toApiResponseDto($raw);

        // 4. success check
        if ($dto->success !== true) {
            throw new IntegrationException(
                'Dictionary service returned success=false',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'response_success_false',
                    'source' => 'http_dictionary_service',
                    'dictionary' => $dictionary,
                ]
            );
        }

        $items = $dto->data['items'] ?? [];
        $total = $dto->data['total'] ?? null;

        // 5. items structure check
        if (!is_array($items)) {
            throw new IntegrationException(
                'Invalid dictionary response: items is missing or invalid',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'invalid_response_structure',
                    'field' => 'items',
                    'dictionary' => $dictionary,
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        // 6. total check
        if (!is_int($total) || $total === 0) {
            throw new IntegrationException(
                'Dictionary service returned empty data',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'empty_dictionary_response',
                    'dictionary' => $dictionary,
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        // 7. cache dictionary
        $this->cacheDictionaries[$dictionary] = $items;

        return $items;
    }

    public function getValueByKey(string $name, $value): int
    {
        // 1. cache hit
        if (array_key_exists($name, $this->cacheSettings)) {
            return $this->cacheSettings[$name];
        }

        // 2. HTTP request
        $response = $this->httpClient->request(
            'GET',
            rtrim($this->baseUrl, '/') . '/settings',
            [
                'timeout' => 2.0,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        $raw = $response->toArray();

        // 3. DTO
        $dto = $this->toApiResponseDto($raw);

        // 4. success check
        if ($dto->success !== true) {
            throw new IntegrationException(
                'Dictionary service returned success=false',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'response_success_false',
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        $items = $dto->data['items'] ?? [];
        $total = $dto->data['total'] ?? null;

        // 5. items structure check
        if (!is_array($items)) {
            throw new IntegrationException(
                'Invalid dictionary response: items is missing or invalid',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'invalid_response_structure',
                    'field' => 'items',
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        // 6. total check (empty data rule)
        if (!is_int($total) || $total === 0) {
            throw new IntegrationException(
                'Dictionary service returned empty data (total = 0)',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'empty_dictionary_response',
                    'total' => $total,
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        // 7. hydrate cache
        foreach ($items as $item) {
            if (
                isset($item['setting_key']) &&
                array_key_exists('value', $item)
            ) {
                $this->cacheSettings[$item['setting_key']] = $item['value'];
            }
        }

        // 8. final check
        if (!array_key_exists($name, $this->cacheSettings)) {
            throw new IntegrationException(
                sprintf('Setting "%s" not found in dictionary response', $name),
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'missing_setting',
                    'setting_key' => $name,
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        return $this->cacheSettings[$name];
    }

    private function toApiResponseDto(array $data): ApiResponseDtoInterface
    {
        if (!isset($data['success']) || !is_bool($data['success'])) {
            throw new IntegrationException(
                'Invalid response: missing or invalid success field',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'invalid_success_field',
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        if (!isset($data['timestamp']) || !is_string($data['timestamp'])) {
            throw new IntegrationException(
                'Invalid response: missing or invalid timestamp field',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'invalid_timestamp_field',
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new IntegrationException(
                'Invalid response: missing data field',
                ErrorCode::D_INTEGRATION_ERROR,
                [
                    'type' => 'invalid_data_field',
                    'source' => 'http_dictionary_service',
                ]
            );
        }

        return new ApiResponseDto(
            success: $data['success'],
            timestamp: new \DateTimeImmutable($data['timestamp']),
            data: $data['data'],
        );
    }
}
