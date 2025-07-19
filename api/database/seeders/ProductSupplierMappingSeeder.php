<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use Illuminate\Database\Seeder;

class ProductSupplierMappingSeeder extends Seeder
{
    public function run(): void
    {
        $this->createDropshipProducts();
        $this->createProductMappings();
    }

    private function createDropshipProducts(): void
    {
        $supplierProducts = SupplierProduct::with('supplier')->get();

        foreach ($supplierProducts as $supplierProduct) {
            $product = $this->createProductFromSupplierProduct($supplierProduct);

            $supplierProduct->update([
                'product_id' => $product->id,
                'is_mapped' => true,
            ]);
        }
    }

    private function createProductFromSupplierProduct(SupplierProduct $supplierProduct): Product
    {
        $markupPercentage = $this->getMarkupPercentage($supplierProduct->supplier->name);
        $retailPrice = (int) round($supplierProduct->supplier_price * (1 + ($markupPercentage / 100)));

        $profitMargin = round((($retailPrice - $supplierProduct->supplier_price) / $retailPrice) * 100, 2);

        return Product::create([
            'vendor_id' => 1,
            'product_category_id' => $this->getCategoryIdFromSupplierProduct($supplierProduct),
            'name' => $supplierProduct->name,
            'description' => $supplierProduct->description,
            'price' => $retailPrice,
            'quantity' => max(0, $supplierProduct->stock_quantity - 5),
            'product_status_id' => 1,
            'low_stock_threshold' => 10,
            'weight' => $supplierProduct->weight,
            'length' => $supplierProduct->length,
            'width' => $supplierProduct->width,
            'height' => $supplierProduct->height,
            'shipping_class' => $this->getShippingClass($supplierProduct),
            'requires_shipping' => true,
            'is_virtual' => false,
            'is_dropship' => true,
            'primary_supplier_id' => $supplierProduct->supplier_id,
            'dropship_sync_status' => 'synced',
            'last_supplier_sync' => now(),
            'supplier_cost' => $supplierProduct->supplier_price,
            'profit_margin_percentage' => $profitMargin,
            'supplier_processing_days' => $supplierProduct->processing_time_days ?? 2,
            'auto_fulfill_dropship' => $supplierProduct->supplier->auto_fulfill,
            'supplier_data' => [
                'supplier_sku' => $supplierProduct->supplier_sku,
                'supplier_product_id' => $supplierProduct->supplier_product_id,
                'attributes' => $supplierProduct->attributes,
                'categories' => $supplierProduct->categories,
                'images' => $supplierProduct->images,
            ],
            'handling_time_days' => $supplierProduct->processing_time_days ?? 2,
        ]);
    }

    private function createProductMappings(): void
    {
        $products = Product::where('is_dropship', true)->get();

        foreach ($products as $product) {
            $supplierProduct = SupplierProduct::where('product_id', $product->id)->first();

            if ($supplierProduct) {
                ProductSupplierMapping::create([
                    'product_id' => $product->id,
                    'supplier_id' => $supplierProduct->supplier_id,
                    'supplier_product_id' => $supplierProduct->id,
                    'is_primary' => true,
                    'is_active' => true,
                    'priority_order' => 1,
                    'markup_percentage' => $this->getMarkupPercentage($supplierProduct->supplier->name),
                    'markup_type' => 'percentage',
                    'minimum_stock_threshold' => 5,
                    'auto_update_price' => $supplierProduct->supplier->price_sync_enabled,
                    'auto_update_stock' => $supplierProduct->supplier->stock_sync_enabled,
                    'auto_update_description' => false,
                    'field_mappings' => $this->getFieldMappings($supplierProduct->supplier->name),
                    'last_price_update' => now()->subHours(rand(1, 48)),
                    'last_stock_update' => now()->subMinutes(rand(30, 1440)),
                ]);
            }
        }

        $this->createMultiSupplierMappings();
    }

