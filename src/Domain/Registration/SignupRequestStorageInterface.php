<?php

namespace App\Domain\Registration;

use App\Application\Registration\QuickSignup\DTO\SignupRequestDto;

interface SignupRequestStorageInterface
{
    /**
     * Уникальное имя справочника
     */
    public function getName(): string;
}
