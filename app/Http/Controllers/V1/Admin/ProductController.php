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
     * Retrieve a paginated list of products.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Products retrieved successfully.",
     *     "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Retrieve a specific product.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product retrieved successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Create a new product.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product created successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Update an existing product.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product updated successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Soft delete a product.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Permanently delete a product.
     *
     * @group Products
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, ProdDB $product)
    {
        try {
            return $this->product->delete($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
