<?php

namespace App\Services\V1\Users;

use App\Models\Vendor as DB;
use App\Filters\V1\VendorFilter;
use App\Resources\V1\VendorResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Vendor
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request, VendorFilter $filter)
    {
        $user = $request->user();

        if ($user->hasPermission('view_vendors')) {
            $query = DB::with(['user'])->filter($filter);
            $perPage = $request->input('per_page', 15);
            $vendors = $query->paginate($perPage);

            return $this->ok('Vendors fetched successfully', VendorResource::collection($vendors));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('view_vendors')) {
            $vendor->load(['user']);
            return $this->success('Vendor details fetched successfully', new VendorResource($vendor));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_vendors')) {
            $data = $request->validated();

            $vendor = DB::create($data);

            if (!empty($data['logo'])) {
                $vendor->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            return $this->ok('Vendor created successfully', new VendorResource($vendor));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_vendors')) {
            $data = $request->validated();

            $vendor->update($data);

            if (!empty($data['logo'])) {
                $vendor->clearMediaCollection('logo');
                $vendor->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            return $this->ok('Vendor updated successfully', new VendorResource($vendor));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_vendors')) {
            $vendor->delete();
            return $this->ok('Vendor deleted successfully');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
