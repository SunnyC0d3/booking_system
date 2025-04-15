<?php

namespace App\Services\V1\Products;

use App\Models\ProductTag as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class ProductTag
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_tags')) {
            $tags = DB::all();
            return $this->ok('Tags retrieved successfully.', $tags);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $productTag)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_tags')) {
            return $this->ok('Tag retrieved successfully.', $productTag->load('products'));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_product_tags')) {
            $data = $request->validated(
                $request->only(['name'])
            );

            $tag = DB::create($data);
            return $this->ok('Tag created successfully.', $tag);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $productTag)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_product_tags')) {
            $data = $request->validated(
                $request->only(['name'])
            );

            $productTag->update($data);
            return $this->ok('Tag updated successfully.', $productTag);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $productTag)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_product_tags')) {
            $productTag->forceDelete();
            return $this->ok('Tag deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
