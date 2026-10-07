<?php

namespace App\Application\ImportProducts\Parser;

use App\Application\ImportProducts\DTO\ProductRow;

final class ProductCsvParser
{
    private const COL_NOMENCLATURE = 0;
    private const COL_EXTERNAL_CODE = 1;
    private const COL_QUANTITY = 2;
    private const COL_UNIT = 3;
    private const COL_PRICE = 4;
    private const MIN_COLUMNS = 2;

    /**
     * @return \Generator<ProductRow>
     */
    public function parse(string $filePath): \Generator
    {
        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV);
        $file->setCsvControl(';');

        $lineNumber = 0;

        foreach ($file as $row) {
            $lineNumber++;

            if (!$row || count($row) < self::MIN_COLUMNS) {
                continue;
            }

            if ($lineNumber === 1) {
                continue;
            }

            yield new ProductRow(
                nomenclature: trim($row[self::COL_NOMENCLATURE] ?? ''),
                externalCode: $this->nullableString($row[self::COL_EXTERNAL_CODE] ?? null),
                quantity: $this->nullableFloat($row[self::COL_QUANTITY] ?? null),
                unit: $this->nullableString($row[self::COL_UNIT] ?? null),
                price: $this->nullableFloat($row[self::COL_PRICE] ?? null),
                lineNumber: $lineNumber,
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
