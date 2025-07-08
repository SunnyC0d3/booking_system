<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search engine that will be used
    | for product searches. You can set this to any of the engines
    | defined in the "engines" array below.
    |
    */

    'default_engine' => env('SEARCH_ENGINE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Search Engines
    |--------------------------------------------------------------------------
    |
    | Here you may configure the search engines that your application uses.
    | Each engine can have its own configuration options.
    |
    */

    'engines' => [
        'database' => [
            'driver' => 'mysql_fulltext',
            'min_word_length' => 3,
            'enable_boolean_mode' => true,
            'enable_wildcard_search' => true,
            'relevance_threshold' => 0.1,
        ],

        'elasticsearch' => [
            'driver' => 'elasticsearch',
            'host' => env('ELASTICSEARCH_HOST', 'localhost:9200'),
            'index' => env('ELASTICSEARCH_INDEX', 'products'),
            'username' => env('ELASTICSEARCH_USERNAME'),
            'password' => env('ELASTICSEARCH_PASSWORD'),
        ],

        'algolia' => [
            'driver' => 'algolia',
            'app_id' => env('ALGOLIA_APP_ID'),
            'secret' => env('ALGOLIA_SECRET'),
            'index' => env('ALGOLIA_INDEX', 'products'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Performance
    |--------------------------------------------------------------------------
    |
    | Configuration options for search performance optimization.
    |
    */

    'performance' => [
        'max_results_per_page' => 100,
        'default_results_per_page' => 15,
        'search_timeout_seconds' => 5,
        'enable_query_cache' => env('SEARCH_CACHE_ENABLED', true),
        'cache_ttl_seconds' => env('SEARCH_CACHE_TTL', 3600),
        'slow_query_threshold_ms' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific search features.
    |
    */

    'features' => [
        'typo_tolerance' => true,
        'synonym_matching' => false, // Phase 3 feature
        'auto_complete' => false,    // Phase 3 feature
        'faceted_search' => true,
        'result_highlighting' => false, // Phase 3 feature
        'personalization' => true,
        'result_diversification' => true,
        'search_explanations' => env('APP_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ranking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how search results are ranked and scored.
    |
    */

    'ranking' => [
        'boost_factors' => [
            'exact_name_match' => 100,
            'name_starts_with' => 75,
            'name_contains' => 50,
            'description_contains' => 25,
            'in_stock' => 20,
            'high_quantity' => 15,
            'recent_product' => 10,
            'has_variants' => 5,
            'has_media' => 5,
        ],

        'penalty_factors' => [
            'out_of_stock' => -50,
            'low_stock' => -10,
            'inactive_status' => -100,
            'old_product' => -5,
        ],

        'intelligence_factors' => [
            'user_category_preference' => 25,
            'user_brand_preference' => 20,
            'price_range_preference' => 15,
            'popularity_boost' => 20,
            'trending_boost' => 15,
            'seasonal_boost' => 10,
        ],

        'business_factors' => [
            'featured_product' => 30,
            'sale_product' => 20,
            'new_arrival' => 25,
            'high_margin' => 0, // Configure based on your business needs
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Facet Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which facets are available and how they're generated.
    |
    */

    'facets' => [
        'enabled' => true,
        'cache_ttl_seconds' => 300,
        'max_categories' => 10,
        'max_brands' => 10,
        'max_attributes_per_type' => 15,
        'price_range_buckets' => 6,

        'available_facets' => [
            'price_ranges',
            'categories',
            'brands',
            'availability',
            'attributes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Processing
    |--------------------------------------------------------------------------
    |
    | Configure how search queries are processed and cleaned.
    |
    */

    'query_processing' => [
        'max_query_length' => 500,
        'min_term_length' => 2,
        'enable_stemming' => false, // Phase 3 feature
        'remove_stop_words' => true,
        'expand_synonyms' => false, // Phase 3 feature

        'stop_words' => [
            'the', 'and', 'for', 'with', 'from', 'this', 'that',
            'are', 'was', 'will', 'been', 'have', 'has', 'had',
            'but', 'not', 'can', 'could', 'should', 'would',
        ],

        'boolean_operators' => [
            'and' => ['+', 'AND', 'and'],
            'or' => ['|', 'OR', 'or'],
            'not' => ['-', 'NOT', 'not'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Result Diversification
    |--------------------------------------------------------------------------
    |
    | Configure how search results are diversified to show variety.
    |
    */

    'diversification' => [
        'enabled' => true,
        'max_per_category' => 5,
        'max_per_brand' => 3,
        'max_similar_products' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Analytics
    |--------------------------------------------------------------------------
    |
    | Configuration for search analytics and monitoring (Phase 2).
    |
    */

    'analytics' => [
        'enabled' => env('SEARCH_ANALYTICS_ENABLED', false),
        'log_all_queries' => env('SEARCH_LOG_ALL_QUERIES', false),
        'log_zero_results' => true,
        'log_slow_queries' => true,
        'retention_days' => env('SEARCH_ANALYTICS_RETENTION', 90),
        'sample_rate' => 1.0, // Log 100% of queries (reduce for high traffic)
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-complete Settings (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Configuration for search auto-complete functionality.
    |
    */

    'autocomplete' => [
        'enabled' => false,
        'min_query_length' => 2,
        'max_suggestions' => 10,
        'cache_ttl_seconds' => 3600,
        'include_products' => true,
        'include_categories' => true,
        'include_brands' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Suggestions (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Configuration for "did you mean" suggestions.
    |
    */

    'suggestions' => [
        'enabled' => true,
        'levenshtein_threshold' => 2,
        'max_suggestions' => 5,
        'cache_ttl_seconds' => 1800,

        'common_typos' => [
            'headfones' => 'headphones',
            'computor' => 'computer',
            'phon' => 'phone',
            'labtop' => 'laptop',
            'accesories' => 'accessories',
            'electronis' => 'electronics',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration for search functionality.
    |
    */

    'security' => [
        'sanitize_queries' => true,
        'max_concurrent_searches_per_ip' => 10,
        'rate_limit_enabled' => true,
        'blocked_terms' => [
            // Add any terms you want to block from searches
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for development and debugging features.
    |
    */

    'debug' => [
        'log_queries' => env('SEARCH_DEBUG_LOG_QUERIES', false),
        'log_performance' => env('SEARCH_DEBUG_PERFORMANCE', false),
        'explain_scores' => env('SEARCH_DEBUG_EXPLAIN', false),
        'log_facet_generation' => false,
    ],
];
