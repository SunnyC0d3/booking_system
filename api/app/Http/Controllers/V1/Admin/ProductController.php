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

    private $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Retrieve a paginated list of products
     *
     * Get a comprehensive paginated list of all products in the system with advanced filtering capabilities.
     * This endpoint provides administrators with complete product information including variants, media,
     * categories, tags, vendor details, and status information. Essential for product catalog management,
     * inventory oversight, and administrative operations.
     *
     * @group Product Management
     * @authenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[name] string optional Filter products by name (partial match supported). Example: wireless
     * @queryParam filter[search] string optional Search across product names and descriptions. Example: bluetooth headphones
     * @queryParam filter[price] string optional Filter by price range in pounds. Single value or comma-separated min,max. Example: 10.00,50.00
     * @queryParam filter[category] string optional Filter by category ID(s). Single ID or comma-separated multiple IDs. Example: 5,12,18
     * @queryParam filter[created_at] string optional Filter by creation date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Options: vendor,variants,category,tags,media. Example: vendor,variants,category
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of products per page (max 100). Default: 15. Example: 24
     * @queryParam sort string optional Sort products. Prefix with '-' for descending. Available: name, price, created_at, updated_at. Example: -price,name
     *
     * @response 200 scenario="Products retrieved successfully" {
     *     "message": "Products retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 15,
     *                 "name": "Wireless Bluetooth Headphones",
     *                 "description": "Premium quality wireless headphones with active noise cancellation and 30-hour battery life.",
     *                 "price": 7999,
     *                 "price_formatted": "£79.99",
     *                 "quantity": 45,
     *                 "product_status": {
     *                     "id": 1,
     *                     "name": "Active"
     *                 },
     *                 "category": {
     *                     "id": 5,
     *                     "name": "Audio Devices",
     *                     "parent_id": 2
     *                 },
     *                 "vendor": {
     *                     "id": 8,
     *                     "name": "AudioTech Solutions",
     *                     "description": "Premium audio equipment manufacturer",
     *                     "logo": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *                     "products_count": 23
     *                 },
     *                 "variants": [
     *                     {
     *                         "id": 23,
     *                         "value": "Black",
     *                         "additional_price": 0,
     *                         "additional_price_formatted": null,
     *                         "quantity": 25,
     *                         "product_attribute": {
     *                             "id": 1,
     *                             "name": "Color"
     *                         },
     *                         "created_at": "2025-01-10T14:35:00.000000Z",
     *                         "updated_at": "2025-01-15T09:22:00.000000Z"
     *                     },
     *                     {
     *                         "id": 24,
     *                         "value": "White",
     *                         "additional_price": 500,
     *                         "additional_price_formatted": "£5.00",
     *                         "quantity": 20,
     *                         "product_attribute": {
     *                             "id": 1,
     *                             "name": "Color"
     *                         },
     *                         "created_at": "2025-01-10T14:35:00.000000Z",
     *                         "updated_at": "2025-01-15T09:22:00.000000Z"
     *                     }
     *                 ],
     *                 "tags": [
     *                     {
     *                         "id": 3,
     *                         "name": "wireless",
     *                         "products_count": 12
     *                     },
     *                     {
     *                         "id": 7,
     *                         "name": "bluetooth",
     *                         "products_count": 15
     *                     },
     *                     {
     *                         "id": 12,
     *                         "name": "noise-cancelling",
     *                         "products_count": 6
     *                     }
     *                 ],
     *                 "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg",
     *                 "gallery": [
     *                     {
     *                         "id": 45,
     *                         "url": "https://yourapi.com/storage/products/headphones-gallery-1.jpg",
     *                         "name": "Front view",
     *                         "file_name": "headphones-front.jpg",
     *                         "mime_type": "image/jpeg",
     *                         "size": 245760
     *                     },
     *                     {
     *                         "id": 46,
     *                         "url": "https://yourapi.com/storage/products/headphones-gallery-2.jpg",
     *                         "name": "Side view",
     *                         "file_name": "headphones-side.jpg",
     *                         "mime_type": "image/jpeg",
     *                         "size": 198432
     *                     }
     *                 ],
     *                 "media_count": {
     *                     "featured_image": 1,
     *                     "gallery": 2
     *                 },
     *                 "created_at": "2025-01-10T14:30:00.000000Z",
     *                 "updated_at": "2025-01-15T09:22:00.000000Z",
     *                 "deleted_at": null
     *             },
     *             {
     *                 "id": 22,
     *                 "name": "Bluetooth Portable Speaker",
     *                 "description": "Compact waterproof speaker with 12-hour battery and crystal clear sound.",
     *                 "price": 4999,
     *                 "price_formatted": "£49.99",
     *                 "quantity": 32,
     *                 "product_status": {
     *                     "id": 1,
     *                     "name": "Active"
     *                 },
     *                 "category": {
     *                     "id": 5,
     *                     "name": "Audio Devices",
     *                     "parent_id": 2
     *                 },
     *                 "vendor": {
     *                     "id": 12,
     *                     "name": "SoundWave Electronics",
     *                     "description": "Innovative portable audio solutions"
     *                 },
     *                 "variants": [],
     *                 "tags": [
     *                     {
     *                         "id": 3,
     *                         "name": "wireless"
     *                     },
     *                     {
     *                         "id": 7,
     *                         "name": "bluetooth"
     *                     },
     *                     {
     *                         "id": 15,
     *                         "name": "waterproof"
     *                     }
     *                 ],
     *                 "featured_image": "https://yourapi.com/storage/products/speaker-featured.jpg",
     *                 "gallery": [],
     *                 "media_count": {
     *                     "featured_image": 1,
     *                     "gallery": 0
     *                 },
     *                 "created_at": "2025-01-08T11:15:00.000000Z",
     *                 "updated_at": "2025-01-14T16:45:00.000000Z",
     *                 "deleted_at": null
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 156,
     *         "last_page": 11,
     *         "from": 1,
     *         "to": 15
     *     }
     * }
     *
     * @response 200 scenario="No products found" {
     *     "message": "Products retrieved successfully.",
     *     "data": {
     *         "data": [],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 0,
     *         "last_page": 1,
     *         "from": null,
     *         "to": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *     "errors": [
     *         "The filter.price field must match the format: number or number,number.",
     *         "The filter.category field must contain only comma-separated numbers.",
     *         "The filter.created_at field must be a valid date or date range in YYYY-MM-DD format."
     *     ]
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
     * vendor information, tags, and administrative metadata. This endpoint provides complete product
     * data needed for product management, editing, and detailed analysis.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to retrieve. Example: 15
     *
     * @response 200 scenario="Product retrieved successfully" {
     *     "message": "Product retrieved successfully.",
     *     "data": {
     *         "id": 15,
     *         "name": "Wireless Bluetooth Headphones",
     *         "description": "Premium quality wireless headphones with active noise cancellation, 30-hour battery life, and superior sound quality. Features include touch controls, voice assistant support, and comfortable over-ear design perfect for long listening sessions.",
     *         "price": 7999,
     *         "price_formatted": "£79.99",
     *         "quantity": 45,
     *         "product_status": {
     *             "id": 1,
     *             "name": "Active"
     *         },
     *         "category": {
     *             "id": 5,
     *             "name": "Audio Devices",
     *             "parent_id": 2
     *         },
     *         "vendor": {
     *             "id": 8,
     *             "name": "AudioTech Solutions",
     *             "description": "Premium audio equipment manufacturer specializing in wireless technology",
     *             "logo": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *             "products_count": 23
     *         },
     *         "variants": [
     *             {
     *                 "id": 23,
     *                 "value": "Black",
     *                 "additional_price": 0,
     *                 "additional_price_formatted": null,
     *                 "quantity": 25,
     *                 "product_attribute": {
     *                     "id": 1,
     *                     "name": "Color"
     *                 }
     *             },
     *             {
     *                 "id": 24,
     *                 "value": "White",
     *                 "additional_price": 500,
     *                 "additional_price_formatted": "£5.00",
     *                 "quantity": 20,
     *                 "product_attribute": {
     *                     "id": 1,
     *                     "name": "Color"
     *                 }
     *             }
     *         ],
     *         "tags": [
     *             {
     *                 "id": 3,
     *                 "name": "wireless",
     *                 "products_count": 12
     *             },
     *             {
     *                 "id": 7,
     *                 "name": "bluetooth",
     *                 "products_count": 15
     *             }
     *         ],
     *         "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg",
     *         "gallery": [
     *             {
     *                 "id": 45,
     *                 "url": "https://yourapi.com/storage/products/headphones-gallery-1.jpg",
     *                 "name": "Front view",
     *                 "file_name": "headphones-front.jpg",
     *                 "mime_type": "image/jpeg",
     *                 "size": 245760
     *             }
     *         ],
     *         "media_count": {
     *             "featured_image": 1,
     *             "gallery": 1
     *         },
     *         "created_at": "2025-01-10T14:30:00.000000Z",
     *         "updated_at": "2025-01-15T09:22:00.000000Z",
     *         "deleted_at": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *     "message": "No query results for model [App\\Models\\Product] 999"
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

    /**
     * Create a new product
     *
     * Add a new product to the system with complete details including variants, tags, and media.
     * Products are automatically assigned to the vendor associated with the authenticated admin user.
     * Supports secure file uploads for featured images and gallery photos with comprehensive validation.
     *
     * @group Product Management
     * @authenticated
     *
     * @bodyParam name string required The product name. Will be used for display and search. Example: "Premium Wireless Earbuds"
     * @bodyParam description string optional Detailed product description. Supports basic HTML tags. Example: "High-quality wireless earbuds with noise cancellation and long battery life."
     * @bodyParam price numeric required Product price in pounds. Will be converted to pennies for storage. Example: 79.99
     * @bodyParam quantity integer required Available stock quantity. Example: 50
     * @bodyParam product_category_id integer required The category ID this product belongs to. Example: 5
     * @bodyParam product_status_id integer required The status ID for this product. Example: 1
     * @bodyParam product_tags array optional Array of tag IDs to associate with this product. Example: [3,7,12]
     * @bodyParam product_variants array optional Array of product variants.
     * @bodyParam product_variants.*.product_attribute_id integer required The attribute ID for this variant. Example: 1
     * @bodyParam product_variants.*.value string required The value for this variant. Example: "Red"
     * @bodyParam product_variants.*.additional_price numeric optional Additional price for this variant in pounds. Example: 5.00
     * @bodyParam product_variants.*.quantity integer required Stock quantity for this variant. Example: 20
     * @bodyParam media.featured_image file optional Featured product image. Max 5MB, dimensions 100x100 to 4000x4000px.
     * @bodyParam media.gallery file[] optional Gallery images. Each max 5MB, dimensions 100x100 to 4000x4000px.
     *
     * @response 200 scenario="Product created successfully" {
     *     "message": "Product created successfully.",
     *     "data": {
     *         "id": 47,
     *         "name": "Premium Wireless Earbuds",
     *         "description": "High-quality wireless earbuds with noise cancellation and long battery life.",
     *         "price": 7999,
     *         "price_formatted": "£79.99",
     *         "quantity": 50,
     *         "product_status": {
     *             "id": 1,
     *             "name": "Active"
     *         },
     *         "category": {
     *             "id": 5,
     *             "name": "Audio Devices"
     *         },
     *         "vendor": {
     *             "id": 8,
     *             "name": "AudioTech Solutions"
     *         },
     *         "variants": [
     *             {
     *                 "id": 156,
     *                 "value": "Red",
     *                 "additional_price": 500,
     *                 "additional_price_formatted": "£5.00",
     *                 "quantity": 20,
     *                 "product_attribute": {
     *                     "id": 1,
     *                     "name": "Color"
     *                 }
     *             }
     *         ],
     *         "tags": [
     *             {
     *                 "id": 3,
     *                 "name": "wireless"
     *             },
     *             {
     *                 "id": 7,
     *                 "name": "bluetooth"
     *             }
     *         ],
     *         "featured_image": "https://yourapi.com/storage/products/earbuds-featured.jpg",
     *         "gallery": [],
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *     "message": "Vendor not found."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The name field is required.",
     *         "The price field is required.",
     *         "The price must be greater than 0.",
     *         "The quantity must be at least 0.",
     *         "The product category id must exist in product_categories table.",
     *         "The product status id must exist in product_statuses table."
     *     ]
     * }
     *
     * @response 413 scenario="File too large" {
     *     "message": "File too large. Maximum size is 5.0 MB."
     * }
     *
     * @response 500 scenario="Media upload failed" {
     *     "message": "Failed to process media files: Unable to write file to disk."
     * }
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
     * Update an existing product
     *
     * Modify an existing product's details including variants, tags, and media. This endpoint
     * supports partial updates - only provided fields will be updated. When updating variants,
     * all existing variants are replaced with the new ones. Media files can be updated independently.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to update. Example: 47
     *
     * @bodyParam name string optional The updated product name. Example: "Premium Wireless Earbuds Pro"
     * @bodyParam description string optional Updated product description. Example: "Enhanced wireless earbuds with improved noise cancellation."
     * @bodyParam price numeric optional Updated price in pounds. Example: 89.99
     * @bodyParam quantity integer optional Updated stock quantity. Example: 75
     * @bodyParam product_category_id integer optional Updated category ID. Example: 6
     * @bodyParam product_status_id integer optional Updated status ID. Example: 1
     * @bodyParam product_tags array optional Updated array of tag IDs. Example: [3,7,12,18]
     * @bodyParam product_variants array optional Updated array of variants (replaces existing).
     * @bodyParam product_variants.*.product_attribute_id integer required Attribute ID for variant. Example: 1
     * @bodyParam product_variants.*.value string required Variant value. Example: "Blue"
     * @bodyParam product_variants.*.additional_price numeric optional Additional price in pounds. Example: 10.00
     * @bodyParam product_variants.*.quantity integer required Variant stock quantity. Example: 15
     * @bodyParam media.featured_image file optional New featured image (replaces existing).
     * @bodyParam media.gallery file[] optional New gallery images (replaces existing).
     *
     * @response 200 scenario="Product updated successfully" {
     *     "message": "Product updated successfully.",
     *     "data": {
     *         "id": 47,
     *         "name": "Premium Wireless Earbuds Pro",
     *         "description": "Enhanced wireless earbuds with improved noise cancellation.",
     *         "price": 8999,
     *         "price_formatted": "£89.99",
     *         "quantity": 75,
     *         "product_status": {
     *             "id": 1,
     *             "name": "Active"
     *         },
     *         "category": {
     *             "id": 6,
     *             "name": "Premium Audio"
     *         },
     *         "vendor": {
     *             "id": 8,
     *             "name": "AudioTech Solutions"
     *         },
     *         "variants": [
     *             {
     *                 "id": 157,
     *                 "value": "Blue",
     *                 "additional_price": 1000,
     *                 "additional_price_formatted": "£10.00",
     *                 "quantity": 15,
     *                 "product_attribute": {
     *                     "id": 1,
     *                     "name": "Color"
     *                 }
     *             }
     *         ],
     *         "tags": [
     *             {
     *                 "id": 3,
     *                 "name": "wireless"
     *             },
     *             {
     *                 "id": 7,
     *                 "name": "bluetooth"
     *             },
     *             {
     *                 "id": 18,
     *                 "name": "premium"
     *             }
     *         ],
     *         "featured_image": "https://yourapi.com/storage/products/earbuds-pro-featured.jpg",
     *         "gallery": [
     *             {
     *                 "id": 89,
     *                 "url": "https://yourapi.com/storage/products/earbuds-pro-gallery-1.jpg",
     *                 "name": "Product showcase"
     *             }
     *         ],
     *         "updated_at": "2025-01-16T15:45:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *     "message": "No query results for model [App\\Models\\Product] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The price must be greater than 0.",
     *         "The quantity must be at least 0."
     *     ]
     * }
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
     * Soft delete a product
     *
     * Soft delete a product, making it inactive but preserving the data for potential recovery.
     * Soft deleted products are hidden from public listings but remain in the database for
     * administrative purposes and order history integrity.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to soft delete. Example: 47
     *
     * @response 200 scenario="Product soft deleted successfully" {
     *     "message": "Product deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *     "message": "No query results for model [App\\Models\\Product] 999"
     * }
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
     * Restore a soft deleted product
     *
     * Restore a previously soft deleted product, making it active again in the system.
     * Only products that have been soft deleted can be restored.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted product to restore. Example: 47
     *
     * @response 200 scenario="Product restored successfully" {
     *     "message": "Product restored successfully.",
     *     "data": {
     *         "id": 47,
     *         "name": "Premium Wireless Earbuds Pro",
     *         "deleted_at": null,
     *         "updated_at": "2025-01-16T16:00:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *     "message": "No query results for model [App\\Models\\Product] 47"
     * }
     *
     * @response 400 scenario="Product not deleted" {
     *     "message": "Product is not deleted."
     * }
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
     * Permanently delete a product
     *
     * Permanently remove a product from the database. This action is irreversible and will
     * also remove all associated variants and media files. The product must be soft deleted
     * first before it can be permanently deleted.
     *
     * @group Product Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted product to permanently delete. Example: 47
     *
     * @response 200 scenario="Product permanently deleted" {
     *     "message": "Product deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *     "message": "No query results for model [App\\Models\\Product] 47"
     * }
     *
     * @response 400 scenario="Product must be soft deleted first" {
     *     "message": "Product must be soft deleted before force deleting."
     * }
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
