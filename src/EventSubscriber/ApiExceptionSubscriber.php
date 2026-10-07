<?php

namespace App\EventSubscriber;

use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\ForbiddenException;
use App\Shared\Exception\TooManyRequestsException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Exception\UnprocessableEntityException;
use App\Shared\Exception\IntegrationException;
use App\Application\Dictionaries\DictionaryNotFoundException;
use App\Shared\Exception\UserNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.api')]
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $status = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
        $code = 'INTERNAL_SERVER_ERROR';
        $message = 'Внутренняя ошибка сервера';

        switch (true) {

            /**
             * DOMAIN / APPLICATION ERRORS
             */
            case $exception instanceof DictionaryNotFoundException:
                $status = JsonResponse::HTTP_NOT_FOUND;
                $code = $exception->getErrorCode();
                $message = $exception->getMessage();
                break;

            /**
             * CLIENT ERRORS (4xx)
             */
            case $exception instanceof BadRequestException:
            case $exception instanceof UnauthorizedException:
            case $exception instanceof ForbiddenException:
            case $exception instanceof TooManyRequestsException:
            case $exception instanceof UnprocessableEntityException:
            case $exception instanceof UserNotFoundException:
                $status = $exception->getStatusCode();
                $code = $exception->getErrorCode();
                $message = $exception->getMessage();
                break;

            /**
             * INTEGRATION ERRORS (external services, kafka, http, etc)
             */
            case $exception instanceof IntegrationException:
                $status = JsonResponse::HTTP_SERVICE_UNAVAILABLE;
                $code = $exception->getErrorCode();
                $message = 'External service temporarily unavailable';
                break;

            /**
             * ROUTING ERRORS
             */
            case $exception instanceof NotFoundHttpException:
                $status = JsonResponse::HTTP_NOT_FOUND;
                $code = 'ROUTE_NOT_FOUND';
                $message = 'API маршрут не найден';
                break;

            /**
             * SYMFONY HTTP ERRORS
             */
            case $exception instanceof HttpExceptionInterface:
                $status = $exception->getStatusCode();
                $code = 'HTTP_ERROR';
                $message = $exception->getMessage();
                break;

            /**
             * DATABASE ERRORS
             */
            case $exception instanceof \PDOException:
            case $exception instanceof \Doctrine\DBAL\Exception:
                $status = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
                $code = 'DATABASE_ERROR';
                $message = 'Database error occurred';
                break;

            case $exception instanceof NotFoundException:
                $status = $exception->getStatusCode();
                $code = $exception->getErrorCode();
                $message = $exception->getMessage();
                break;

            /**
             * FALLBACK
             */
            default:
                $status = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
                $code = 'INTERNAL_SERVER_ERROR';
                $message = $exception->getMessage() ?: 'Unexpected error';
                break;
        }

        /**
         * LOGGING (structured)
         */
        $errorCode = method_exists($exception, 'getErrorCode')
            ? $exception->getErrorCode()
            : $code;

        $context = method_exists($exception, 'getContext')
            ? $exception->getContext()
            : [];
        $this->logger->error('API exception', [
            'error_code' => $errorCode,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'context' => $context,
            'path' => $event->getRequest()->getPathInfo(),
            'method' => $event->getRequest()->getMethod(),
            'trace' => $exception->getTraceAsString(),
        ]);

        /**
         * RESPONSE
         */
        $response = new JsonResponse([
            'success' => false,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
            'requestId' => array_key_exists('requestId', $context)
                ? $context['requestId']
                : null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);

        $event->setResponse($response);
    }
}
