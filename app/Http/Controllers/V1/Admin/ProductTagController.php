<?php

namespace App\Http\Controllers\V1\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Models\ProductTag;
use App\Http\Controllers\Controller;
use App\Requests\V1\StoreProductTagRequest;
use App\Requests\V1\UpdateProductTagRequest;
use App\Traits\V1\ApiResponses;

class ProductTagController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve all product tags.
     * 
     * @group Product Tags
     * @authenticated
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
        return response()->json(ProductTag::all());
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_tags')) {
                $tags = ProductTag::all();
                return $this->ok('Tags retrieved successfully.', $tags);
            }

            return $this->error('You do not have the required permissions.', 403);
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
     * @bodyParam name string required The name of the product tag.
     *
     * @response 201 {
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
        $request->validated($request->only(['name']));

        $user = $request->user();

        try {
            if ($user->hasPermission('create_product_tags')) {
                $tag = ProductTag::create($request->validated());
                return $this->ok('Tag created successfully.', $tag);
            }

            return $this->error('You do not have the required permissions.', 403);
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
     * @response 200 {
     *     "message": "Tag retrieved successfully.",
     *     "data": {"id": 1, "name": "Electronics"}
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, ProductTag $productTag)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_tags')) {
                return $this->ok('Tag retrieved successfully.', $productTag->load('products'));
            }

            return $this->error('You do not have the required permissions.', 403);
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
    public function update(UpdateProductTagRequest $request, ProductTag $productTag)
    {
        $request->validated($request->only(['name']));

        $user = $request->user();

        try {
            if ($user->hasPermission('edit_product_tags')) {
                $productTag->update($request->validated());
                return $this->ok('Tag updated successfully.', $productTag);
            }

            return $this->error('You do not have the required permissions.', 403);
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
     * @response 200 {
     *     "message": "Tag deleted successfully."
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, ProductTag $productTag)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('delete_product_tags')) {
                $productTag->forceDelete();
                return $this->ok('Tag deleted successfully.');
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
