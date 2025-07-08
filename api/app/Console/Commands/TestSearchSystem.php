<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\V1\Search\QueryProcessor;
use App\Services\V1\Search\SearchRanker;
use App\Filters\V1\ProductFilter;
use App\Models\Product;
use Illuminate\Http\Request;

class TestSearchSystem extends Command
{
    protected $signature = 'search:test {query?} {--limit=10} {--explain} {--facets}';

    protected $description = 'Test the enhanced search system with various queries';

    protected QueryProcessor $queryProcessor;
    protected SearchRanker $searchRanker;

    public function __construct(QueryProcessor $queryProcessor, SearchRanker $searchRanker)
    {
        parent::__construct();
        $this->queryProcessor = $queryProcessor;
        $this->searchRanker = $searchRanker;
    }

    public function handle(): int
    {
        $this->info('🔍 Enhanced Search System Test');
        $this->line('=====================================');

        $query = $this->argument('query') ?: $this->ask('Enter search query');

        if (empty($query)) {
            $this->error('No search query provided');
            return 1;
        }

        $limit = $this->option('limit');
        $explain = $this->option('explain');
        $facets = $this->option('facets');

        $this->line('');
        $this->info("Testing search for: \"$query\"");
        $this->line('');

        $this->testQueryProcessing($query);
        $this->testDatabaseSearch($query, $limit, $explain);
        $this->testSearchPerformance($query);

        if ($facets) {
            $this->testFacetGeneration($query);
        }

        $this->testSearchQuality($query);

        $this->line('');
        $this->info('✅ Search system test completed!');

        return 0;
    }

    protected function testQueryProcessing(string $query): void
    {
        $this->line('📝 Testing Query Processing...');
        $this->line('─────────────────────────────');

        try {
            $startTime = microtime(true);
            $parsed = $this->queryProcessor->parseQuery($query);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->table(
                ['Property', 'Value'],
                [
                    ['Original Query', $parsed->original],
                    ['Cleaned Query', $parsed->cleaned],
                    ['Terms', implode(', ', $parsed->terms)],
                    ['Phrases', implode(', ', $parsed->phrases)],
                    ['Boolean Query', $parsed->boolean_query],
                    ['Fulltext Query', $parsed->fulltext_query],
                    ['Processing Time', $processingTime . ' ms'],
                ]
            );

            if (!empty($parsed->filters)) {
                $this->line('');
                $this->line('🔍 Extracted Filters:');
                foreach ($parsed->filters as $key => $value) {
                    if (is_array($value)) {
                        $this->line("  - $key: " . implode(', ', $value));
                    } else {
                        $this->line("  - $key: $value");
                    }
                }
            }

            $complexity = $this->queryProcessor->calculateComplexity($query);
            $this->line('');
            $this->info("Query Complexity Score: {$complexity['score']}");

            if (!empty($complexity['suggestions'])) {
                $this->line('💡 Optimization Suggestions:');
                foreach ($complexity['suggestions'] as $suggestion) {
                    $this->line("  - $suggestion");
                }
            }

        } catch (\Exception $e) {
            $this->error("Query processing failed: " . $e->getMessage());
        }

        $this->line('');
    }

