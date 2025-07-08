<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Product as ProdDB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Filters\V1\ProductFilter;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\FilterProductRequest;
use App\Requests\V1\StoreProductRequest;
use App\Requests\V1\UpdateProductRequest;
use App\Services\V1\Products\Product;

class ProductController extends Controller
{
    use ApiResponses;

    private Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Retrieve a paginated list of products with enhanced search
     *
     * Get a comprehensive paginated list of all products in the system with advanced filtering capabilities.
     * This endpoint provides administrators with complete product information including variants, media,
     * categories, tags, vendor details, relevance scoring, and faceted search results.
     *
     * @group Product Management
     * @authenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[search] string optional Advanced search across product names and descriptions with relevance scoring. Example: wireless bluetooth headphones
     * @queryParam filter[name] string optional Filter products by name (partial match supported). Example: wireless
     * @queryParam filter[price] string optional Filter by price range in pounds. Single value or comma-separated min,max. Example: 10.00,50.00
     * @queryParam filter[priceRanges] string optional Multiple price ranges separated by commas. Example: 0-25,50-100,200-500
     * @queryParam filter[category] string optional Filter by category ID(s) including child categories. Example: 5,12,18
     * @queryParam filter[availability] string optional Filter by stock status. Options: in_stock, out_of_stock, low_stock, available. Example: in_stock
     * @queryParam filter[vendors] string optional Filter by vendor ID(s). Example: 2,5,8
     * @queryParam filter[vendor] string optional Filter by vendor name (partial match). Example: apple
     * @queryParam filter[tags] string optional Filter by tag ID(s) with logic support. Example: 3,7,12
     * @queryParam filter[attributes] string optional Filter by product attributes. Format: attribute:value,attribute:value. Example: color:red,size:large
     * @queryParam filter[status] string optional Filter by product status. Example: Active
     * @queryParam filter[quantity] string optional Filter by quantity range. Single value or min,max. Example: 10,100
     * @queryParam filter[created_at] string optional Filter by creation date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[updated_at] string optional Filter by update date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Options: vendor,variants,category,tags,media. Example: vendor,variants,category
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of products per page (max 100). Default: 15. Example: 24
     * @queryParam sort string optional Sort products. Prefix with '-' for descending. Available: name, price, quantity, created_at, updated_at, relevance. Example: -relevance,price
     * @queryParam diversify boolean optional Apply result diversification to avoid too many similar products. Default: false. Example: true
     * @queryParam explain boolean optional Include search explanations for debugging. Default: false. Example: true
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
     * Retrieve a specific product with enhanced data
     *
     * Get detailed information about a specific product including all variants, media, category details,
     * vendor information, tags, search metadata, and administrative information. This endpoint provides
     * complete product data needed for product management, editing, and detailed analysis.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to retrieve. Example: 15
     */
    public function show(Request $request, ProdDB $product)
    {
        try {
            return $this->product->find($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product with enhanced features
     *
     * Add a new product to the system with complete details including variants, tags, media, and search optimization.
     * Products are automatically assigned to the vendor associated with the authenticated admin user.
     * Supports secure file uploads and automatic search keyword generation for optimal findability.
     *
     * @group Product Management
     * @authenticated
     *
     * @bodyParam name string required The product name. Will be used for display and search optimization. Example: "Premium Wireless Earbuds Pro"
     * @bodyParam description string optional Detailed product description. Supports basic HTML tags and is used for search indexing. Example: "High-quality wireless earbuds with advanced noise cancellation and 30-hour battery life."
     * @bodyParam price numeric required Product price in pounds. Will be converted to pennies for storage. Example: 89.99
     * @bodyParam quantity integer required Available stock quantity. Example: 50
     * @bodyParam low_stock_threshold integer optional Low stock alert threshold. Default: 10. Example: 5
     * @bodyParam product_category_id integer required The category ID this product belongs to. Example: 5
     * @bodyParam product_status_id integer required The status ID for this product. Example: 1
     * @bodyParam product_tags array optional Array of tag IDs to associate with this product for better searchability. Example: [3,7,12,18]
     * @bodyParam product_variants array optional Array of product variants with enhanced attributes.
     * @bodyParam product_variants.*.product_attribute_id integer required The attribute ID for this variant. Example: 1
     * @bodyParam product_variants.*.value string required The value for this variant. Example: "Midnight Black"
     * @bodyParam product_variants.*.additional_price numeric optional Additional price for this variant in pounds. Example: 10.00
     * @bodyParam product_variants.*.quantity integer required Stock quantity for this variant. Example: 25
     * @bodyParam product_variants.*.low_stock_threshold integer optional Low stock threshold for this variant. Default: 5. Example: 3
     * @bodyParam media.featured_image file optional Featured product image. Max 5MB, dimensions 100x100 to 4000x4000px.
     * @bodyParam media.gallery file[] optional Gallery images. Each max 5MB, dimensions 100x100 to 4000x4000px.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            return $this->product->create($request);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing product with enhanced features
     *
     * Modify an existing product's details including variants, tags, media, and search optimization.
     * This endpoint supports partial updates - only provided fields will be updated. When updating variants,
     * all existing variants are replaced with the new ones. Search keywords are automatically regenerated
     * for optimal findability.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to update. Example: 47
     *
     * @bodyParam name string optional The updated product name. Example: "Premium Wireless Earbuds Pro Max"
     * @bodyParam description string optional Updated product description with search optimization. Example: "Enhanced wireless earbuds with improved noise cancellation and extended battery life."
     * @bodyParam price numeric optional Updated price in pounds. Example: 99.99
     * @bodyParam quantity integer optional Updated stock quantity. Example: 75
     * @bodyParam low_stock_threshold integer optional Updated low stock threshold. Example: 8
     * @bodyParam product_category_id integer optional Updated category ID. Example: 6
     * @bodyParam product_status_id integer optional Updated status ID. Example: 1
     * @bodyParam product_tags array optional Updated array of tag IDs for better search optimization. Example: [3,7,12,18,22]
     * @bodyParam product_variants array optional Updated array of variants (replaces existing).
     * @bodyParam product_variants.*.product_attribute_id integer required Attribute ID for variant. Example: 1
     * @bodyParam product_variants.*.value string required Variant value. Example: "Pearl White"
     * @bodyParam product_variants.*.additional_price numeric optional Additional price in pounds. Example: 15.00
     * @bodyParam product_variants.*.quantity integer required Variant stock quantity. Example: 20
     * @bodyParam product_variants.*.low_stock_threshold integer optional Variant low stock threshold. Example: 2
     * @bodyParam media.featured_image file optional New featured image (replaces existing).
     * @bodyParam media.gallery file[] optional New gallery images (replaces existing).
     */
    public function update(UpdateProductRequest $request, ProdDB $product)
    {
        try {
            return $this->product->update($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Soft delete a product with search index update
     *
     * Soft delete a product, making it inactive but preserving the data for potential recovery.
     * Soft deleted products are hidden from public listings but remain in the database for
     * administrative purposes and order history integrity. Search indexes are updated automatically.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to soft delete. Example: 47
     */
    public function softDestroy(Request $request, ProdDB $product)
    {
        try {
            return $this->product->softDelete($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Restore a soft deleted product with search optimization
     *
     * Restore a previously soft deleted product, making it active again in the system.
     * Only products that have been soft deleted can be restored. Search indexes are
     * automatically updated to include the restored product.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted product to restore. Example: 47
     */
    public function restore(Request $request, int $id)
    {
        try {
            return $this->product->restore($request, $id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete a product with complete cleanup
     *
     * Permanently remove a product from the database. This action is irreversible and will
     * also remove all associated variants, media files, tag associations, and search indexes.
     * The product must be soft deleted first before it can be permanently deleted.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted product to permanently delete. Example: 47
     */
    public function destroy(Request $request, int $id)
    {
        try {
            return $this->product->delete($request, $id);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
