<?php

namespace App\Http\Controllers\V1\Public;

use App\Models\Product as ProdDB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Filters\V1\ProductFilter;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\FilterProductRequest;
use App\Services\V1\Products\Product;

class ProductController extends Controller
{
    use ApiResponses;

    private $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Retrieve a paginated list of products.
     *
     * @group Products
     *
     * @response 200 {
     *     "message": "Products retrieved successfully.",
     *     "data": []
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(FilterProductRequest $request, ProductFilter $filter)
    {
        $request->validated($request->only([
            'filter' => [
                'name',
                'price',
                'category',
                'quantity',
                'created_at',
                'updated_at',
                'search',
                'include'
            ],
            'page',
            'per_page',
            'sort',
        ]));

        try {
            return $this->product->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific product.
     *
     * @group Products
     *
     * @response 200 {
     *     "message": "Product retrieved successfully.",
     *     "data": {}
     * }
     * 
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, ProdDB $product)
    {
        try {
            return $this->product->find($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

