<?php

namespace App\Api\Dictionaries\DTO;

final class ApiResponseDto implements ApiResponseDtoInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $success,
        public \DateTimeImmutable $timestamp,
        public array $data,
    ) {
    }
}
