<?php

namespace App\Http\Controllers\V1\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreProductCategoryRequest;
use App\Requests\V1\UpdateProductCategoryRequest;
use App\Services\V1\Products\ProductCategory;
use App\Models\ProductCategory as DB;

class ProductCategoryController extends Controller
{
    use ApiResponses;

    private $productCategory;

    public function __construct(ProductCategory $productCategory)
    {
        $this->productCategory = $productCategory;
    }

    /**
     * Get all product categories
     *
     * Retrieve a complete hierarchical list of all product categories in the system. Categories
     * help organize products for better navigation and filtering. This endpoint returns both
     * parent categories and their child subcategories, enabling administrators to understand
     * the complete category structure and manage product organization effectively.
     *
     * @group Product Category Management
     * @authenticated
     *
     * @response 200 scenario="Categories retrieved successfully" {
     *     "message": "Categories retrieved successfully.",
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "Electronics",
     *             "parent_id": null,
     *             "created_at": "2024-12-01T10:00:00.000000Z",
     *             "updated_at": "2024-12-01T10:00:00.000000Z",
     *             "children": [
     *                 {
     *                     "id": 2,
     *                     "name": "Smartphones",
     *                     "parent_id": 1,
     *                     "created_at": "2024-12-01T10:05:00.000000Z",
     *                     "updated_at": "2024-12-01T10:05:00.000000Z",
     *                     "children": []
     *                 },
     *                 {
     *                     "id": 3,
     *                     "name": "Laptops",
     *                     "parent_id": 1,
     *                     "created_at": "2024-12-01T10:10:00.000000Z",
     *                     "updated_at": "2024-12-01T10:10:00.000000Z",
     *                     "children": []
     *                 },
     *                 {
     *                     "id": 4,
     *                     "name": "Audio Devices",
     *                     "parent_id": 1,
     *                     "created_at": "2024-12-01T10:15:00.000000Z",
     *                     "updated_at": "2024-12-01T10:15:00.000000Z",
     *                     "children": [
     *                         {
     *                             "id": 5,
     *                             "name": "Headphones",
     *                             "parent_id": 4,
     *                             "created_at": "2024-12-01T10:20:00.000000Z",
     *                             "updated_at": "2024-12-01T10:20:00.000000Z",
     *                             "children": []
     *                         },
     *                         {
     *                             "id": 6,
     *                             "name": "Speakers",
     *                             "parent_id": 4,
     *                             "created_at": "2024-12-01T10:25:00.000000Z",
     *                             "updated_at": "2024-12-01T10:25:00.000000Z",
     *                             "children": []
     *                         }
     *                     ]
     *                 }
     *             ]
     *         },
     *         {
     *             "id": 7,
     *             "name": "Clothing",
     *             "parent_id": null,
     *             "created_at": "2024-12-01T10:30:00.000000Z",
     *             "updated_at": "2024-12-01T10:30:00.000000Z",
     *             "children": [
     *                 {
     *                     "id": 8,
     *                     "name": "Men's Clothing",
     *                     "parent_id": 7,
     *                     "created_at": "2024-12-01T10:35:00.000000Z",
     *                     "updated_at": "2024-12-01T10:35:00.000000Z",
     *                     "children": []
     *                 },
     *                 {
     *                     "id": 9,
     *                     "name": "Women's Clothing",
     *                     "parent_id": 7,
     *                     "created_at": "2024-12-01T10:40:00.000000Z",
     *                     "updated_at": "2024-12-01T10:40:00.000000Z",
     *                     "children": []
     *                 }
     *             ]
     *         }
     *     ]
     * }
     *
     * @response 200 scenario="No categories found" {
     *     "message": "Categories retrieved successfully.",
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
            return $this->productCategory->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Store a new product category
     *
     * Create a new product category in the system. Categories can be created as top-level
     * categories (with no parent) or as subcategories under existing categories. This hierarchical
     * structure helps organize products for better navigation and filtering. Category names
     * must be unique across the entire system to avoid confusion.
     *
     * @group Product Category Management
     * @authenticated
     *
     * @bodyParam name string required The name of the category. Must be unique across all categories. Example: "Laptops"
     * @bodyParam parent_id integer optional The ID of the parent category. Omit for top-level categories. Example: 1
     *
     * @response 200 scenario="Category created successfully" {
     *     "message": "Category created successfully.",
     *     "data": {
     *         "id": 10,
     *         "name": "Laptops",
     *         "parent_id": 1,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="Top-level category created" {
     *     "message": "Category created successfully.",
     *     "data": {
     *         "id": 11,
     *         "name": "Home & Garden",
     *         "parent_id": null,
     *         "created_at": "2025-01-16T14:35:00.000000Z",
     *         "updated_at": "2025-01-16T14:35:00.000000Z"
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
     *         "The parent id must exist in product_categories table.",
     *         "The name may not be greater than 255 characters."
     *     ]
     * }
     *
     * @response 422 scenario="Duplicate category name" {
     *     "errors": [
     *         "The name has already been taken."
     *     ]
     * }
     *
     * @response 422 scenario="Invalid parent category" {
     *     "errors": [
     *         "The selected parent id is invalid."
     *     ]
     * }
     */
    public function store(StoreProductCategoryRequest $request)
    {
        try {
            return $this->productCategory->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get a specific product category
     *
     * Retrieve detailed information about a specific product category including its subcategories.
     * This endpoint is useful for examining category hierarchies and understanding the structure
     * of product organization within a particular category branch.
     *
     * @group Product Category Management
     * @authenticated
     *
     * @urlParam productCategory integer required The ID of the product category to retrieve. Example: 1
     *
     * @response 200 scenario="Category retrieved successfully" {
     *     "message": "Category retrieved successfully.",
     *     "data": {
     *         "id": 1,
     *         "name": "Electronics",
     *         "parent_id": null,
     *         "created_at": "2024-12-01T10:00:00.000000Z",
     *         "updated_at": "2024-12-01T10:00:00.000000Z",
     *         "children": [
     *             {
     *                 "id": 2,
     *                 "name": "Smartphones",
     *                 "parent_id": 1,
     *                 "created_at": "2024-12-01T10:05:00.000000Z",
     *                 "updated_at": "2024-12-01T10:05:00.000000Z",
     *                 "children": []
     *             },
     *             {
     *                 "id": 3,
     *                 "name": "Laptops",
     *                 "parent_id": 1,
     *                 "created_at": "2024-12-01T10:10:00.000000Z",
     *                 "updated_at": "2024-12-01T10:10:00.000000Z",
     *                 "children": []
     *             }
     *         ]
     *     }
     * }
     *
     * @response 200 scenario="Leaf category with no children" {
     *     "message": "Category retrieved successfully.",
     *     "data": {
     *         "id": 2,
     *         "name": "Smartphones",
     *         "parent_id": 1,
     *         "created_at": "2024-12-01T10:05:00.000000Z",
     *         "updated_at": "2024-12-01T10:05:00.000000Z",
     *         "children": []
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Category not found" {
     *     "message": "No query results for model [App\\Models\\ProductCategory] 999"
     * }
     */
    public function show(Request $request, DB $productCategory)
    {
        try {
            return $this->productCategory->find($request, $productCategory);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a product category
     *
     * Modify an existing product category's name or parent relationship. When changing the parent,
     * ensure that circular references are not created (a category cannot be its own ancestor).
     * The system prevents self-referencing to maintain a valid hierarchical structure.
     *
     * @group Product Category Management
     * @authenticated
     *
     * @urlParam productCategory integer required The ID of the product category to update. Example: 10
     *
     * @bodyParam name string required The updated name of the category. Must be unique across all categories. Example: "Updated Category Name"
     * @bodyParam parent_id integer optional The ID of the parent category. Cannot be the same as the category being updated. Example: 2
     *
     * @response 200 scenario="Category updated successfully" {
     *     "message": "Category updated successfully.",
     *     "data": {
     *         "id": 10,
     *         "name": "Updated Category Name",
     *         "parent_id": 2,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T15:45:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="Category made top-level" {
     *     "message": "Category updated successfully.",
     *     "data": {
     *         "id": 10,
     *         "name": "Independent Category",
     *         "parent_id": null,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T15:50:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Category not found" {
     *     "message": "No query results for model [App\\Models\\ProductCategory] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The name field is required.",
     *         "The name has already been taken.",
     *         "The parent id must exist in product_categories table.",
     *         "The parent id field cannot reference the current category (self-reference not allowed)."
     *     ]
     * }
     *
     * @response 422 scenario="Self-reference attempt" {
     *     "errors": [
     *         "A category cannot be its own parent."
     *     ]
     * }
     *
     * @response 422 scenario="Circular reference prevention" {
     *     "errors": [
     *         "Cannot set parent category as it would create a circular reference in the hierarchy."
     *     ]
     * }
     */
    public function update(UpdateProductCategoryRequest $request, DB $productCategory)
    {
        try {
            return $this->productCategory->update($request, $productCategory);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product category
     *
     * Permanently remove a product category from the system. Categories can only be deleted
     * if they have no subcategories (children). If a category contains subcategories, those
     * must be deleted first or moved to another parent category. This prevents orphaned
     * categories and maintains the integrity of the hierarchical structure.
     *
     * **Warning**: Deleting a category may affect products that are currently assigned to it.
     * Ensure products are reassigned to appropriate categories before deletion.
     *
     * @group Product Category Management
     * @authenticated
     *
     * @urlParam productCategory integer required The ID of the product category to delete. Example: 10
     *
     * @response 200 scenario="Category deleted successfully" {
     *     "message": "Category deleted successfully"
     * }
     *
     * @response 400 scenario="Category has subcategories" {
     *     "message": "Cannot delete a category with subcategories"
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Category not found" {
     *     "message": "No query results for model [App\\Models\\ProductCategory] 999"
     * }
     *
     * @response 409 scenario="Category has associated products" {
     *     "message": "Cannot delete category that has products assigned to it. Please reassign products to other categories first."
     * }
     *
     * @response 422 scenario="Category has dependencies" {
     *     "message": "This category cannot be deleted as it has associated products or subcategories."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *     "message": "An error occurred while deleting the category."
     * }
     */
    public function destroy(Request $request, DB $productCategory)
    {
        try {
            return $this->productCategory->delete($request, $productCategory);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
