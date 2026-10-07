<?php

namespace App\Shared\Exception;

final class InvalidTokenException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Invalid access token',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::CATALOG_AUTH_INVALID_TOKEN,
            context: $context,
            previous: $previous
        );
    }
}
