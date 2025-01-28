<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Filters\V1\ProductFilter;
use App\Resources\V1\ProductResource;
use \Exception;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\FilterProductRequest;
use App\Requests\V1\StoreProductRequest;
use App\Requests\V1\UpdateProductRequest;
use App\Requests\V1\DeleteProductRequest;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ApiResponses;

    public function index(FilterProductRequest $request, ProductFilter $filter)
    {
        $user = $request->user();

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

    public function show(Request $request, Product $product)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('view_products')) {
                $product->load(['vendor', 'productVariants', 'category', 'media']);
                return $this->ok(new ProductResource($product));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('create_products')) {
                $data = $request->validated();

                $vendor = Vendor::where('user_id', $user->id)->first();

                if (!$vendor) {
                    return $this->error('Vendor not found.', 404);
                }

                $product = DB::transaction(function () use ($data, $vendor) {
                    $product = Product::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'price' => $data['price'],
                        'quantity' => $data['quantity'],
                        'product_category_id' => $data['product_category_id'],
                        'vendor_id' => $vendor->id,
                        'product_status_id' => $data['product_status_id'],
                    ]);

                    if (!empty($data['product_variants'])) {
                        foreach ($data['product_variants'] as $variant) {
                            $product->productVariants()->create([
                                'product_id' => $product->id,
                                'product_attribute_id' => $variant['product_attribute_id'],
                                'value' => $variant['value'],
                                'additional_price' => $variant['additional_price'],
                                'quantity' => $variant['quantity']
                            ]);
                        }
                    }

                    if (!empty($data['media'])) {
                        if(!empty($data['media']['featured_image'])) {
                            $product->addMediaFromRequest('media.featured_image')->toMediaCollection('featured_image');
                        }

                        if(!empty($data['media']['gallery'])) {
                            foreach ($data['media']['gallery'] as $media) {
                                $product->addMedia($media)->toMediaCollection('gallery');
                            }
                        }
                    }

                    return $product;
                });

                return $this->ok('Product created successfully!', new ProductResource($product));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('edit_products')) {
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
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(DeleteProductRequest $request)
    {
        $user = $request->user();

        try {
            if ($user->hasPermission('delete_products')) {
                $productId = $request->validated()['id'];
                $product = Product::findOrFail($productId);
                $product->delete();

                return $this->ok('Product deleted successfully.');
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
