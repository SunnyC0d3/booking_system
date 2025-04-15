<?php

namespace App\Services\V1\Products;

use App\Models\ProductStatus as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class ProductStatus
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_statuses')) {
            $statuses = DB::all();
            return $this->ok('Statuses retrieved successfully.', $statuses);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $productStatus)
    {
        $user = $request->user();

        if ($user->hasPermission('view_product_statuses')) {
            return $this->ok('Status retrieved successfully.', $productStatus);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_product_statuses')) {
            $data = $request->validated(
                $request->only(['name'])
            );

            $status = DB::create($data);
            return $this->ok('Product status created successfully.', $status);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $productStatus)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_product_statuses')) {
            $data = $request->validated(
                $request->only(['name'])
            );

            $productStatus->update($data);
            return $this->ok('Product status updated successfully.', $productStatus);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $productStatus)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_product_statuses')) {
            $productStatus->forceDelete();
            return $this->ok('Product status deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
