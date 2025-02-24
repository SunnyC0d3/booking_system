<?php

namespace App\Services\V1\Product;

use App\Models\ProductAttribute as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class ProductAttribute
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_attributes')) {
            $attributes = DB::all();
            return $this->ok('Attributes retrieved successfully.', $attributes);
        }
    }

    public function find(Request $request, DB $productAttribute)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_attributes')) {
            return $this->ok('Attribute retrieved successfully.', $productAttribute);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_product_attributes')) {
            $attribute = DB::create($request->validated());
            return $this->ok('Product attribute created successfully.', $attribute);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $productAttribute)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_product_attributes')) {
            $productAttribute->update($request->validated());
            return $this->ok('Product attribute updated successfully.', $productAttribute);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $productAttribute)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_product_attributes')) {
            $productAttribute->forceDelete();
            return $this->ok('Product attribute deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
