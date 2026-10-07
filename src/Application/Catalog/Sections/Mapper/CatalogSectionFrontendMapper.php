<?php

namespace App\Application\Catalog\Sections\Mapper;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CatalogSectionFrontendMapper
{
    public function __construct(
        #[Autowire('%catalog.section.link_prefix%')]
        private string $sectionLinkPrefix,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function mapItem(array $row): array
    {
        return [
            'id' => (string) $row['external_id'],
            'parentId' => $row['parent_external_id'] !== null ? (string) $row['parent_external_id'] : null,
            'level' => (int) $row['level'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'nodeType' => 'folder',
            'imageUrl' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => $this->toBool($row['is_active']),
            'isLeaf' => $this->toBool($row['is_leaf']),
            'parent' => $this->buildSectionLinks(
                $this->decodeJsonArray($row['parent_slugs_json'] ?? '[]')
            ),
            'children' => $this->buildSectionLinks(
                $this->decodeJsonArray($row['children_slugs_json'] ?? '[]')
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map('strval', $decoded));
    }

    /**
     * @param array<int, string> $slugs
     *
     * @return array<int, string>
     */
    private function buildSectionLinks(array $slugs): array
    {
        return array_map(
            fn (string $slug): string => $this->buildSectionLink($slug),
            $slugs
        );
    }

    private function buildSectionLink(string $slug): string
    {
        $prefix = trim($this->sectionLinkPrefix, '/');

        if ($prefix === '') {
            return $slug;
        }

        return $prefix . '/' . ltrim($slug, '/');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 't', 'true', 'TRUE'], true);
    }
}
