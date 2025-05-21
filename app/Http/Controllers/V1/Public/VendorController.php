<?php

namespace App\Http\Controllers\V1\Public;

use App\Services\V1\Users\Vendor;
use App\Models\Vendor as DB;
use Illuminate\Http\Request;
use App\Requests\V1\UpdateVendorRequest;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
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
}
