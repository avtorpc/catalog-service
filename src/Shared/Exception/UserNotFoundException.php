<?php

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserNotFoundException extends HttpException
{
    private ErrorCode $errorCode;
    private array $context;

    public function __construct(
        string $message = 'User not found',
        ErrorCode $errorCode = ErrorCode::AUTH_USER_NOT_FOUND,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(JsonResponse::HTTP_NOT_FOUND, $message, $previous);

        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
