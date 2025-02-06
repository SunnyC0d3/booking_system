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
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ApiResponses;

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

        $user = $request->user();

        try {
            if ($user->hasPermission('view_products')) {
                $query = Product::with(['vendor', 'variants', 'category', 'tags', 'media'])->filter($filter);
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
                $product->load(['vendor', 'variants', 'category', 'tags', 'media']);
                return $this->ok(new ProductResource($product));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreProductRequest $request)
    {
        $request->validated($request->only([
            'name',
            'description',
            'price',
            'product_category_id',
            'product_status_id',
            'quantity',
            'product_tags',
            'product_tags.*',
            'product_variants',
            'product_variants.*.product_attribute_id',
            'product_variants.*.value',
            'product_variants.*.additional_price',
            'product_variants.*.quanatity',
            'media',
            'media.*',
            'media.feature_image',
            'media.gallery.*'
        ]));

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

                    if (!empty($data['product_tags'])) {
                        $product->tags()->sync($data['product_tags']);
                    }

                    if (!empty($data['product_variants'])) {
                        foreach ($data['product_variants'] as $variant) {
                            $product->variants()->create([
                                'product_id' => $product->id,
                                'product_attribute_id' => $variant['product_attribute_id'],
                                'value' => $variant['value'],
                                'additional_price' => $variant['additional_price'],
                                'quantity' => $variant['quantity']
                            ]);
                        }
                    }

                    if (!empty($data['media'])) {
                        if (!empty($data['media']['featured_image'])) {
                            $product->addMediaFromRequest('media.featured_image')->toMediaCollection('featured_image');
                        }

                        if (!empty($data['media']['gallery'])) {
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
        $request->validated($request->only([
            'name',
            'description',
            'price',
            'product_category_id',
            'product_status_id',
            'quantity',
            'product_tags',
            'product_tags.*',
            'product_variants',
            'product_variants.*.product_attribute_id',
            'product_variants.*.value',
            'product_variants.*.additional_price',
            'product_variants.*.quanatity',
            'media',
            'media.*',
            'media.feature_image',
            'media.gallery.*'
        ]));

        $user = $request->user();

        try {
            if ($user->hasPermission('edit_products')) {
                $data = $request->validated();

                DB::transaction(function () use ($data, $product) {
                    $product->update([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'price' => $data['price'],
                        'quantity' => $data['quantity'],
                        'product_category_id' => $data['product_category_id'],
                        'product_status_id' => $data['product_status_id'],
                    ]);

                    if (!empty($data['product_tags'])) {
                        $product->tags()->sync($data['product_tags']);
                    }

                    if (isset($data['product_variants'])) {
                        $product->variants()->delete();
                        foreach ($data['product_variants'] as $variant) {
                            $product->variants()->create([
                                'product_id' => $product->id,
                                'product_attribute_id' => $variant['product_attribute_id'],
                                'value' => $variant['value'],
                                'additional_price' => $variant['additional_price'],
                                'quantity' => $variant['quantity']
                            ]);
                        }
                    }

                    if (!empty($data['media'])) {
                        if (!empty($data['media']['featured_image'])) {
                            $product->clearMediaCollection('featured_image');
                            $product->addMediaFromRequest('media.featured_image')->toMediaCollection('featured_image');
                        }

                        if (!empty($data['media']['gallery'])) {
                            $product->clearMediaCollection('gallery');
                            foreach ($data['media']['gallery'] as $galleryItem) {
                                $product->addMedia($galleryItem)->toMediaCollection('gallery');
                            }
                        }
                    }
                });

                return $this->ok('Product updated successfully!', new ProductResource($product));
            }

            return $this->error('You do not have the required permissions.', 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function softDestroy(Request $request, Product $product)
    {
        $user = $request->user();

        try {
            if (!$user->hasPermission('delete_products')) {
                return $this->error('You do not have the required permissions.', 403);
            }

            DB::transaction(function () use ($product) {
                $product->delete();
            });

            return $this->ok('Product deleted successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        try {
            if (!$user->hasPermission('delete_products')) {
                return $this->error('You do not have the required permissions.', 403);
            }

            DB::transaction(function () use ($product) {
                $product->clearMediaCollection('featured_image');
                $product->clearMediaCollection('gallery');
                $product->variants()->forceDelete();
                $product->forceDelete();
            });

            return $this->ok('Product deleted successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
