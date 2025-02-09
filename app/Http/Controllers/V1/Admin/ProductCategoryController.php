<?php

namespace App\Http\Controllers\V1\Admin;

use Exception;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreProductCategoryRequest;
use App\Requests\V1\UpdateProductCategoryRequest;

class ProductCategoryController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_categories')) {
                $categories = ProductCategory::with('children')->get();
                return $this->ok('Categories retrieved successfully.', $categories);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $request->validated($request->only(['name', 'parent_id']));

        $user = $request->user();

        try {
            if ($user->hasPermission('create_product_categories')) {
                $category = ProductCategory::create($request->validated());
                return $this->ok('Category created successfully.', $category);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(Request $request, ProductCategory $productCategory)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_product_categories')) {
                return $this->ok('Category retrieved successfully.', $productCategory->load('children'));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $request->validated($request->only(['name', 'parent_id']));

        $user = $request->user();

        try {
            if ($user->hasPermission('edit_product_categories')) {
                $productCategory->update($request->validated());
                return $this->ok('Category updated successfully.', $productCategory);
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, ProductCategory $productCategory)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('delete_product_categories')) {
                if ($productCategory->children()->exists()) {
                    return $this->error('Cannot delete a category with subcategories', 400);
                }

                $productCategory->delete();

                return $this->ok('Category deleted successfully');
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
