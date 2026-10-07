<?php

namespace App\Shared\Exception;

final class AuthenticationRequiredException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Authentication required',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::CATALOG_AUTH_AUTH_REQUIRED,
            context: $context,
            previous: $previous
        );
    }
}
