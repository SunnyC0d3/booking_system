<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateProductAttributeRequest;
use App\Requests\V1\StoreProductAttributeRequest;
use \Exception;
use Illuminate\Http\Request;
use App\Services\V1\Products\ProductAttribute;
use App\Models\ProductAttribute as DB;

class ProductAttributeController extends Controller
{
    use ApiResponses;

    private $productAttribute;

    public function __construct(ProductAttribute $productAttribute)
    {
        $this->productAttribute = $productAttribute;
    }

    /**
     * Retrieve all product attributes
     *
     * Get a complete list of all product attributes in the system. Product attributes define
     * the variable characteristics of products (like Color, Size, Material, etc.) and are used
     * to create product variants. This endpoint is essential for product catalog management
     * and understanding what customization options are available for products.
     *
     * @group Product Attribute Management
     * @authenticated
     *
     * @response 200 scenario="Product attributes retrieved successfully" {
     *   "message": "Attributes retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Color",
     *       "created_at": "2024-12-01T09:00:00.000000Z",
     *       "updated_at": "2024-12-01T09:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "Size",
     *       "created_at": "2024-12-01T09:05:00.000000Z",
     *       "updated_at": "2024-12-01T09:05:00.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "Material",
     *       "created_at": "2024-12-01T09:10:00.000000Z",
     *       "updated_at": "2024-12-01T09:10:00.000000Z"
     *     },
     *     {
     *       "id": 4,
     *       "name": "Storage",
     *       "created_at": "2024-12-01T09:15:00.000000Z",
     *       "updated_at": "2024-12-01T09:15:00.000000Z"
     *     },
     *     {
     *       "id": 5,
     *       "name": "Weight",
     *       "created_at": "2024-12-01T09:20:00.000000Z",
     *       "updated_at": "2024-12-01T09:20:00.000000Z"
     *     },
     *     {
     *       "id": 6,
     *       "name": "Style",
     *       "created_at": "2024-12-01T09:25:00.000000Z",
     *       "updated_at": "2024-12-01T09:25:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="No product attributes found" {
     *   "message": "Attributes retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->productAttribute->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product attribute
     *
     * Add a new product attribute to the system. Product attributes represent customizable
     * characteristics of products (such as Color, Size, Material). Once created, these attributes
     * can be used to define product variants with specific values (e.g., Color: Red, Blue, Green).
     * Attribute names should be descriptive and follow consistent naming conventions.
     *
     * @group Product Attribute Management
     * @authenticated
     *
     * @bodyParam name string required The name of the product attribute. Should be descriptive and unique. Will be used in product variant creation. Example: "Color"
     *
     * @response 200 scenario="Product attribute created successfully" {
     *   "message": "Product attribute created successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "Color",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The name has already been taken.",
     *     "The name may not be greater than 255 characters."
     *   ]
     * }
     *
     * @response 422 scenario="Duplicate attribute name" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function store(StoreProductAttributeRequest $request)
    {
        try {
            return $this->productAttribute->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product attribute
     *
     * Get detailed information about a specific product attribute including its current usage
     * in the system. This endpoint is useful for examining attribute details before making
     * modifications or understanding how the attribute is currently being utilized across products.
     *
     * @group Product Attribute Management
     * @authenticated
     *
     * @urlParam productAttribute integer required The ID of the product attribute to retrieve. Example: 7
     *
     * @response 200 scenario="Product attribute found" {
     *   "message": "Attribute retrieved successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "Color",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product attribute not found" {
     *   "message": "No query results for model [App\\Models\\ProductAttribute] 999"
     * }
     */
    public function show(Request $request, DB $productAttribute)
    {
        try {
            return $this->productAttribute->find($request, $productAttribute);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a product attribute
     *
     * Modify an existing product attribute name. Exercise caution when updating attributes
     * as they may be referenced by existing product variants. Changing attribute names will
     * affect how they appear in product listings and variant descriptions. Ensure consistency
     * with existing naming conventions and verify the impact on related products.
     *
     * @group Product Attribute Management
     * @authenticated
     *
     * @urlParam productAttribute integer required The ID of the product attribute to update. Example: 7
     *
     * @bodyParam name string required The updated name of the product attribute. Must be unique and descriptive. Example: "Size"
     *
     * @response 200 scenario="Product attribute updated successfully" {
     *   "message": "Product attribute updated successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "Size",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T15:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product attribute not found" {
     *   "message": "No query results for model [App\\Models\\ProductAttribute] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The name has already been taken.",
     *     "The name may not be greater than 255 characters."
     *   ]
     * }
     *
     * @response 422 scenario="Name already exists" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function update(UpdateProductAttributeRequest $request, DB $productAttribute)
    {
        try {
            return $this->productAttribute->update($request, $productAttribute);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product attribute
     *
     * Permanently remove a product attribute from the system. This action is irreversible and
     * will affect all product variants that currently use this attribute. Before deletion,
     * ensure that no active products depend on this attribute, as removing it may cause
     * data inconsistencies or break product variant functionality.
     *
     * **Warning**: Deleting an attribute that is currently used by product variants may cause
     * those variants to become invalid or lose important characteristic information.
     *
     * @group Product Attribute Management
     * @authenticated
     *
     * @urlParam productAttribute integer required The ID of the product attribute to delete. Example: 7
     *
     * @response 200 scenario="Product attribute deleted successfully" {
     *   "message": "Product attribute deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product attribute not found" {
     *   "message": "No query results for model [App\\Models\\ProductAttribute] 999"
     * }
     *
     * @response 409 scenario="Attribute in use by variants" {
     *   "message": "Cannot delete product attribute that is currently used by product variants."
     * }
     *
     * @response 422 scenario="Attribute has dependencies" {
     *   "message": "This attribute is currently being used by active products and cannot be deleted."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *   "message": "An error occurred while deleting the product attribute."
     * }
     */
    public function destroy(Request $request, DB $productAttribute)
    {
        try {
            return $this->productAttribute->delete($request, $productAttribute);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
