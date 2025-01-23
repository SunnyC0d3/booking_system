<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Auth\V1\UserAuth;
use App\Filters\V1\ProductFilter;
use App\Resources\V1\ProductResource;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreProductRequest;
use App\Requests\V1\UpdateProductRequest;
use App\Requests\V1\DeleteProductRequest;

class ProductController extends Controller
{
    use ApiResponses;

    private $userAuth;

    public function __construct(UserAuth $userAuth)
    {
        $this->userAuth = $userAuth;
    }

    public function index(Request $request, ProductFilter $filter)
    {
        try {
            if ($this->userAuth->checkRole(['admin']) && $this->userAuth->hasPermission('read-products')) {
                $query = Product::with(['categories', 'images', 'attributes'])->filter($filter);
                $perPage = $request->input('per_page', 15);
                $products = $query->paginate($perPage);

                return ProductResource::collection($products);
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function create() {}

    public function show(Product $product)
    {
        try {
            if ($this->userAuth->checkRole(['admin'])) {
                $product->load(['attributes', 'images']);
                return ProductResource::collection($product);
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $product = Product::create($data);

        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attribute) {
                $product->attributes()->create($attribute);
            }
        }

        if (isset($data['images'])) {
            foreach ($data['images'] as $image) {
                $product->images()->create(['path' => $image->store('product_images', 'public')]);
            }
        }

        return ProductResource::collection($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $product->update($data);

        if (isset($data['attributes'])) {
            $product->attributes()->delete();
            foreach ($data['attributes'] as $attribute) {
                $product->attributes()->create($attribute);
            }
        }

        if (isset($data['images'])) {
            $product->images()->delete();
            foreach ($data['images'] as $image) {
                $product->images()->create(['path' => $image->store('product_images', 'public')]);
            }
        }

        return ProductResource::collection($product);
    }

    public function destroy(DeleteProductRequest $request)
    {
        $productId = $request->validated()['id'];
        $product = Product::findOrFail($productId);
        $product->delete();

        return $this->ok('Product deleted successfully.', [], 200);
    }
}
