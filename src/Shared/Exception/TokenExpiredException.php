<?php

namespace App\Shared\Exception;

final class TokenExpiredException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Access token expired',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::CATALOG_AUTH_TOKEN_EXPIRED,
            context: $context,
            previous: $previous
        );
    }
}
