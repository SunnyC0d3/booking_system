<?php

namespace App\Http\Controllers\V1\Public;

use App\Requests\V1\StoreReturnRequest;
use App\Services\V1\Orders\Returns;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class ReturnsController extends Controller
{
    use ApiResponses;

    private $returns;

    public function __construct(Returns $returns)
    {
        $this->returns = $returns;
    }

    public function return(StoreReturnRequest $request)
    {
        try {
            $this->returns->createReturn($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
