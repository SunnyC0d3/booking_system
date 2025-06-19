<?php

namespace App\Services\V1\Users;

use App\Models\Product as ProdDB;
use App\Models\Vendor as DB;
use App\Filters\V1\VendorFilter;
use App\Resources\V1\VendorResource;
use App\Services\V1\Media\SecureMedia;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Log;

class Vendor
{
    use ApiResponses;

    protected SecureMedia $mediaService;

    public function __construct(SecureMedia $mediaService)
    {
        $this->mediaService = $mediaService;
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

            if ($request->hasFile('logo')) {
                $this->handleSecureLogoUpload($vendor, $request);
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

            if ($request->hasFile('logo')) {
                $this->handleSecureLogoUpload($vendor, $request, true);
            }

            return $this->ok('Vendor updated successfully', new VendorResource($vendor));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    protected function handleSecureLogoUpload(DB $vendor, Request $request, bool $isUpdate = false): void
    {
        try {
            if ($isUpdate) {
                $vendor->clearMediaCollection('logo');
            }

            $this->mediaService->addSecureMedia(
                $vendor,
                $request->file('logo'),
                'logo'
            );

        } catch (\Exception $e) {
            \Log::error('Vendor logo upload failed', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to process logo file: ' . $e->getMessage());
        }
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
