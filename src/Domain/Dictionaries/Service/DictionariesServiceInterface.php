<?php

namespace App\Domain\Dictionaries\Service;

interface DictionariesServiceInterface
{
    public function getSettings(string $group): array;

    public function getDictionary(
        string $dictionary,
        ?bool $onlyActive = true
    ): array;
}
