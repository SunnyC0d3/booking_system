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
     * @group Product Category
     * @authenticated
     *
     * @response 200 {
     *     "message": "Categories retrieved successfully.",
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "Electronics",
     *             "children": []
     *         }
     *     ]
     * }
     * 
     * @response 403 {
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
     * @group Product Category
     * @authenticated
     *
     * @bodyParam name string required The name of the category.
     * @bodyParam parent_id int optional The ID of the parent category.
     *
     * @response 201 {
     *     "message": "Category created successfully.",
     *     "data": {
     *         "id": 2,
     *         "name": "Laptops",
     *         "parent_id": 1
     *     }
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreProductCategoryRequest $request)
    {
        $request->validated($request->only(['name', 'parent_id']));

        try {
            return $this->productCategory->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get a specific product category
     *
     * @group Product Category
     * @authenticated
     *
     * @response 200 {
     *     "message": "Category retrieved successfully.",
     *     "data": {
     *         "id": 1,
     *         "name": "Electronics",
     *         "children": []
     *     }
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * @group Product Category
     * @authenticated
     *
     * @bodyParam name string required The name of the category.
     * @bodyParam parent_id int optional The ID of the parent category.
     *
     * @response 200 {
     *     "message": "Category updated successfully.",
     *     "data": {
     *         "id": 1,
     *         "name": "Updated Category Name",
     *         "parent_id": null
     *     }
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateProductCategoryRequest $request, DB $productCategory)
    {
        $request->validated($request->only(['name', 'parent_id']));

        try {
            return $this->productCategory->update($request, $productCategory);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product category
     *
     * @group Product Category
     * @authenticated
     *
     * @response 200 {
     *     "message": "Category deleted successfully"
     * }
     * 
     * @response 400 {
     *     "message": "Cannot delete a category with subcategories"
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
