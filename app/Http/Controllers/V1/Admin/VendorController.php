<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Users\Vendor;
use App\Models\Vendor as VendorDB;
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

    public function index(Request $request, VendorFilter $filter)
    {
        try {
            return $this->vendor->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreVendorRequest $request)
    {
        try {
            return $this->vendor->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(Request $request, VendorDB $vendor)
    {
        try {
            return $this->vendor->find($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateVendorRequest $request, VendorDB $vendor)
    {
        try {
            return $this->vendor->update($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, VendorDB $vendor)
    {
        try {
            return $this->vendor->delete($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
