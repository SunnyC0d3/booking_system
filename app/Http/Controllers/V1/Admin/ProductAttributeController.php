<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\ProductAttribute;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateProductAttributeRequest;
use App\Requests\V1\StoreProductAttributeRequest;
use \Exception;
use Illuminate\Http\Request;

class ProductAttributeController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_attributes')) {
                $attributes = ProductAttribute::all();
                return $this->ok('Attributes retrieved successfully.', $attributes);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductAttributeRequest $request)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('create_product_attributes')) {
                $attribute = ProductAttribute::create($request->validated());
                return $this->ok('Product attribute created successfully.', $attribute);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(Request $request, ProductAttribute $productAttribute)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_attributes')) {
                return $this->ok('Attribute retrieved successfully.', $productAttribute);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateProductAttributeRequest $request, ProductAttribute $productAttribute)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('edit_product_attributes')) {
                $productAttribute->update($request->validated());
                $this->ok('Product attribute updated successfully.', $productAttribute);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, ProductAttribute $productAttribute)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('delete_product_attributes')) {
                $productAttribute->forceDelete();
                $this->ok('Product attribute deleted successfully.');
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
