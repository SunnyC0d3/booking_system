<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateProductStatusRequest;
use App\Requests\V1\StoreProductStatusRequest;
use \Exception;
use Illuminate\Http\Request;
use App\Services\V1\Products\ProductStatus;
use App\Models\ProductStatus as DB;

class ProductStatusController extends Controller
{
    use ApiResponses;

    private $productStatus;

    public function __construct(ProductStatus $productStatus)
    {
        $this->productStatus = $productStatus;
    }

    /**
     * Retrieve all product statuses.
     *
     * @group Product Statuses
     * @authenticated
     *
     * @response 200 {
     *     "message": "Statuses retrieved successfully.",
     *     "data": [
     *         {"id": 1, "name": "Available"},
     *         {"id": 2, "name": "Out of Stock"}
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
            return $this->productStatus->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product status.
     *
     * @group Product Statuses
     * @authenticated
     *
     * @bodyParam name string required The name of the product status.
     *
     * @response 201 {
     *     "message": "Product status created successfully.",
     *     "data": {"id": 3, "name": "Discontinued"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreProductStatusRequest $request)
    {
        try {
            return $this->productStatus->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product status.
     *
     * @group Product Statuses
     * @authenticated
     *
     * @response 200 {
     *     "message": "Status retrieved successfully.",
     *     "data": {"id": 1, "name": "Available"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, DB $productStatus)
    {
        try {
            return $this->productStatus->find($request, $productStatus);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing product status.
     *
     * @group Product Statuses
     * @authenticated
     *
     * @bodyParam name string required The updated name of the product status.
     *
     * @response 200 {
     *     "message": "Product status updated successfully.",
     *     "data": {"id": 1, "name": "Unavailable"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateProductStatusRequest $request, DB $productStatus)
    {
        try {
            return $this->productStatus->update($request, $productStatus);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product status.
     *
     * @group Product Statuses
     * @authenticated
     *
     * @response 200 {
     *     "message": "Product status deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, DB $productStatus)
    {
        try {
            return $this->productStatus->delete($request, $productStatus);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
