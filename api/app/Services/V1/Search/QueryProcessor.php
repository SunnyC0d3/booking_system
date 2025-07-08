<?php

namespace App\Services\V1\Search;

use Illuminate\Support\Str;

class QueryProcessor
{
    public function parseQuery(string $query): ParsedQuery
    {
        $query = $this->sanitizeQuery($query);

        return new ParsedQuery([
            'original' => $query,
            'cleaned' => $this->cleanQuery($query),
            'terms' => $this->extractTerms($query),
            'phrases' => $this->extractPhrases($query),
            'operators' => $this->extractOperators($query),
            'filters' => $this->extractFilters($query),
            'boolean_query' => $this->buildBooleanQuery($query),
            'fulltext_query' => $this->buildFulltextQuery($query),
        ]);
    }

    public function sanitizeQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

        $query = preg_replace('/\s+/', ' ', $query);

        $query = trim($query);
        $query = Str::limit($query, 500, '');

        return $query;
    }

    protected function cleanQuery(string $query): string
    {
        $cleaned = Str::lower($query);

        $cleaned = preg_replace('/[^\w\s\-\+\*\(\)\"\'&|!]/', ' ', $cleaned);

        $cleaned = str_replace([' and ', ' AND '], ' +', $cleaned);
        $cleaned = str_replace([' or ', ' OR '], ' |', $cleaned);
        $cleaned = str_replace([' not ', ' NOT ', ' -'], ' -', $cleaned);

        return trim($cleaned);
    }

    protected function extractTerms(string $query): array
    {
        $cleaned = $this->cleanQuery($query);

        $withoutPhrases = preg_replace('/"[^"]*"/', '', $cleaned);

        $terms = preg_split('/\s+/', $withoutPhrases);

        $terms = array_filter($terms, function($term) {
            return !empty($term) && !in_array($term, ['+', '-', '|', '&', '(', ')']);
        });

        return array_values($terms);
    }

    protected function extractPhrases(string $query): array
    {
        preg_match_all('/"([^"]+)"/', $query, $matches);
        return $matches[1] ?? [];
    }

    protected function extractOperators(string $query): array
    {
        $operators = [
            'and' => [],
            'or' => [],
            'not' => [],
        ];

        if (preg_match_all('/(\w+)\s+(?:and|AND|\+)\s+(\w+)/', $query, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $operators['and'][] = [
                    'left' => $matches[1][$i],
                    'right' => $matches[2][$i]
                ];
            }
        }

        if (preg_match_all('/(\w+)\s+(?:or|OR|\|)\s+(\w+)/', $query, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $operators['or'][] = [
                    'left' => $matches[1][$i],
                    'right' => $matches[2][$i]
                ];
            }
        }

        if (preg_match_all('/(?:not|NOT|\-)\s+(\w+)/', $query, $matches)) {
            $operators['not'] = $matches[1];
        }

        return $operators;
    }

    protected function extractFilters(string $query): array
    {
        $filters = [];

        if (preg_match('/(?:under|below|less than|<)\s*\$?(\d+(?:\.\d{2})?)/', $query, $matches)) {
            $filters['price_max'] = floatval($matches[1]) * 100;
        }

        if (preg_match('/(?:over|above|more than|>)\s*\$?(\d+(?:\.\d{2})?)/', $query, $matches)) {
            $filters['price_min'] = floatval($matches[1]) * 100;
        }

        if (preg_match('/\$?(\d+(?:\.\d{2})?)\s*(?:to|-)\s*\$?(\d+(?:\.\d{2})?)/', $query, $matches)) {
            $filters['price_min'] = floatval($matches[1]) * 100;
            $filters['price_max'] = floatval($matches[2]) * 100;
        }

        $colors = ['red', 'blue', 'green', 'black', 'white', 'yellow', 'orange', 'purple', 'pink', 'brown', 'gray', 'grey'];
        foreach ($colors as $color) {
            if (stripos($query, $color) !== false) {
                $filters['colors'][] = $color;
            }
        }

        $sizes = ['xs', 'small', 'medium', 'large', 'xl', 'xxl', 's', 'm', 'l'];
        foreach ($sizes as $size) {
            if (preg_match('/\b' . preg_quote($size, '/') . '\b/i', $query)) {
                $filters['sizes'][] = $size;
            }
        }

        $brands = ['apple', 'samsung', 'nike', 'adidas', 'sony', 'lg', 'hp', 'dell', 'microsoft'];
        foreach ($brands as $brand) {
            if (stripos($query, $brand) !== false) {
                $filters['brands'][] = $brand;
            }
        }

        return $filters;
    }

    protected function buildBooleanQuery(string $query): string
    {
        $cleaned = $this->cleanQuery($query);

        $booleanQuery = str_replace([' +', ' |', ' -'], [' +', ' ', ' -'], $cleaned);

        $booleanQuery = preg_replace('/"([^"]+)"/', '"$1"', $booleanQuery);

        $booleanQuery = str_replace('*', '*', $booleanQuery);

        return $booleanQuery;
    }

    protected function buildFulltextQuery(string $query): string
    {
        $parsed = $this->parseQuery($query);

        $fulltextParts = [];

        foreach ($parsed->phrases as $phrase) {
            $fulltextParts[] = '"' . $phrase . '"';
        }

        foreach ($parsed->terms as $term) {
            if (strlen($term) >= 3) {
                $fulltextParts[] = '+' . $term . '*';
            }
        }

        return implode(' ', $fulltextParts);
    }

    public function calculateComplexity(string $query): array
    {
        $parsed = $this->parseQuery($query);

        $complexity = [
            'score' => 0,
            'factors' => [],
            'suggestions' => []
        ];

        $complexity['score'] += count($parsed->terms) * 1;
        $complexity['score'] += count($parsed->phrases) * 2;
        $complexity['score'] += count($parsed->operators['and']) * 1;
        $complexity['score'] += count($parsed->operators['or']) * 2;
        $complexity['score'] += count($parsed->operators['not']) * 1;

        if (count($parsed->terms) > 5) {
            $complexity['factors'][] = 'many_terms';
            $complexity['suggestions'][] = 'Consider using more specific terms';
        }

        if (count($parsed->phrases) > 2) {
            $complexity['factors'][] = 'many_phrases';
            $complexity['suggestions'][] = 'Multiple phrases may slow down search';
        }

        if (count($parsed->operators['or']) > 3) {
            $complexity['factors'][] = 'many_or_operators';
            $complexity['suggestions'][] = 'Many OR operations increase query time';
        }

        return $complexity;
    }
}
