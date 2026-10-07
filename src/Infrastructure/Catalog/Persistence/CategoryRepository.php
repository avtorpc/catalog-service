<?php

namespace App\Infrastructure\Catalog\Persistence;

use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class CategoryRepository
{
    public function __construct(
        private Connection $connection,
        private SchemaSqlHelper $schemaSqlHelper
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveTreeRowsForFrontend(string $tableName, ?int $maxLevel): array
    {
        $table = $this->schemaSqlHelper->table($tableName);
        $maxLevelValue = $maxLevel ?? 0;

        return $this->connection->fetchAllAssociative(
            "
            WITH RECURSIVE tree AS (
                SELECT
                    c.external_id,
                    c.parent_external_id,
                    c.level,
                    c.name,
                    c.slug,
                    c.image_url,
                    c.sort_order,
                    c.is_active,
                    c.is_leaf,
                    ARRAY[]::varchar[] AS parent_slugs,
                    ARRAY[c.external_id]::varchar[] AS visited_external_ids
                FROM {$table} c
                WHERE c.is_active = true
                  AND c.parent_external_id IS NULL

                UNION ALL

                SELECT
                    c.external_id,
                    c.parent_external_id,
                    c.level,
                    c.name,
                    c.slug,
                    c.image_url,
                    c.sort_order,
                    c.is_active,
                    c.is_leaf,
                    tree.parent_slugs || tree.slug,
                    tree.visited_external_ids || c.external_id
                FROM {$table} c
                INNER JOIN tree ON tree.external_id = c.parent_external_id
                WHERE c.is_active = true
                  AND NOT c.external_id = ANY(tree.visited_external_ids)
            ),
            filtered AS (
                SELECT
                    external_id,
                    parent_external_id,
                    level,
                    name,
                    slug,
                    image_url,
                    sort_order,
                    is_active,
                    is_leaf,
                    parent_slugs
                FROM tree
                WHERE :max_level = 0 OR level <= :max_level
            )
            SELECT
                f.external_id,
                f.parent_external_id,
                f.level,
                f.name,
                f.slug,
                f.image_url,
                f.sort_order,
                f.is_active,
                f.is_leaf,
                to_json(f.parent_slugs)::text AS parent_slugs_json,
                COALESCE((
                    SELECT json_agg(
                        child.slug
                        ORDER BY child.sort_order ASC, child.name ASC, child.external_id ASC
                    )::text
                    FROM filtered child
                    WHERE child.parent_external_id = f.external_id
                ), '[]') AS children_slugs_json
            FROM filtered f
            ORDER BY f.level ASC, f.sort_order ASC, f.name ASC, f.external_id ASC
            ",
            [
                'max_level' => $maxLevelValue,
            ],
            [
                'max_level' => ParameterType::INTEGER,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveSectionBranchRowsForFrontend(string $tableName, string $slug): array
    {
        $table = $this->schemaSqlHelper->table($tableName);

        return $this->connection->fetchAllAssociative(
            "
            WITH RECURSIVE full_tree AS (
                SELECT
                    c.external_id,
                    c.parent_external_id,
                    c.level,
                    c.name,
                    c.slug,
                    c.image_url,
                    c.sort_order,
                    c.is_active,
                    c.is_leaf,
                    ARRAY[]::varchar[] AS parent_slugs,
                    ARRAY[c.external_id]::varchar[] AS visited_external_ids
                FROM {$table} c
                WHERE c.is_active = true
                  AND c.parent_external_id IS NULL

                UNION ALL

                SELECT
                    c.external_id,
                    c.parent_external_id,
                    c.level,
                    c.name,
                    c.slug,
                    c.image_url,
                    c.sort_order,
                    c.is_active,
                    c.is_leaf,
                    full_tree.parent_slugs || full_tree.slug,
                    full_tree.visited_external_ids || c.external_id
                FROM {$table} c
                INNER JOIN full_tree ON full_tree.external_id = c.parent_external_id
                WHERE c.is_active = true
                  AND NOT c.external_id = ANY(full_tree.visited_external_ids)
            ),
            target AS (
                SELECT *
                FROM full_tree
                WHERE slug = :slug
                LIMIT 1
            ),
            parents AS (
                SELECT
                    parent.*,
                    array_position(target.parent_slugs, parent.slug) AS parent_position
                FROM full_tree parent
                CROSS JOIN target
                WHERE parent.slug = ANY(target.parent_slugs)
            ),
            descendants AS (
                SELECT
                    target.external_id,
                    target.parent_external_id,
                    target.level,
                    target.name,
                    target.slug,
                    target.image_url,
                    target.sort_order,
                    target.is_active,
                    target.is_leaf,
                    target.parent_slugs,
                    ARRAY[target.external_id]::varchar[] AS visited_external_ids,
                    0 AS relative_depth
                FROM target

                UNION ALL

                SELECT
                    c.external_id,
                    c.parent_external_id,
                    c.level,
                    c.name,
                    c.slug,
                    c.image_url,
                    c.sort_order,
                    c.is_active,
                    c.is_leaf,
                    descendants.parent_slugs || descendants.slug,
                    descendants.visited_external_ids || c.external_id,
                    descendants.relative_depth + 1
                FROM {$table} c
                INNER JOIN descendants ON descendants.external_id = c.parent_external_id
                WHERE c.is_active = true
                  AND NOT c.external_id = ANY(descendants.visited_external_ids)
            ),
            selected AS (
                SELECT
                    'parent'::varchar AS relation_type,
                    parent_position AS relation_order,
                    external_id,
                    parent_external_id,
                    level,
                    name,
                    slug,
                    image_url,
                    sort_order,
                    is_active,
                    is_leaf,
                    parent_slugs
                FROM parents

                UNION ALL

                SELECT
                    CASE WHEN relative_depth = 0 THEN 'target' ELSE 'child' END AS relation_type,
                    relative_depth AS relation_order,
                    external_id,
                    parent_external_id,
                    level,
                    name,
                    slug,
                    image_url,
                    sort_order,
                    is_active,
                    is_leaf,
                    parent_slugs
                FROM descendants
            )
            SELECT
                selected.relation_type,
                selected.relation_order,
                selected.external_id,
                selected.parent_external_id,
                selected.level,
                selected.name,
                selected.slug,
                selected.image_url,
                selected.sort_order,
                selected.is_active,
                selected.is_leaf,
                to_json(selected.parent_slugs)::text AS parent_slugs_json,
                COALESCE((
                    SELECT json_agg(
                        child.slug
                        ORDER BY child.sort_order ASC, child.name ASC, child.external_id ASC
                    )::text
                    FROM {$table} child
                    WHERE child.is_active = true
                      AND child.parent_external_id = selected.external_id
                ), '[]') AS children_slugs_json
            FROM selected
            ORDER BY
                CASE selected.relation_type
                    WHEN 'parent' THEN 1
                    WHEN 'target' THEN 2
                    ELSE 3
                END ASC,
                selected.relation_order ASC,
                selected.level ASC,
                selected.sort_order ASC,
                selected.name ASC,
                selected.external_id ASC
            ",
            [
                'slug' => $slug,
            ],
            [
                'slug' => ParameterType::STRING,
            ]
        );
    }

    public function insertRaw(
        string $uuid,
        string $tableName,
        string $externalId,
        ?string $parentExternalId,
        string $name,
        string $slug,
        int $lineNumber
    ): void {
        $table = $this->schemaSqlHelper->table($tableName);

        $sql = "
            INSERT INTO {$table}
                (external_id, uuid, parent_external_id, name, slug, created_at, updated_at)
            VALUES
                (:external_id, :uuid, :parent_external_id, :name, :slug, NOW(), NOW())
        ";

        $this->connection->executeStatement($sql, [
            'external_id' => $externalId,
            'uuid' => $uuid,
            'parent_external_id' => $parentExternalId,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    /**
     * POST-PROCESS: level + is_leaf
     */
    public function recalculateHierarchy(string $tableName): void
    {
        $table = $this->schemaSqlHelper->table($tableName);

        /**
         * 1. level = depth from root (recursive CTE)
         */
        $this->connection->executeStatement("
            WITH RECURSIVE tree AS (
                SELECT
                    id,
                    external_id,
                    parent_external_id,
                    1 AS level
                FROM {$table}
                WHERE parent_external_id IS NULL

                UNION ALL

                SELECT
                    c.id,
                    c.external_id,
                    c.parent_external_id,
                    t.level + 1
                FROM {$table} c
                JOIN tree t ON t.external_id = c.parent_external_id
            )
            UPDATE {$table} c
            SET level = tree.level
            FROM tree
            WHERE c.id = tree.id
        ");

        /**
         * 2. is_leaf = no children
         */
        $this->connection->executeStatement("
            UPDATE {$table} c
            SET is_leaf = NOT EXISTS (
                SELECT 1
                FROM {$table} child
                WHERE child.parent_external_id = c.external_id
            )
        ");
    }

    /**
     * Строит карту "external_id узла" => "полный путь до узла" (Родитель > Ребёнок > ...)
     * для указанной таблицы категорий.
     *
     * @return array<string, array{fullPath: string}> keyed by external_id
     */
    public function findFullPathsByTable(string $tableName): array
    {
        $table = $this->schemaSqlHelper->table($tableName);

        $rows = $this->connection->fetchAllAssociative(
            "
            WITH RECURSIVE category_path AS (
                SELECT
                    external_id,
                    name::text AS full_path
                FROM {$table}
                WHERE (parent_external_id IS NULL OR parent_external_id = '')
                  AND external_id IS NOT NULL AND external_id <> ''

                UNION ALL

                SELECT
                    c.external_id,
                    cp.full_path || ' > ' || c.name AS full_path
                FROM {$table} c
                INNER JOIN category_path cp ON c.parent_external_id = cp.external_id
                WHERE c.external_id IS NOT NULL AND c.external_id <> ''
            )
            SELECT external_id, full_path
            FROM category_path
            "
        );

        $paths = [];

        foreach ($rows as $row) {
            $paths[(string) $row['external_id']] = [
                'fullPath' => $row['full_path'],
            ];
        }

        return $paths;
    }

    /**
     * Возвращает список всех external_id для узла и его потомков.
     *
     * @return string[]
     */
    public function findDescendantExternalIds(string $tableName, string $externalId): array
    {
        $table = $this->schemaSqlHelper->table($tableName);

        $rows = $this->connection->fetchFirstColumn(
            "
            WITH RECURSIVE descendants AS (
                SELECT external_id
                FROM {$table}
                WHERE external_id = :externalId

                UNION ALL

                SELECT c.external_id
                FROM {$table} c
                INNER JOIN descendants d ON c.parent_external_id = d.external_id
            )
            SELECT external_id FROM descendants
            ",
            ['externalId' => $externalId],
            ['externalId' => ParameterType::STRING]
        );

        return array_map('strval', $rows);
    }
}
