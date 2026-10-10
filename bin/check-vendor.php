<?php
declare(strict_types=1);

// Offline startup check: dependencies are delivered with the repository.
$root = dirname(__DIR__);
try {
    foreach (['vendor/autoload.php', 'vendor/autoload_runtime.php', 'vendor/composer/installed.json', 'composer.lock'] as $file) {
        if (!is_file($root.'/'.$file)) throw new RuntimeException('Отсутствует '.$file);
    }
    $lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $metadata = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $installed = array_column($metadata['packages'], null, 'name');
    foreach (array_merge($lock['packages'], $lock['packages-dev'] ?? []) as $package) {
        $actual = $installed[$package['name']] ?? null;
        if (!$actual || $actual['version'] !== $package['version']
            || ($actual['source']['reference'] ?? null) !== ($package['source']['reference'] ?? null)
            || !is_dir($root.'/vendor/composer/'.($actual['install-path'] ?? '../'.$package['name']))) {
            throw new RuntimeException('Зависимость не соответствует composer.lock: '.$package['name']);
        }
    }
    require $root.'/vendor/autoload.php';
    if (!class_exists(Symfony\Component\HttpKernel\Kernel::class) || !class_exists(Doctrine\DBAL\Connection::class)) {
        throw new RuntimeException('Автозагрузка Symfony/Doctrine недоступна');
    }
    fwrite(STDOUT, "Vendor соответствует composer.lock; загрузка пакетов не требуется.\n");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().". Подготовьте vendor через composer install на машине с доступом к репозиториям и сохраните его в Git.\n");
    exit(1);
}
