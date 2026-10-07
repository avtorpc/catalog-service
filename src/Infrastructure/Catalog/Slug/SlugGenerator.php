<?php

namespace App\Infrastructure\Catalog\Slug;

use Ramsey\Uuid\Uuid;

final class SlugGenerator
{
    private const UUID_NAMESPACE = '8d7e82d8-c4d8-4d62-a4d7-6d0ec4f88d2f';

    private const MAP = [
        // RU → EN basic translit
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

        ' ' => '_', '-' => '_', '.' => '_', ',' => '',
        '/' => '_', '\\' => '_',
    ];

    public function generate(string $name): string
    {
        $name = mb_strtolower(trim($name));

        $slug = strtr($name, self::MAP);

        $slug = preg_replace('~[^a-z0-9_]+~', '_', $slug);
        $slug = preg_replace('~_+~', '_', $slug);

        return trim($slug, '_');
    }

    public function generateUuid(string $slug): string
    {
        return Uuid::fromString(
            Uuid::uuid5(
                Uuid::fromString(self::UUID_NAMESPACE),
                $slug,
            )->toString()
        );
    }
}
