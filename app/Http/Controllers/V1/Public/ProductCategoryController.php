<?php

namespace App\Http\Controllers\V1\Public;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
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
     * Get a specific product category
     *
     * @group Product Category
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
}
