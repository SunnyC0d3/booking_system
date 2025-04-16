<?php

namespace App\Services\V1\Users;

use App\Models\Vendor as VendorDB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Vendor
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_vendors')) {
            $vendors = VendorDB::all();
            return $this->ok('Vendors fetched successfully', $vendors);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, VendorDB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('view_vendors')) {
            $vendor->load(['user']);
            $this->success('Vendor details fetched successfully', $vendor);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_vendors')) {
            $data = $request->validated();

            $vendor = Vendor::create($data);

            if ($data['logo']) {
                $vendor->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            return $this->ok('Vendor created successfully', $vendor);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, VendorDB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_vendors')) {
            $data = $request->validated();

            $vendor->update($data);

            if (!empty($data['logo'])) {
                $vendor->clearMediaCollection('logo');
                $vendor->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            return $this->ok('Vendor updated successfully', $vendor);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, VendorDB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_vendors')) {
            $vendor->delete();
            return $this->ok('Vendor deleted successfully');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
