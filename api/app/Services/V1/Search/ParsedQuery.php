<?php

namespace App\Services\V1\Search;

class ParsedQuery
{
    public string $original;
    public string $cleaned;
    public array $terms;
    public array $phrases;
    public array $operators;
    public array $filters;
    public string $boolean_query;
    public string $fulltext_query;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function hasTerms(): bool
    {
        return !empty($this->terms) || !empty($this->phrases);
    }

    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    public function getAllTerms(): array
    {
        return array_merge($this->terms, $this->phrases);
    }

    public function getLength(): int
    {
        return strlen($this->original);
    }

    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'cleaned' => $this->cleaned,
            'terms' => $this->terms,
            'phrases' => $this->phrases,
            'operators' => $this->operators,
            'filters' => $this->filters,
            'boolean_query' => $this->boolean_query,
            'fulltext_query' => $this->fulltext_query,
            'has_terms' => $this->hasTerms(),
            'has_filters' => $this->hasFilters(),
            'length' => $this->getLength(),
        ];
    }
}
