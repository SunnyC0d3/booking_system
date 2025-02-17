<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\ProductStatus;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateProductStatusRequest;
use App\Requests\V1\StoreProductStatusRequest;
use \Exception;
use Illuminate\Http\Request;

class ProductStatusController extends Controller
{
    use ApiResponses;

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
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_statuses')) {
                $statuses = ProductStatus::all();
                return $this->ok('Statuses retrieved successfully.', $statuses);
            }

            return $this->error('You do not have the required permissions.', 403);
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
        $request->validated($request->only(['name']));

        $user = $request->user();

        try {
            if ($user->hasPermission('create_product_statuses')) {
                $status = ProductStatus::create($request->validated());
                return $this->ok('Product status created successfully.', $status);
            }

            return $this->error('You do not have the required permissions.', 403);
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
    public function show(Request $request, ProductStatus $productStatus)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_statuses')) {
                return $this->ok('Status retrieved successfully.', $productStatus);
            }

            return $this->error('You do not have the required permissions.', 403);
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
    public function update(UpdateProductStatusRequest $request, ProductStatus $productStatus)
    {
        $request->validated($request->only(['name']));

        $user = $request->user();

        try {
            if ($user->hasPermission('edit_product_statuses')) {
                $productStatus->update($request->validated());
                return $this->ok('Product status updated successfully.', $productStatus);
            }

            return $this->error('You do not have the required permissions.', 403);
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
    public function destroy(Request $request, ProductStatus $productStatus)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('delete_product_statuses')) {
                $productStatus->forceDelete();
                return $this->ok('Product status deleted successfully.');
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
