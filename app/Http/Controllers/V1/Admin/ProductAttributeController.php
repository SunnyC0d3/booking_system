<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\ProductAttribute;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateProductAttributeRequest;
use App\Requests\V1\StoreProductAttributeRequest;
use \Exception;

class ProductAttributeController extends Controller
{
    use ApiResponses;

    public function index()
    {
        try {
            $attributes = ProductAttribute::all();
            return $this->ok('Attributes retrieved successfully.', $attributes);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductAttributeRequest $request)
    {
        try {
            $attribute = ProductAttribute::create($request->validated());
            return $this->ok('Product attribute created successfully.', $attribute);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(ProductAttribute $productAttribute)
    {
        return $this->ok('', $productAttribute);
    }

    public function update(UpdateProductAttributeRequest $request, ProductAttribute $productAttribute)
    {
        try {
            $productAttribute->update($request->validated());

            $this->ok('Product attribute updated successfully.', $productAttribute);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(ProductAttribute $productAttribute)
    {
        try {
            $productAttribute->forceDelete();
            $this->ok('Product attribute deleted successfully.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
