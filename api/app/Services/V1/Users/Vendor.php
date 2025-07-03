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

        if ($user->hasPermission('view_all_vendors')) {
            $query = DB::with(['user'])->filter($filter);
            $perPage = $request->input('per_page', 15);
            $vendors = $query->paginate($perPage);

            return VendorResource::collection($vendors)->additional([
                'message' => 'Vendors fetched successfully.',
                'status' => 200
            ]);
        }

        if ($user->hasPermission('view_own_vendor')) {
            $vendors = DB::with(['user'])
                ->where('user_id', $user->id)
                ->filter($filter)
                ->paginate($request->input('per_page', 15));

            return VendorResource::collection($vendors)->additional([
                'message' => 'Your vendor profiles fetched successfully.',
                'status' => 200
            ]);
        }

        if ($user->hasPermission('view_vendors_public')) {
            $query = DB::with(['user'])
                ->whereNull('deleted_at')
                ->filter($filter);
            $perPage = $request->input('per_page', 15);
            $vendors = $query->paginate($perPage);

            return VendorResource::collection($vendors)->additional([
                'message' => 'Vendors fetched successfully.',
                'status' => 200
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $vendor)
    {
        $user = $request->user();

        if ($user->hasPermission('view_all_vendors')) {
            $vendor->load(['user']);
            return $this->ok('Vendor details fetched successfully', new VendorResource($vendor));
        }

        if ($user->hasPermission('view_own_vendor')) {
            if ($vendor->user_id !== $user->id) {
                return $this->error('You can only view your own vendor profile.', 403);
            }

            $vendor->load(['user']);
            return $this->ok('Your vendor profile fetched successfully', new VendorResource($vendor));
        }

        if ($user->hasPermission('view_vendors_public')) {
            if ($vendor->deleted_at !== null) {
                return $this->error('Vendor not found.', 404);
            }

            $vendor->load(['user']);
            return $this->ok('Vendor details fetched successfully', new VendorResource($vendor));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('create_vendors_for_users')) {
            $userId = $data['user_id'];
        }

        elseif ($user->hasPermission('create_own_vendor')) {
            $userId = $user->id;

            if (isset($data['user_id']) && $data['user_id'] != $user->id) {
                return $this->error('You can only create vendor profiles for yourself.', 403);
            }

            if (DB::where('user_id', $user->id)->exists()) {
                return $this->error('You already have a vendor profile.', 403);
            }
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data['user_id'] = $userId;

        $vendor = DB::create($data);

        if ($request->hasFile('logo')) {
            $this->handleSecureLogoUpload($vendor, $request);
        }

        return $this->ok('Vendor created successfully', new VendorResource($vendor));
    }

    public function update(Request $request, DB $vendor)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('edit_own_vendor')) {
            if ($vendor->user_id !== $user->id) {
                return $this->error('You can only edit your own vendor profile.', 403);
            }

            if (isset($data['user_id']) && $data['user_id'] != $vendor->user_id) {
                return $this->error('You cannot transfer vendor ownership.', 403);
            }

            unset($data['user_id']);
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        $vendor->update($data);

        if ($request->hasFile('logo')) {
            $this->handleSecureLogoUpload($vendor, $request, true);
        }

        return $this->ok('Vendor updated successfully', new VendorResource($vendor));
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
            Log::error('Vendor logo upload failed', [
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

        if ($user->hasPermission('delete_own_vendor')) {
            if ($vendor->user_id !== $user->id) {
                return $this->error('You can only delete your own vendor profile.', 403);
            }

            $activeProducts = ProdDB::where('vendor_id', $vendor->id)->whereNull('deleted_at')->count();
            if ($activeProducts > 0) {
                return $this->error('Cannot delete vendor profile with active products.', 403);
            }
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        $vendor->delete();
        return $this->ok('Vendor deleted successfully');
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('restore_vendors')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $vendor = DB::withTrashed()->findOrFail($id);

        if (!$vendor->trashed()) {
            return $this->error('Vendor is not deleted.', 400);
        }

        $vendor->restore();
        $vendor->load(['user']);

        return $this->ok('Vendor restored successfully.', new VendorResource($vendor));
    }

    public function forceDelete(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('force_delete_vendors')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $vendor = DB::withTrashed()->findOrFail($id);

        if (!$vendor->trashed()) {
            return $this->error('Vendor must be soft deleted before force deleting.', 400);
        }

        $vendor->forceDelete();

        return $this->ok('Vendor permanently deleted.');
    }
}
