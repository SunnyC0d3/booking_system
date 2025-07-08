<?php

namespace App\Http\Controllers\V1\Public;

use App\Models\Product as ProdDB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Filters\V1\ProductFilter;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\FilterProductRequest;
use App\Services\V1\Products\Product;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    use ApiResponses;

    private Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Search and browse products with advanced features
     *
     * Get a paginated and filtered list of active products with advanced search capabilities.
     * This endpoint supports intelligent search with relevance scoring, faceted filtering,
     * result diversification, and personalized recommendations. Includes comprehensive
     * product information optimized for customer browsing and purchasing decisions.
     *
     * @group Product Catalog
     * @unauthenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[search] string optional Intelligent search with typo tolerance and relevance scoring. Example: wireless bluetooth headphones under £50
     * @queryParam filter[name] string optional Filter products by name (partial match supported). Example: wireless
     * @queryParam filter[price] string optional Filter by price range in pounds. Single value or comma-separated min,max. Example: 10.00,50.00
     * @queryParam filter[priceRanges] string optional Multiple price ranges. Example: 0-25,50-100
     * @queryParam filter[category] string optional Filter by category ID(s) including child categories. Example: 5,12,18
     * @queryParam filter[availability] string optional Filter by stock status. Options: in_stock, available. Example: in_stock
     * @queryParam filter[vendors] string optional Filter by brand/vendor ID(s). Example: 2,5,8
     * @queryParam filter[vendor] string optional Filter by brand name. Example: apple
     * @queryParam filter[tags] string optional Filter by tag ID(s). Example: 3,7,12
     * @queryParam filter[attributes] string optional Filter by product attributes. Format: attribute:value. Example: color:red,size:large
     * @queryParam filter[created_at] string optional Filter by creation date range. Example: 2025-01-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Options: vendor,variants,category,tags,media. Example: vendor,variants,category
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of products per page (max 100). Default: 15. Example: 24
     * @queryParam sort string optional Sort products. Available: name, price, created_at, relevance. Example: -relevance,price
     * @queryParam diversify boolean optional Apply result diversification for better variety. Default: false. Example: true
     */
    public function index(FilterProductRequest $request, ProductFilter $filter)
    {
        try {
            return $this->product->search($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve detailed product information
     *
     * Get comprehensive information about a specific product including all variants, media,
     * category details, vendor information, and customer-relevant data. This endpoint provides
     * complete product data needed for product detail pages, including high-resolution images,
     * pricing for all variants, inventory levels, and related product suggestions.
     *
     * @group Product Catalog
     * @unauthenticated
     *
     * @urlParam product integer required The ID of the product to retrieve. Example: 15
     */
    public function show(Request $request, ProdDB $product)
    {
        try {
            if (!$product->productStatus || $product->productStatus->name !== 'Active') {
                return $this->error('Product is not available for viewing', 404);
            }

            $this->trackProductView($request, $product);

            return $this->product->find($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    protected function trackProductView(Request $request, ProdDB $product): void
    {
        try {
            $viewedProducts = session('viewed_products', []);
            $viewedCategories = session('viewed_categories', []);
            $viewedBrands = session('viewed_brands', []);

            if (!in_array($product->id, $viewedProducts)) {
                $viewedProducts[] = $product->id;
                session(['viewed_products' => array_slice($viewedProducts, -20)]);
            }

            if ($product->product_category_id && !in_array($product->product_category_id, $viewedCategories)) {
                $viewedCategories[] = $product->product_category_id;
                session(['viewed_categories' => array_slice($viewedCategories, -10)]);
            }

            if ($product->vendor_id && !in_array($product->vendor_id, $viewedBrands)) {
                $viewedBrands[] = $product->vendor_id;
                session(['viewed_brands' => array_slice($viewedBrands, -10)]);
            }


        } catch (\Exception $e) {
            Log::warning('Failed to track product view: ' . $e->getMessage());
        }
    }
}
