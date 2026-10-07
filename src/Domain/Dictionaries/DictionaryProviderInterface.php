<?php

namespace App\Domain\Dictionaries;


/**
 * Определяет контракт для всех справочников.
 * Контроллер или сервис не знают, как именно реализован справочник (SQL, API, кэш).
 */
interface DictionaryProviderInterface
{
    /**
     * Уникальное имя справочника (код для API)
     */
    public function getName(): string;

    /**
     * Получение всех элементов справочника
     *
     * @return object[]
     */
    public function getItems(bool $onlyActive = true): array;
}
