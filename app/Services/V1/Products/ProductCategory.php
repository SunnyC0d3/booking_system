<?php

namespace App\Services\V1\Products;

use App\Models\ProductCategory as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class ProductCategory
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_categories')) {
            $categories = DB::with('children')->get();
            return $this->ok('Categories retrieved successfully.', $categories);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $productCategory)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_categories')) {
            return $this->ok('Category retrieved successfully.', $productCategory->load('children'));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_product_categories')) {
            $data = $request->validated(
                $request->only([
                    'name',
                    'parent_id',
                ])
            );

            $category = DB::create($data);
            return $this->ok('Category created successfully.', $category);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $productCategory)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_product_categories')) {
            $data = $request->validated(
                $request->only([
                    'name',
                    'parent_id',
                ])
            );

            $productCategory->update($data);
            return $this->ok('Category updated successfully.', $productCategory);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $productCategory)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_product_categories')) {
            if ($productCategory->children()->exists()) {
                return $this->error('Cannot delete a category with subcategories', 400);
            }

            $productCategory->delete();

            return $this->ok('Category deleted successfully');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
