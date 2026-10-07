<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\ErrorHandler;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Регистрируем стандартный ErrorHandler Symfony
ErrorHandler::register();

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    // Shutdown handler для всех фатальных ошибок
    register_shutdown_function(function () use ($kernel) {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            try {
                // Пишем в Monolog
                $logger = $kernel->getContainer()->get('monolog.logger.api');
                $logger->critical('Fatal error during DI or request processing', [
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]);
            } catch (\Exception $e) {
                error_log(sprintf(
                    'Fatal error: [%d] %s in %s on line %d',
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                ));
            }

            // Возвращаем JSON клиенту, если headers ещё не отправлены
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');

                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'FATAL_ERROR',
                        'message' => $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                    ],
                    'timestamp' => (new \DateTimeImmutable())->format('c'),
                ], JSON_UNESCAPED_UNICODE);
            }
        }
    });

    return $kernel;
};
