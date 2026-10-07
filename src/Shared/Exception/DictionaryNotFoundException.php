<?php
namespace App\Shared\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DictionaryNotFoundException extends NotFoundHttpException
{
    private string $dictionaryName;

    public function __construct(string $dictionaryName, string $message = '', ?\Throwable $previous = null)
    {
        $this->dictionaryName = $dictionaryName;
        $message = $message ?: "Не найден справочник {$dictionaryName}";
        parent::__construct($message, $previous);
    }

    public function getDictionaryName(): string
    {
        return $this->dictionaryName;
    }

    public function getErrorCode(): string
    {
        return 'NOT_FOUND_DICTIONARY';
    }
}
