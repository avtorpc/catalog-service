<?php

namespace App\Shared\Exception;

final class DictionariesServiceException extends IntegrationException
{
    public static function serviceUnavailable(?\Throwable $previous = null): self
    {
        return new self('Dictionaries service unavailable', 503, $previous);
    }

    public static function badResponse(string $details = '', ?\Throwable $previous = null): self
    {
        return new self('Invalid response from dictionaries service: ' . $details, 502, $previous);
    }
}
