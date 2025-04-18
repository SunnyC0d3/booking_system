<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Users\Vendor;
use App\Models\Vendor as DB;
use Illuminate\Http\Request;
use App\Requests\V1\StoreVendorRequest;
use App\Requests\V1\UpdateVendorRequest;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Filters\V1\VendorFilter;
use \Exception;

class VendorController extends Controller
{
    use ApiResponses;

    private $vendor;

    public function __construct(Vendor $vendor)
    {
        $this->vendor = $vendor;
    }

    /**
     * Retrieve a paginated list of vendors.
     *
     * @group Vendors
     * @authenticated
     *
     * @response 200 {
     *     "message": "Vendors retrieved successfully.",
     *     "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request, VendorFilter $filter)
    {
        try {
            return $this->vendor->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new vendor.
     *
     * @group Vendors
     * @authenticated
     *
     * @response 200 {
     *     "message": "Vendors created successfully!",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreVendorRequest $request)
    {
        try {
            return $this->vendor->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific vendor.
     *
     * @group Vendors
     * @authenticated
     *
     * @response 200 {
     *     "message": "Vendor details retrieved.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, DB $vendor)
    {
        try {
            return $this->vendor->find($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing vendor.
     *
     * @group Vendors
     * @authenticated
     *
     * @response 200 {
     *     "message": "Vendor updated successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateVendorRequest $request, DB $vendor)
    {
        try {
            return $this->vendor->update($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete a vendor.
     *
     * @group Vendors
     * @authenticated
     *
     * @response 200 {
     *     "message": "Vendor deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, DB $vendor)
    {
        try {
            return $this->vendor->delete($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
