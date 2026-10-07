<?php

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Shared\Exception\ErrorCode;

class IntegrationException extends HttpException
{
    private ErrorCode $errorCode;
    private array $context;

    public function __construct(
        string $message = 'Integration error',
        ErrorCode $errorCode = ErrorCode::S_INTERNAL_SERVER_ERROR,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(JsonResponse::HTTP_SERVICE_UNAVAILABLE, $message, $previous);

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
