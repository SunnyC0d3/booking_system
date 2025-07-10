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

        $cleaned = str_replace([' and ', ' AND '], ' ', $cleaned);
        $cleaned = str_replace([' or ', ' OR '], ' |', $cleaned);
        $cleaned = str_replace([' not ', ' NOT ', ' -'], ' -', $cleaned);

        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    protected function extractTerms(string $query): array
    {
        $cleaned = $this->cleanQuery($query);

        $withoutPhrases = preg_replace('/"[^"]*"/', '', $cleaned);

        $terms = preg_split('/\s+/', $withoutPhrases);

        $terms = array_filter($terms, function($term) {
            $term = trim($term);
            if (empty($term) || in_array($term, ['+', '-', '|', '&', '(', ')'])) {
                return false;
            }
            return true;
        });

        $cleanedTerms = [];
        foreach ($terms as $term) {
            $cleanTerm = ltrim($term, '+-|&');
            if (!empty($cleanTerm) && strlen($cleanTerm) >= 2) {
                $cleanedTerms[] = $cleanTerm;
            }
        }

        return array_values(array_unique($cleanedTerms));
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

        $terms = $this->extractTerms($query);
        if (count($terms) > 1) {
            for ($i = 0; $i < count($terms) - 1; $i++) {
                $operators['and'][] = [
                    'left' => $terms[$i],
                    'right' => $terms[$i + 1]
                ];
            }
        }

        if (preg_match_all('/(\w+)\s+(?:or|OR|\|)\s+(\w+)/', $query, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $operators['or'][] = [
                    'left' => strtolower($matches[1][$i]),
                    'right' => strtolower($matches[2][$i])
                ];
            }
        }

        if (preg_match_all('/(?:not|NOT|\-)\s+(\w+)/', $query, $matches)) {
            $operators['not'] = array_map('strtolower', $matches[1]);
        }

        return $operators;
    }

    protected function extractFilters(string $query): array
    {
        $filters = [];

        // Price filters
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

        // Color filters
        $colors = ['red', 'blue', 'green', 'black', 'white', 'yellow', 'orange', 'purple', 'pink', 'brown', 'gray', 'grey'];
        foreach ($colors as $color) {
            if (stripos($query, $color) !== false) {
                $filters['colors'][] = $color;
            }
        }

        // Size filters
        $sizes = ['xs', 'small', 'medium', 'large', 'xl', 'xxl', 's', 'm', 'l'];
        foreach ($sizes as $size) {
            if (preg_match('/\b' . preg_quote($size, '/') . '\b/i', $query)) {
                $filters['sizes'][] = $size;
            }
        }

        // Brand filters
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

        $terms = $this->extractTerms($query);
        $phrases = $this->extractPhrases($query);

        $booleanParts = [];

        foreach ($phrases as $phrase) {
            $booleanParts[] = '"' . $phrase . '"';
        }

        foreach ($terms as $term) {
            if (strlen($term) >= 2) {
                $booleanParts[] = '+' . $term;
            }
        }

        return implode(' ', $booleanParts);
    }

    protected function buildFulltextQuery(string $query): string
    {
        $phrases = $this->extractPhrases($query);
        $terms = $this->extractTerms($query);

        $fulltextParts = [];

        foreach ($phrases as $phrase) {
            if (!empty(trim($phrase))) {
                $fulltextParts[] = '"' . addslashes(trim($phrase)) . '"';
            }
        }

        foreach ($terms as $term) {
            $cleanTerm = $this->cleanTermForFulltext($term);
            if ($cleanTerm && strlen($cleanTerm) >= 2) {
                $fulltextParts[] = '+' . $cleanTerm . '*';
            }
        }

        return implode(' ', $fulltextParts);
    }

    protected function cleanTermForFulltext(string $term): string
    {
        $term = ltrim($term, '+-|&');

        $term = preg_replace('/[^\w\s]/', '', $term);
        $term = trim($term);

        $term = str_replace(['+', '-', '~', '<', '>', '(', ')', '"', '*'], '', $term);

        return $term;
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
