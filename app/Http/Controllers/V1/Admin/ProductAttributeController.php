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
     * Retrieve all product attributes.
     *
     * @group Product Attributes
     * @authenticated
     *
     * @response 200 {
     *   "message": "Attributes retrieved successfully.",
     *   "data": []
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Create a new product attribute.
     *
     * @group Product Attributes
     * @authenticated
     *
     * @bodyParam name string required The name of the product attribute. Example: "Color"
     *
     * @response 201 {
     *   "message": "Product attribute created successfully.",
     *   "data": {}
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreProductAttributeRequest $request)
    {
        $request->validated($request->only(['name']));

        try {
            return $this->productAttribute->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product attribute.
     *
     * @group Product Attributes
     * @authenticated
     *
     * @response 200 {
     *   "message": "Attribute retrieved successfully.",
     *   "data": {}
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Update a product attribute.
     *
     * @group Product Attributes
     * @authenticated
     *
     * @bodyParam name string required The updated name of the product attribute. Example: "Size"
     *
     * @response 200 {
     *   "message": "Product attribute updated successfully.",
     *   "data": {}
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateProductAttributeRequest $request, DB $productAttribute)
    {
        $request->validated($request->only(['name']));

        try {
            return $this->productAttribute->update($request, $productAttribute);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product attribute.
     *
     * @group Product Attributes
     * @authenticated
     *
     * @response 200 {
     *   "message": "Product attribute deleted successfully."
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
