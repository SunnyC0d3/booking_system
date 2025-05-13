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
     * Retrieve all product tags.
     *
     * @group Product Tags
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @response 200 {
     *     "message": "Tags retrieved successfully.",
     *     "data": [
     *         {"id": 1, "name": "Electronics"},
     *         {"id": 2, "name": "Fashion"}
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
            return $this->productTag->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product tag.
     *
     * @group Product Tags
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam name string required The name of the product tag.
     *
     * @response 200 {
     *     "message": "Tag created successfully.",
     *     "data": {"id": 3, "name": "Home Appliances"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Retrieve a specific product tag.
     *
     * @group Product Tags
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @response 200 {
     *     "message": "Tag retrieved successfully.",
     *     "data": {"id": 1, "name": "Electronics"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Update an existing product tag.
     *
     * @group Product Tags
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam name string required The updated name of the product tag.
     *
     * @response 200 {
     *     "message": "Tag updated successfully.",
     *     "data": {"id": 1, "name": "Gadgets"}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Delete a product tag.
     *
     * @group Product Tags
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @response 200 {
     *     "message": "Tag deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
