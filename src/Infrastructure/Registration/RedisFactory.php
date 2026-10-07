<?php

namespace App\Infrastructure\Registration;

use Predis\Client;

class RedisFactory
{
    public static function create(
        string $url,
        ?string $password = null,
        ?string $caPath = null
    ): Client {
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid REDIS_URL: {$url}");
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? 6379;
        $scheme = $parsed['scheme'] ?? 'redis';

        /**
         * Базовая конфигурация
         */
        $parameters = [
            'scheme' => $scheme === 'rediss' ? 'tls' : 'tcp',
            'host'   => $host,
            'port'   => $port,
        ];

        /**
         * Пароль (если есть)
         */
        if (!empty($password)) {
            $parameters['password'] = $password;
        }

        /**
         * TLS настройки (только если rediss)
         */
        if ($scheme === 'rediss') {
            $parameters['ssl'] = [
                'cafile' => $caPath,
                'verify_peer' => true,
                'verify_peer_name' => false, // важно для Selectel
            ];
        }

        /**
         * Опции клиента
         */
        $options = [];

        // включаем cluster только для managed Redis
        if ($scheme === 'rediss') {
            $options['cluster'] = 'redis';
        }

        return new Client($parameters, $options);
    }
}
