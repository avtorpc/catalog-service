<?php

namespace App\Application\ImportProducts\Matcher;

final class ProductCategoryMatcher
{
    /** @var array<string, array{path: string, tokens: string[]}> */
    private array $categoryPaths = [];

    /**
     * @param array<string, array{fullPath: string}> $categoryPaths keyed by external_id
     */
    public function loadCategoryPaths(array $categoryPaths): void
    {
        $this->categoryPaths = [];

        foreach ($categoryPaths as $externalId => $data) {
            $tokens = $this->tokenize($data['fullPath']);

            if (count($tokens) === 0) {
                continue;
            }

            $this->categoryPaths[$externalId] = [
                'path' => $data['fullPath'],
                'tokens' => $tokens,
            ];
        }
    }

    /**
     * @return array{categoryId: string|null, percent: float, fullPath: string|null}
     */
    public function findBestMatch(string $productName): array
    {
        $bestId = null;
        $bestPercent = 0.0;
        $bestPath = null;

        $productTokens = $this->tokenize($productName);

        if (count($productTokens) === 0) {
            return [
                'categoryId' => null,
                'percent' => 0.0,
                'fullPath' => null,
            ];
        }

        foreach ($this->categoryPaths as $externalId => $data) {
            $common = array_intersect($productTokens, $data['tokens']);
            $percent = count($common) / count($productTokens) * 100;

            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $bestId = $externalId;
                $bestPath = $data['path'];
            }
        }

        return [
            'categoryId' => $bestId,
            'percent' => round($bestPercent, 2),
            'fullPath' => $bestPath,
        ];
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));

        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $words = array_filter($words, fn(string $w) => mb_strlen($w) > 1);

        return array_values(array_unique($words));
    }
}