    private function createMultiSupplierMappings(): void
    {
        $globalTech = Supplier::where('name', 'GlobalTech Distributors')->first();
        $techGadgets = Supplier::where('name', 'TechGadgets Pro')->first();

        if ($globalTech && $techGadgets) {
            $headphones = Product::where('name', 'like', '%Wireless Bluetooth Headphones%')->first();
            $chargingProducts = Product::where('name', 'like', '%Charging%')->get();

            if ($headphones) {
                $techHeadphones = SupplierProduct::where('supplier_id', $techGadgets->id)
                    ->where('name', 'like', '%Wireless%')
                    ->first();

                if ($techHeadphones) {
                    ProductSupplierMapping::create([
                        'product_id' => $headphones->id,
                        'supplier_id' => $techGadgets->id,
                        'supplier_product_id' => $techHeadphones->id,
                        'is_primary' => false,
                        'is_active' => true,
                        'priority_order' => 2,
                        'markup_percentage' => 65.0,
                        'markup_type' => 'percentage',
                        'minimum_stock_threshold' => 3,
                        'auto_update_price' => false,
                        'auto_update_stock' => true,
                        'auto_update_description' => false,
                        'field_mappings' => $this->getFieldMappings('TechGadgets Pro'),
                    ]);
                }
            }
        }
    }

    private function getMarkupPercentage(string $supplierName): float
    {
        return match($supplierName) {
            'GlobalTech Distributors' => 78.0,
            'Fashion Forward Wholesale' => 108.0,
            'HomeDecor Direct' => 128.0,
            'Asian Marketplace Hub' => 87.0,
            'TechGadgets Pro' => 84.0,
            'EcoFriendly Supplies' => 133.0,
            default => 100.0,
        };
    }

    private function getCategoryIdFromSupplierProduct(SupplierProduct $supplierProduct): int
    {
        $categories = $supplierProduct->categories ?? [];

        if (in_array('Electronics', $categories)) {
            return 1;
        } elseif (in_array('Fashion', $categories) || in_array('Clothing', $categories)) {
            return 2;
        } elseif (in_array('Home Decor', $categories) || in_array('Kitchen', $categories)) {
            return 3;
        }

        return 1;
    }

    private function getShippingClass(SupplierProduct $supplierProduct): string
    {
        $weight = $supplierProduct->weight;
        $categories = $supplierProduct->categories ?? [];

        if ($weight > 5) {
            return 'heavy';
        } elseif (in_array('Electronics', $categories)) {
            return 'fragile';
        } elseif ($supplierProduct->supplier->integration_type === 'api') {
            return 'express';
        }

        return 'standard';
    }

    private function getFieldMappings(string $supplierName): array
    {
        return match($supplierName) {
            'GlobalTech Distributors' => [
                'name' => 'product_name',
                'description' => 'product_description',
                'price' => 'wholesale_price',
                'stock' => 'available_quantity',
                'sku' => 'item_code',
                'weight' => 'shipping_weight',
                'images' => 'product_images',
            ],
            'Fashion Forward Wholesale' => [
                'name' => 'title',
                'description' => 'full_description',
                'price' => 'cost_price',
                'stock' => 'inventory_count',
                'sku' => 'style_number',
                'weight' => 'item_weight',
                'sizes' => 'available_sizes',
                'colors' => 'color_options',
            ],
            'HomeDecor Direct' => [
                'name' => 'item_name',
                'description' => 'item_details',
                'price' => 'trade_price',
                'stock' => 'stock_level',
                'sku' => 'product_ref',
                'dimensions' => 'measurements',
            ],
            'Asian Marketplace Hub' => [
                'name' => 'product_title',
                'description' => 'details',
                'price' => 'unit_price',
                'stock' => 'qty_available',
                'sku' => 'product_id',
                'origin' => 'country_of_origin',
            ],
            'TechGadgets Pro' => [
                'name' => 'device_name',
                'description' => 'tech_specs',
                'price' => 'dealer_price',
                'stock' => 'units_in_stock',
                'sku' => 'model_number',
                'warranty' => 'warranty_period',
                'features' => 'key_features',
            ],
            default => [
                'name' => 'name',
                'description' => 'description',
                'price' => 'price',
                'stock' => 'stock',
                'sku' => 'sku',
            ],
        };
    }
}
