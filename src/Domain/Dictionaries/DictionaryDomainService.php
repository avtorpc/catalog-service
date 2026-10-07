<?php

namespace App\Domain\Dictionaries;

use App\Infrastructure\Dictionary\HttpDictionaryProvider;
use App\Shared\Exception\ErrorCode;
use App\Shared\Exception\UnprocessableEntityException;

class DictionaryDomainService
{
    public function __construct(
        private HttpDictionaryProvider $dictionaryProvider
    ) {}

    public function isRoleAllowedInCountry(string $role, string $country): bool
    {
        $items = $this->dictionaryProvider->getDictionaries('roles-countries');

        foreach ($items as $item) {
            if (
                ($item['role'] ?? null) === $role
                && ($item['country'] ?? null) === $country
                && ($item['is_available'] ?? false) === true
            ) {
                return true;
            }
        }

        return false;
    }

    public function validateRoleCountry(string $role, string $country): void
    {
        if (!$this->existsByCode(
            $this->dictionaryProvider->getDictionaries('countries'),
            $country,
            'alpha_2_code'
        )) {
            throw new UnprocessableEntityException(
                sprintf('Страна с кодом %s не найдена', $country),
                ErrorCode::B_COUNTRY_NOT_FOUND
            );
        }

        if (!$this->existsByCode(
            $this->dictionaryProvider->getDictionaries('roles'),
            $role,
            'code'
        )) {
            throw new UnprocessableEntityException(
                sprintf('Роль с кодом %s не найдена', $role),
                ErrorCode::B_ROLE_NOT_FOUND
            );
        }

        if (!$this->isRoleAllowedInCountry($role, $country)) {
            throw new UnprocessableEntityException(
                sprintf(
                    'Для %s недоступна регистрация в качестве %s',
                    $country,
                    $role
                ),
                ErrorCode::B_ROLE_UNAVAILABLE_IN_COUNTRY,
                ['validateRoleCountry not found role + country']
            );
        }
    }

    public function getVerificationStatus( $status ): string{
        if (!$this->existsByCode(
            $this->dictionaryProvider->getDictionaries('verification-statuses'),
            $status,
            'code'
        )) {
            throw new UnprocessableEntityException(
                sprintf('Статус отпраки почты с кодом %s не существует', $status),
                ErrorCode::B_ROLE_NOT_FOUND
            );
        } else {
            return $status;
        }
    }

    private function existsByCode(array $items, string $value, string $field = 'code'): bool
    {
        foreach ($items as $item) {
            if (
                ($item[$field] ?? null) === $value
                && ($item['is_active'] ?? true)
            ) {
                return true;
            }
        }

        return false;
    }
}
