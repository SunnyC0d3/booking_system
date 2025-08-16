<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\PaymentMethod as DB;
use App\Requests\V1\StorePaymentMethodRequest;
use App\Requests\V1\UpdatePaymentMethodRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use App\Services\V1\Payments\PaymentMethod;

class PaymentMethodController extends Controller
{
    use ApiResponses;

    private $paymentMethod;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function index(Request $request) {
        try {
            return $this->paymentMethod->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StorePaymentMethodRequest $request) {
        try {
            return $this->paymentMethod->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdatePaymentMethodRequest $request, DB $paymentMethod) {
        try {
            return $this->paymentMethod->update($request, $paymentMethod);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, DB $paymentMethod) {
        try {
            return $this->paymentMethod->delete($request, $paymentMethod);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
