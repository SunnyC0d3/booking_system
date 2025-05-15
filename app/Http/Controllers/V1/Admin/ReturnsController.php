<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Orders\Returns;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use Illuminate\Http\Request;

class ReturnsController extends Controller
{
    use ApiResponses;

    private $returns;

    public function __construct(Returns $returns)
    {
        $this->returns = $returns;
    }

    public function index(Request $request)
    {
        try {
            return $this->returns->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function reviewReturn(Request $request, int $returnId, string $action)
    {
        try {
            return $this->returns->reviewReturn($request, $returnId, $action);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
