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

class ProductController extends Controller
{
    use ApiResponses;

    private $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Retrieve paginated list of products
     *
     * Get a paginated and filtered list of active products available for purchase.
     * This endpoint supports advanced filtering by name, price range, category, search terms, and more.
     * Products include detailed information such as variants, media, categories, tags, and vendor details.
     * This is the main endpoint for product browsing and search functionality.
     *
     * @group Product Catalog
     * @unauthenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[name] string optional Filter products by name (partial match supported). Example: wireless
     * @queryParam filter[search] string optional Search across product names and descriptions. Example: bluetooth headphones
     * @queryParam filter[price] string optional Filter by price range in pounds. Single value or comma-separated min,max. Example: 10.00,50.00
     * @queryParam filter[category] string optional Filter by category ID(s). Single ID or comma-separated multiple IDs. Example: 5,12,18
     * @queryParam filter[created_at] string optional Filter by creation date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Comma-separated values: vendor,variants,category,tags,media. Example: vendor,variants,category
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of products per page (max 100). Default: 15. Example: 24
     * @queryParam sort string optional Sort products. Prefix with '-' for descending. Available: name, price, created_at, updated_at. Example: -price,name
     *
     * @response 200 scenario="Products found with filters" {
     *   "message": "Products retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 15,
     *         "name": "Wireless Bluetooth Headphones",
     *         "description": "Premium quality wireless headphones with active noise cancellation and 30-hour battery life.",
     *         "price": 7999,
     *         "price_formatted": "£79.99",
     *         "quantity": 45,
     *         "created_at": "2025-01-10T14:30:00.000000Z",
     *         "updated_at": "2025-01-15T09:22:00.000000Z",
     *         "deleted_at": null,
     *         "product_status": {
     *           "id": 1,
     *           "name": "Active"
     *         },
     *         "category": {
     *           "id": 5,
     *           "name": "Audio Devices",
     *           "parent_id": 2
     *         },
     *         "vendor": {
     *           "id": 8,
     *           "name": "AudioTech Solutions",
     *           "description": "Premium audio equipment manufacturer",
     *           "logo": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg"
     *         },
     *         "variants": [
     *           {
     *             "id": 23,
     *             "value": "Black",
     *             "additional_price": 0,
     *             "additional_price_formatted": null,
     *             "quantity": 25,
     *             "product_attribute": {
     *               "id": 1,
     *               "name": "Color"
     *             }
     *           },
     *           {
     *             "id": 24,
     *             "value": "White",
     *             "additional_price": 500,
     *             "additional_price_formatted": "£5.00",
     *             "quantity": 20,
     *             "product_attribute": {
     *               "id": 1,
     *               "name": "Color"
     *             }
     *           }
     *         ],
     *         "tags": [
     *           {
     *             "id": 3,
     *             "name": "wireless"
     *           },
     *           {
     *             "id": 7,
     *             "name": "bluetooth"
     *           },
     *           {
     *             "id": 12,
     *             "name": "noise-cancelling"
     *           }
     *         ],
     *         "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg",
     *         "gallery": [
     *           {
     *             "id": 45,
     *             "url": "https://yourapi.com/storage/products/headphones-gallery-1.jpg",
     *             "name": "Front view",
     *             "file_name": "headphones-front.jpg",
     *             "mime_type": "image/jpeg",
     *             "size": 245760
     *           },
     *           {
     *             "id": 46,
     *             "url": "https://yourapi.com/storage/products/headphones-gallery-2.jpg",
     *             "name": "Side view",
     *             "file_name": "headphones-side.jpg",
     *             "mime_type": "image/jpeg",
     *             "size": 198432
     *           }
     *         ],
     *         "media_count": {
     *           "featured_image": 1,
     *           "gallery": 2
     *         }
     *       },
     *       {
     *         "id": 22,
     *         "name": "Bluetooth Portable Speaker",
     *         "description": "Compact waterproof speaker with 12-hour battery and crystal clear sound.",
     *         "price": 4999,
     *         "price_formatted": "£49.99",
     *         "quantity": 32,
     *         "created_at": "2025-01-08T11:15:00.000000Z",
     *         "updated_at": "2025-01-14T16:45:00.000000Z",
     *         "deleted_at": null,
     *         "product_status": {
     *           "id": 1,
     *           "name": "Active"
     *         },
     *         "category": {
     *           "id": 5,
     *           "name": "Audio Devices",
     *           "parent_id": 2
     *         },
     *         "vendor": {
     *           "id": 12,
     *           "name": "SoundWave Electronics",
     *           "description": "Innovative portable audio solutions"
     *         },
     *         "variants": [],
     *         "tags": [
     *           {
     *             "id": 3,
     *             "name": "wireless"
     *           },
     *           {
     *             "id": 7,
     *             "name": "bluetooth"
     *           },
     *           {
     *             "id": 15,
     *             "name": "waterproof"
     *           }
     *         ],
     *         "featured_image": "https://yourapi.com/storage/products/speaker-featured.jpg",
     *         "gallery": [],
     *         "media_count": {
     *           "featured_image": 1,
     *           "gallery": 0
     *         }
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 28,
     *     "last_page": 2,
     *     "from": 1,
     *     "to": 15,
     *     "path": "https://yourapi.com/api/v1/products",
     *     "first_page_url": "https://yourapi.com/api/v1/products?page=1",
     *     "last_page_url": "https://yourapi.com/api/v1/products?page=2",
     *     "next_page_url": "https://yourapi.com/api/v1/products?page=2",
     *     "prev_page_url": null
     *   }
     * }
     *
     * @response 200 scenario="No products found" {
     *   "message": "Products retrieved successfully.",
     *   "data": {
     *     "data": [],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 0,
     *     "last_page": 1,
     *     "from": null,
     *     "to": null
     *   }
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The filter.price field must match the format: number or number,number.",
     *     "The filter.category field must contain only comma-separated numbers.",
     *     "The filter.created_at field must be a valid date or date range in YYYY-MM-DD format."
     *   ]
     * }
     *
     * @response 400 scenario="Invalid sort parameter" {
     *   "message": "Invalid sort field. Available fields: name, price, created_at, updated_at"
     * }
     */
    public function index(FilterProductRequest $request, ProductFilter $filter)
    {
        try {
            return $this->product->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product
     *
     * Get detailed information about a specific product including all variants, media, category details,
     * vendor information, and tags. This endpoint provides complete product data needed for product detail pages,
     * including high-resolution images, pricing for all variants, and inventory levels.
     *
     * @group Product Catalog
     * @unauthenticated
     *
     * @urlParam product integer required The ID of the product to retrieve. Example: 15
     *
     * @response 200 scenario="Product found" {
     *   "message": "Product retrieved successfully.",
     *   "data": {
     *     "id": 15,
     *     "name": "Wireless Bluetooth Headphones",
     *     "description": "Premium quality wireless headphones with active noise cancellation, 30-hour battery life, and superior sound quality. Features include touch controls, voice assistant support, and comfortable over-ear design perfect for long listening sessions.",
     *     "price": 7999,
     *     "price_formatted": "£79.99",
     *     "quantity": 45,
     *     "created_at": "2025-01-10T14:30:00.000000Z",
     *     "updated_at": "2025-01-15T09:22:00.000000Z",
     *     "deleted_at": null,
     *     "product_status": {
     *       "id": 1,
     *       "name": "Active"
     *     },
     *     "category": {
     *       "id": 5,
     *       "name": "Audio Devices",
     *       "parent_id": 2
     *     },
     *     "vendor": {
     *       "id": 8,
     *       "name": "AudioTech Solutions",
     *       "description": "Premium audio equipment manufacturer specializing in wireless technology",
     *       "logo": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *       "media": [
     *         {
     *           "id": 12,
     *           "url": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *           "name": "AudioTech Logo",
     *           "file_name": "audiotech-logo.jpg",
     *           "mime_type": "image/jpeg",
     *           "size": 45672
     *         }
     *       ],
     *       "products_count": 23
     *     },
     *     "variants": [
     *       {
     *         "id": 23,
     *         "value": "Black",
     *         "additional_price": 0,
     *         "additional_price_formatted": null,
     *         "quantity": 25,
     *         "created_at": "2025-01-10T14:35:00.000000Z",
     *         "updated_at": "2025-01-15T09:22:00.000000Z",
     *         "product_attribute": {
     *           "id": 1,
     *           "name": "Color"
     *         }
     *       },
     *       {
     *         "id": 24,
     *         "value": "White",
     *         "additional_price": 500,
     *         "additional_price_formatted": "£5.00",
     *         "quantity": 20,
     *         "created_at": "2025-01-10T14:35:00.000000Z",
     *         "updated_at": "2025-01-15T09:22:00.000000Z",
     *         "product_attribute": {
     *           "id": 1,
     *           "name": "Color"
     *         }
     *       },
     *       {
     *         "id": 25,
     *         "value": "32GB",
     *         "additional_price": 2000,
     *         "additional_price_formatted": "£20.00",
     *         "quantity": 15,
     *         "created_at": "2025-01-10T14:35:00.000000Z",
     *         "updated_at": "2025-01-15T09:22:00.000000Z",
     *         "product_attribute": {
     *           "id": 4,
     *           "name": "Storage"
     *         }
     *       }
     *     ],
     *     "tags": [
     *       {
     *         "id": 3,
     *         "name": "wireless",
     *         "products_count": 12
     *       },
     *       {
     *         "id": 7,
     *         "name": "bluetooth",
     *         "products_count": 15
     *       },
     *       {
     *         "id": 12,
     *         "name": "noise-cancelling",
     *         "products_count": 6
     *       },
     *       {
     *         "id": 18,
     *         "name": "premium",
     *         "products_count": 8
     *       }
     *     ],
     *     "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg",
     *     "gallery": [
     *       {
     *         "id": 45,
     *         "url": "https://yourapi.com/storage/products/headphones-gallery-1.jpg",
     *         "name": "Front view",
     *         "file_name": "headphones-front.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 245760
     *       },
     *       {
     *         "id": 46,
     *         "url": "https://yourapi.com/storage/products/headphones-gallery-2.jpg",
     *         "name": "Side view",
     *         "file_name": "headphones-side.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 198432
     *       },
     *       {
     *         "id": 47,
     *         "url": "https://yourapi.com/storage/products/headphones-gallery-3.jpg",
     *         "name": "Package contents",
     *         "file_name": "headphones-package.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 312584
     *       }
     *     ],
     *     "media_count": {
     *       "featured_image": 1,
     *       "gallery": 3
     *     }
     *   }
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\Product] 999"
     * }
     *
     * @response 404 scenario="Product not active" {
     *   "message": "Product is not available for viewing"
     * }
     */
    public function show(Request $request, ProdDB $product)
    {
        try {
            return $this->product->find($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
