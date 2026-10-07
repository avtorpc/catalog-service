<?php

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\Response;

class AuthException extends AbstractApiException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getErrorCode(): string
    {
        return ErrorCode::UNAUTHORIZED;
    }
}
