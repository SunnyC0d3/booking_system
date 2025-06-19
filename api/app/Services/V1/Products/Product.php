<?php

namespace App\Services\V1\Products;

use App\Models\Order as OrderDB;
use App\Models\Product as ProdDB;
use App\Services\V1\Media\SecureMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use App\Filters\V1\ProductFilter;
use App\Resources\V1\ProductResource;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;

class Product
{
    use ApiResponses;

    protected SecureMedia $mediaService;

    public function __construct(SecureMedia $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function all(Request $request, ProductFilter $filter)
    {
        $request->validated();

        $query = ProdDB::with(['vendor', 'variants', 'category', 'tags', 'media'])->filter($filter);
        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return $this->ok('Products retrieved successfully.', ProductResource::collection($products));
    }

    public function find(Request $request, ProdDB $product)
    {
        $product->load(['vendor', 'variants', 'category', 'tags', 'media']);
        return $this->ok('Product retrieved successfully.', new ProductResource($product));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_products')) {
            $data = $request->validated();

            $vendor = Vendor::where('user_id', $user->id)->first();

            if (!$vendor) {
                return $this->error('Vendor not found.', 404);
            }

            $product = DB::transaction(function () use ($data, $vendor, $request) {
                $product = ProdDB::create([
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
                    $this->handleSecureMediaUpload($product, $request);
                }

                return $product;
            });

            return $this->ok('Product created successfully.', new ProductResource($product));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, ProdDB $product)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_products')) {
            $data = $request->validated();

            DB::transaction(function () use ($data, $product, $request) {
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
                    $this->handleSecureMediaUpload($product, $request, true);
                }
            });

            return $this->ok('Product updated successfully.', new ProductResource($product));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    protected function handleSecureMediaUpload(ProdDB $product, Request $request, bool $isUpdate = false): void
    {
        try {
            if ($request->hasFile('media.featured_image')) {
                if ($isUpdate) {
                    $product->clearMediaCollection('featured_image');
                }

                $this->mediaService->addSecureMedia(
                    $product,
                    $request->file('media.featured_image'),
                    'featured_image'
                );
            }

            if ($request->hasFile('media.gallery')) {
                if ($isUpdate) {
                    $product->clearMediaCollection('gallery');
                }

                $galleryFiles = $request->file('media.gallery');

                if (!is_array($galleryFiles)) {
                    $galleryFiles = [$galleryFiles];
                }

                foreach ($galleryFiles as $galleryFile) {
                    if ($galleryFile && $galleryFile->isValid()) {
                        $this->mediaService->addSecureMedia(
                            $product,
                            $galleryFile,
                            'gallery'
                        );
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Product media upload failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to process media files: ' . $e->getMessage());
        }
    }

    public function softDelete(Request $request, ProdDB $product)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_products')) {
            $product->delete();
            return $this->ok('Product deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if ($user->hasPermission('restore_products')) {
            $product = ProdDB::withTrashed()->findOrFail($id);

            if (!$product->trashed()) {
                return $this->error('Product is not deleted.', 400);
            }

            $product->restore();

            $product->load(['user', 'orderItems.product', 'orderItems.productVariant', 'status']);

            return $this->success('Product restored successfully.', new ProductResource($product));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, int $id)
    {
        $user = $request->user();

        if ($user->hasPermission('force_delete_products')) {
            $product = ProdDB::withTrashed()->findOrFail($id);

            if (!$product->trashed()) {
                return $this->error('Product must be soft deleted before force deleting.', 400);
            }

            DB::transaction(function () use ($product) {
                $product->clearMediaCollection('featured_image');
                $product->clearMediaCollection('gallery');
                $product->variants()->forceDelete();
                $product->forceDelete();
            });

            return $this->ok('Product deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}
