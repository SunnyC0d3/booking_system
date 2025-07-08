<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\V1\Search\QueryProcessor;
use App\Services\V1\Search\SearchRanker;
use App\Services\V1\Products\Product;
use App\Services\V1\Media\SecureMedia;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueryProcessor::class, function ($app) {
            return new QueryProcessor();
        });

        $this->app->singleton(SearchRanker::class, function ($app) {
            return new SearchRanker();
        });

        $this->app->bind(Product::class, function ($app) {
            return new Product(
                $app->make(SecureMedia::class),
                $app->make(QueryProcessor::class),
                $app->make(SearchRanker::class)
            );
        });

        $this->app->singleton('search.config', function () {
            return config('search');
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/search.php' => config_path('search.php'),
        ], 'search-config');
    }

    public function provides(): array
    {
        return [
            QueryProcessor::class,
            SearchRanker::class,
            Product::class,
            'search.config',
        ];
    }
}
