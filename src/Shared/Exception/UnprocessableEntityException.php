<?php

namespace App\Shared\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnprocessableEntityException extends HttpException
{
    private ErrorCode $errorCode;
    private array $context;

    public function __construct(
        string $message = 'Unprocessable entity---',
        ErrorCode $errorCode = ErrorCode::S_DEFAULT_UNPROCESSABLE_ENTITY,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(422, $message, $previous);

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