    protected function testDatabaseSearch(string $query, int $limit, bool $explain): void
    {
        $this->line('🗄️  Testing Database Search...');
        $this->line('─────────────────────────────');

        try {
            $startTime = microtime(true);

            $request = Request::create('/', 'GET', [
                'filter' => ['search' => $query],
                'per_page' => $limit,
                'explain' => $explain
            ]);

            $filter = new ProductFilter($request);

            $queryBuilder = Product::with([
                'vendor:id,name',
                'category:id,name',
                'productStatus:id,name',
                'variants:id,product_id,value,additional_price,quantity',
                'tags:id,name'
            ])->filter($filter);

            $products = $queryBuilder->limit($limit)->get();
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("Found {$products->count()} products in {$searchTime} ms");

            if ($products->isEmpty()) {
                $this->warn('No products found for this query');
                return;
            }

            $rankingStart = microtime(true);
            $rankedProducts = $this->searchRanker->rankSearchResults($products, $query, [
                'explain' => $explain,
                'diversify' => false
            ]);
            $rankingTime = round((microtime(true) - $rankingStart) * 1000, 2);

            $this->info("Ranking applied in {$rankingTime} ms");

            $this->line('');
            $this->line('🏆 Top Results:');

            $tableData = [];
            foreach ($rankedProducts->take(min(5, $limit)) as $index => $product) {
                $score = $product->final_score ?? $product->calculated_relevance_score ?? 0;
                $tableData[] = [
                    $index + 1,
                    $product->id,
                    substr($product->name, 0, 40) . (strlen($product->name) > 40 ? '...' : ''),
                    '£' . number_format($product->price / 100, 2),
                    $product->quantity,
                    round($score, 2)
                ];
            }

            $this->table(
                ['Rank', 'ID', 'Name', 'Price', 'Stock', 'Score'],
                $tableData
            );

            if ($explain && isset($rankedProducts->first()->search_explanations)) {
                $this->line('');
                $this->line('📊 Search Explanations (Top Result):');
                $topProduct = $rankedProducts->first();
                foreach ($topProduct->search_explanations as $explanation) {
                    $this->line("  • $explanation");
                }
            }

        } catch (\Exception $e) {
            $this->error("Database search failed: " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
        }

        $this->line('');
    }

    protected function testSearchPerformance(string $originalQuery): void
    {
        $this->line('⚡ Testing Search Performance...');
        $this->line('─────────────────────────────');

        $testQueries = [
            $originalQuery,
            substr($originalQuery, 0, 3),
            $originalQuery . ' wireless bluetooth',
            '"' . $originalQuery . '"',
        ];

        $performanceData = [];

        foreach ($testQueries as $testQuery) {
            try {
                $startTime = microtime(true);

                $request = Request::create('/', 'GET', [
                    'filter' => ['search' => $testQuery],
                    'per_page' => 10
                ]);

                $filter = new ProductFilter($request);
                $count = Product::filter($filter)->count();

                $queryTime = round((microtime(true) - $startTime) * 1000, 2);

                $performanceData[] = [
                    substr($testQuery, 0, 30) . (strlen($testQuery) > 30 ? '...' : ''),
                    $count,
                    $queryTime . ' ms'
                ];

            } catch (\Exception $e) {
                $performanceData[] = [
                    substr($testQuery, 0, 30) . '...',
                    'Error',
                    'Failed'
                ];
            }
        }

        $this->table(
            ['Query', 'Results', 'Time'],
            $performanceData
        );

        $this->line('');
    }

    protected function testFacetGeneration(string $query): void
    {
        $this->line('📊 Testing Facet Generation...');
        $this->line('─────────────────────────────');

        try {
            $startTime = microtime(true);

            $request = Request::create('/', 'GET', [
                'filter' => ['search' => $query]
            ]);
            $filter = new ProductFilter($request);
            $baseQuery = Product::filter($filter);

            $categoryFacets = $baseQuery->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
                ->groupBy('product_categories.id', 'product_categories.name')
                ->selectRaw('product_categories.name, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            $vendorFacets = Product::filter($filter)
                ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
                ->groupBy('vendors.id', 'vendors.name')
                ->selectRaw('vendors.name, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            $facetTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("Facets generated in {$facetTime} ms");

            if ($categoryFacets->isNotEmpty()) {
                $this->line('');
                $this->line('🏷️  Category Facets:');
                foreach ($categoryFacets as $facet) {
                    $this->line("  • {$facet->name} ({$facet->count})");
                }
            }

            if ($vendorFacets->isNotEmpty()) {
                $this->line('');
                $this->line('🏢 Vendor Facets:');
                foreach ($vendorFacets as $facet) {
                    $this->line("  • {$facet->name} ({$facet->count})");
                }
            }

        } catch (\Exception $e) {
            $this->error("Facet generation failed: " . $e->getMessage());
        }

        $this->line('');
    }

    protected function testSearchQuality(string $query): void
    {
        $this->line('📈 Testing Search Quality...');
        $this->line('─────────────────────────────');

        try {
            $request = Request::create('/', 'GET', [
                'filter' => ['search' => $query],
                'per_page' => 20
            ]);

            $filter = new ProductFilter($request);
            $products = Product::filter($filter)->get();

            $rankedProducts = $this->searchRanker->rankSearchResults($products, $query, []);

            $qualityMetrics = $this->searchRanker->calculateSearchQuality($rankedProducts, $query);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Results', $qualityMetrics['total_results']],
                    ['Relevant Results', $qualityMetrics['relevant_results']],
                    ['In Stock Results', $qualityMetrics['in_stock_results']],
                    ['Quality Score', $qualityMetrics['quality_score'] . '%'],
                    ['Average Relevance', round($qualityMetrics['avg_relevance_score'], 2)],
                    ['Query Length', $qualityMetrics['query_length']],
                    ['Has Results', $qualityMetrics['has_results'] ? 'Yes' : 'No'],
                ]
            );

            $this->line('');
            if ($qualityMetrics['quality_score'] >= 80) {
                $this->info('🟢 Excellent search quality');
            } elseif ($qualityMetrics['quality_score'] >= 60) {
                $this->comment('🟡 Good search quality');
            } elseif ($qualityMetrics['quality_score'] >= 40) {
                $this->warn('🟠 Fair search quality');
            } else {
                $this->error('🔴 Poor search quality');
            }

            if ($qualityMetrics['total_results'] === 0) {
                $this->line('💡 Suggestions:');
                $this->line('  • Check if products exist matching this query');
                $this->line('  • Verify search indexes are properly created');
                $this->line('  • Consider implementing typo tolerance');
            } elseif ($qualityMetrics['quality_score'] < 60) {
                $this->line('💡 Suggestions:');
                $this->line('  • Improve relevance scoring algorithm');
                $this->line('  • Add more product metadata for matching');
                $this->line('  • Implement synonym matching');
            }

        } catch (\Exception $e) {
            $this->error("Quality testing failed: " . $e->getMessage());
        }

        $this->line('');
    }
}
