<?php

namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Shared\Exception\ErrorCode;

class BadRequestException extends HttpException
{
    private ErrorCode $errorCode;
    private array $context;

    public function __construct(
        string $message = 'Bad request',
        ErrorCode $errorCode = ErrorCode::S_BAD_REQUEST,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(JsonResponse::HTTP_BAD_REQUEST, $message, $previous);

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
