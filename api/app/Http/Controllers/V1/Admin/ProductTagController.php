<?php

namespace App\Http\Controllers\V1\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Requests\V1\StoreProductTagRequest;
use App\Requests\V1\UpdateProductTagRequest;
use App\Traits\V1\ApiResponses;
use App\Services\V1\Products\ProductTag;
use App\Models\ProductTag as DB;

class ProductTagController extends Controller
{
    use ApiResponses;

    private $productTag;

    public function __construct(ProductTag $productTag)
    {
        $this->productTag = $productTag;
    }

    /**
     * Retrieve all product tags
     *
     * Get a complete list of all product tags in the system. Product tags are used to label
     * and categorize products for better searchability and organization. Tags help customers
     * find products through filtering and search functionality. This endpoint provides
     * administrators with an overview of all available tags and their usage statistics.
     *
     * @group Product Tag Management
     * @authenticated
     *
     * @response 200 scenario="Product tags retrieved successfully" {
     *     "message": "Tags retrieved successfully.",
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "electronics",
     *             "created_at": "2024-12-01T09:00:00.000000Z",
     *             "updated_at": "2024-12-01T09:00:00.000000Z"
     *         },
     *         {
     *             "id": 2,
     *             "name": "wireless",
     *             "created_at": "2024-12-01T09:05:00.000000Z",
     *             "updated_at": "2024-12-01T09:05:00.000000Z"
     *         },
     *         {
     *             "id": 3,
     *             "name": "bluetooth",
     *             "created_at": "2024-12-01T09:10:00.000000Z",
     *             "updated_at": "2024-12-01T09:10:00.000000Z"
     *         },
     *         {
     *             "id": 4,
     *             "name": "waterproof",
     *             "created_at": "2024-12-01T09:15:00.000000Z",
     *             "updated_at": "2024-12-01T09:15:00.000000Z"
     *         },
     *         {
     *             "id": 5,
     *             "name": "premium",
     *             "created_at": "2024-12-01T09:20:00.000000Z",
     *             "updated_at": "2024-12-01T09:20:00.000000Z"
     *         },
     *         {
     *             "id": 6,
     *             "name": "noise-cancelling",
     *             "created_at": "2024-12-01T09:25:00.000000Z",
     *             "updated_at": "2024-12-01T09:25:00.000000Z"
     *         },
     *         {
     *             "id": 7,
     *             "name": "portable",
     *             "created_at": "2024-12-01T09:30:00.000000Z",
     *             "updated_at": "2024-12-01T09:30:00.000000Z"
     *         },
     *         {
     *             "id": 8,
     *             "name": "gaming",
     *             "created_at": "2024-12-01T09:35:00.000000Z",
     *             "updated_at": "2024-12-01T09:35:00.000000Z"
     *         }
     *     ]
     * }
     *
     * @response 200 scenario="No tags found" {
     *     "message": "Tags retrieved successfully.",
     *     "data": []
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->productTag->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product tag
     *
     * Add a new product tag to the system. Tags should be descriptive, concise, and follow
     * consistent naming conventions (typically lowercase, hyphen-separated for multi-word tags).
     * Once created, tags can be assigned to products to improve searchability and organization.
     * Tag names must be unique across the system.
     *
     * @group Product Tag Management
     * @authenticated
     *
     * @bodyParam name string required The name of the product tag. Should be descriptive and follow naming conventions (lowercase, hyphen-separated). Example: "wireless-charging"
     *
     * @response 200 scenario="Product tag created successfully" {
     *     "message": "Tag created successfully.",
     *     "data": {
     *         "id": 9,
     *         "name": "wireless-charging",
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The name field is required.",
     *         "The name has already been taken.",
     *         "The name may not be greater than 255 characters."
     *     ]
     * }
     *
     * @response 422 scenario="Duplicate tag name" {
     *     "errors": [
     *         "The name has already been taken."
     *     ]
     * }
     */
    public function store(StoreProductTagRequest $request)
    {
        try {
            return $this->productTag->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product tag
     *
     * Get detailed information about a specific product tag including its associated products.
     * This endpoint is useful for examining tag usage and understanding which products are
     * currently tagged with specific labels. Helpful for tag management and optimization.
     *
     * @group Product Tag Management
     * @authenticated
     *
     * @urlParam productTag integer required The ID of the product tag to retrieve. Example: 9
     *
     * @response 200 scenario="Product tag found with associated products" {
     *     "message": "Tag retrieved successfully.",
     *     "data": {
     *         "id": 9,
     *         "name": "wireless-charging",
     *         "products": [
     *             {
     *                 "id": 15,
     *                 "name": "Wireless Bluetooth Headphones",
     *                 "price": 7999,
     *                 "price_formatted": "£79.99",
     *                 "created_at": "2025-01-10T14:30:00.000000Z"
     *             },
     *             {
     *                 "id": 23,
     *                 "name": "Smartphone Wireless Charger",
     *                 "price": 2999,
     *                 "price_formatted": "£29.99",
     *                 "created_at": "2025-01-12T16:20:00.000000Z"
     *             }
     *         ],
     *         "products_count": 2,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="Product tag with no associated products" {
     *     "message": "Tag retrieved successfully.",
     *     "data": {
     *         "id": 9,
     *         "name": "wireless-charging",
     *         "products": [],
     *         "products_count": 0,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product tag not found" {
     *     "message": "No query results for model [App\\Models\\ProductTag] 999"
     * }
     */
    public function show(Request $request, DB $productTag)
    {
        try {
            return $this->productTag->find($request, $productTag);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing product tag
     *
     * Modify an existing product tag name. Exercise caution when updating tags as they may
     * be referenced by multiple products and affect search functionality. Ensure that any
     * name changes maintain consistency with existing tagging conventions and don't create
     * confusion for customers or administrators.
     *
     * @group Product Tag Management
     * @authenticated
     *
     * @urlParam productTag integer required The ID of the product tag to update. Example: 9
     *
     * @bodyParam name string required The updated name of the product tag. Must be unique and follow naming conventions. Example: "fast-wireless-charging"
     *
     * @response 200 scenario="Product tag updated successfully" {
     *     "message": "Tag updated successfully.",
     *     "data": {
     *         "id": 9,
     *         "name": "fast-wireless-charging",
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T15:45:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product tag not found" {
     *     "message": "No query results for model [App\\Models\\ProductTag] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The name field is required.",
     *         "The name has already been taken.",
     *         "The name may not be greater than 255 characters."
     *     ]
     * }
     *
     * @response 422 scenario="Name already exists" {
     *     "errors": [
     *         "The name has already been taken."
     *     ]
     * }
     */
    public function update(UpdateProductTagRequest $request, DB $productTag)
    {
        try {
            return $this->productTag->update($request, $productTag);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product tag
     *
     * Permanently remove a product tag from the system. This action is irreversible and will
     * automatically remove the tag from all products that currently have it assigned. Before
     * deletion, consider the impact on product searchability and organization. Customers may
     * no longer be able to find products using this tag for filtering or search.
     *
     * **Warning**: Deleting a widely-used tag may significantly affect product discoverability
     * and customer search experience.
     *
     * @group Product Tag Management
     * @authenticated
     *
     * @urlParam productTag integer required The ID of the product tag to delete. Example: 9
     *
     * @response 200 scenario="Product tag deleted successfully" {
     *     "message": "Tag deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product tag not found" {
     *     "message": "No query results for model [App\\Models\\ProductTag] 999"
     * }
     *
     * @response 409 scenario="Tag in use by products" {
     *     "message": "Cannot delete product tag that is currently assigned to products. Please remove the tag from all products first."
     * }
     *
     * @response 422 scenario="Tag has dependencies" {
     *     "message": "This tag is currently being used by active products and cannot be deleted."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *     "message": "An error occurred while deleting the product tag."
     * }
     */
    public function destroy(Request $request, DB $productTag)
    {
        try {
            return $this->productTag->delete($request, $productTag);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
