<?php
namespace App\Shared\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Shared\Exception\ErrorCode;

class TooManyRequestsException extends HttpException
{
    private ErrorCode $errorCode;
    private array $context;

    public function __construct(
        string $message = 'Too many requests',
        ErrorCode $errorCode = ErrorCode::B_TOO_MANY_REQUESTS,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(JsonResponse::HTTP_TOO_MANY_REQUESTS, $message, $previous);

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
