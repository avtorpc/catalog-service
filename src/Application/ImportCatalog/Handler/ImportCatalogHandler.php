<?php

namespace App\Application\ImportCatalog\Handler;

use App\Application\ImportCatalog\Command\ImportCatalogCommand;
use App\Application\ImportCatalog\DTO\ImportCatalogResponse;
use App\Application\ImportCatalog\Parser\CatalogCsvParser;
use App\Infrastructure\Catalog\Schema\CatalogTableCreator;
use App\Infrastructure\Catalog\Persistence\CategoryRepository;
use App\Infrastructure\Catalog\Schema\CatalogTableSwitcher;

final class ImportCatalogHandler
{
    public function __construct(
        private CatalogCsvParser $parser,
        private CatalogTableCreator $tableCreator,
        private CategoryRepository $categoryRepository,
        private  CatalogTableSwitcher $switcher
    ) {}

    public function handle(ImportCatalogCommand $command): ImportCatalogResponse
    {
        $startedAt = new \DateTimeImmutable();

        /**
         * 1. Создаём новую таблицу под импорт
         */
        $tableName = $this->tableCreator->create('catalog');

        /**
         * 2. Парсим CSV стримингом
         */
        $rows = $this->parser->parse($command->fullPath);

        $count = 0;

        /**
         * 3. STREAM INSERT (без памяти)
         */
        foreach ($rows as $row) {
            $this->categoryRepository->insertRaw(
                tableName: $tableName,
                externalId: $row->externalId,
                uuid: $row->uuid,
                parentExternalId: $row->parentExternalId,
                name: $row->name,
                slug: $row->slug,
                lineNumber: $row->lineNumber,
            );
            $count++;
        }

        /**
         * 4. POST-PROCESS (level, is_leaf)
         */
        $this->categoryRepository->recalculateHierarchy($tableName);

        /**
         * 5. Финализируем таблицу (регистрация версии)
         */
        $this->switcher->registerVersion($tableName, $count);

        $finishedAt = new \DateTimeImmutable();

        return new ImportCatalogResponse(
            success: true,
            fileName: $command->originalName,
            rowsProcessed: $count,
            maxDepth: 0,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );
    }
}
