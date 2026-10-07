<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Загружаем .env
if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Debug режим
if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

// ГЛАВНОЕ: превращаем все ошибки (включая Notice) в Exception
set_error_handler(function (
    int $severity,
    string $message,
    string $file,
    int $line
) {
    // Если ошибка отключена через error_reporting — пропускаем
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});
