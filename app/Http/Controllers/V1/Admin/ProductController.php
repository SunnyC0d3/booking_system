<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Filters\V1\ProductFilter;
use App\Resources\V1\ProductResource;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreProductRequest;
use App\Requests\V1\UpdateProductRequest;
use App\Requests\V1\DeleteProductRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    use ApiResponses;

    public function index(Request $request, ProductFilter $filter)
    {
        $user = Auth::user();

        try {
            if ($user->hasPermission('view_products')) {
                $query = Product::with(['vendor', 'productVariants', 'category', 'media'])->filter($filter);
                $perPage = $request->input('per_page', 15);
                $products = $query->paginate($perPage);

                return $this->ok(ProductResource::collection($products));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $product->load(['vendor', 'productVariants', 'media']);
            return $this->ok(ProductResource::collection($product));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $data = $request->validated();

            DB::transaction(function () use ($data, &$product) {
                $product = Product::create($data);

                if (isset($data['product_variants'])) {
                    foreach ($data['product_variants'] as $variant) {
                        $product->productVariants()->create($variant);
                    }
                }

                if (isset($data['media'])) {
                    foreach ($data['media'] as $media) {
                        $product->addMedia($media->store('product_media', 'public'))->toMediaCollection();
                    }
                }
            });

            return $this->ok(ProductResource::collection($product));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $data = $request->validated();

            DB::transaction(function () use ($data, $product) {
                $product->update($data);

                if (isset($data['product_variants'])) {
                    $product->productVariants()->delete();
                    foreach ($data['product_variants'] as $variant) {
                        $product->productVariants()->create($variant);
                    }
                }

                if (isset($data['media'])) {
                    $product->clearMediaCollection();
                    foreach ($data['media'] as $media) {
                        $product->addMedia($media->store('product_media', 'public'))->toMediaCollection();
                    }
                }
            });

            return $this->ok(ProductResource::collection($product));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(DeleteProductRequest $request)
    {
        try {
            $productId = $request->validated()['id'];
            $product = Product::findOrFail($productId);
            $product->delete();

            return $this->ok('Product deleted successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
