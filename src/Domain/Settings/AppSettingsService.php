<?php

namespace App\Domain\Settings;

use App\Infrastructure\Dictionary\HttpDictionaryProvider;

class AppSettingsService
{
    private const CODE_EXPIRY = 'code_expiry_seconds';
    private const RESEND_INTERVAL = 'resend_interval_seconds';
    private const MAX_CODE_REGENERATIONS = 'max_code_regenerations';
    private const MAX_VERIFICATION_ATTEMPTS = 'max_verification_attempts';
    private const EMAIL_COOLDOWN = 'email_cooldown_seconds';
    private const IP_WINDOW = 'ip_window_seconds';
    private const IP_MAX_ATTEMPTS = 'ip_max_attempts';
    private const SIGNUP_TTL = 'active_signup_ttl_seconds';

    public function __construct(
        private HttpDictionaryProvider $dictionary
    ) {}

    /**
     * Время жизни кода подтверждения (в минутах).
     * Определяет, сколько минут код остаётся действительным после отправки.
     */
    public function getCodeExpiryMinutes(): int
    {
        return $this->dictionary->getValueByKey(self::CODE_EXPIRY, 10);
    }

    /**
     * Интервал между повторными запросами кода (в секундах).
     * Определяет, через сколько секунд пользователь может запросить новый код.
     */
    public function getResendIntervalSeconds(): int
    {
        return $this->dictionary->getValueByKey(self::RESEND_INTERVAL, 60);
    }

    /**
     * Максимальное количество повторных генераций кода.
     * Ограничивает число запросов на повторную отправку кода.
     */
    public function getMaxCodeRegenerations(): int
    {
        return $this->dictionary->getValueByKey(self::MAX_CODE_REGENERATIONS, 3);
    }

    /**
     * Максимальное количество попыток подтверждения кода.
     * После превышения пользователь должен быть заблокирован или ограничен.
     */
    public function getMaxVerificationAttempts(): int
    {
        return $this->dictionary->getValueByKey(self::MAX_VERIFICATION_ATTEMPTS, 5);
    }

    /**
     * Минимальный интервал между регистрациями с одним email (в секундах). анти-спам / защита
     */
    public function getEmailCooldown(): int
    {
        return $this->dictionary->getValueByKey(self::EMAIL_COOLDOWN, 60);
    }

    /**
     * Временное окно (в секундах) для подсчёта регистраций с одного IP.
     */
    public function getIpWindow(): int
    {
        return $this->dictionary->getValueByKey(self::IP_WINDOW, 60);
    }

    /**
     * Максимальное количество регистраций с одного IP за заданное окно времени.
     */
    public function getIpMaxAttempts(): int
    {
        return $this->dictionary->getValueByKey(self::IP_MAX_ATTEMPTS, 10);
    }

    /**
     * Время жизни активной (незавершённой) регистрации (в секундах).
     * По истечении этого времени регистрация считается просроченной.
     */
    public function getSignupTtl(): int
    {
        return $this->dictionary->getValueByKey(self::SIGNUP_TTL, 600);
    }
}
