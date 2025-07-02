<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\V1\Inventory\InventoryAlertService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use ApiResponses;

    protected InventoryAlertService $inventoryService;

    public function __construct(InventoryAlertService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get inventory overview
     *
     * Returns low stock and out of stock items for admin dashboard.
     * Provides a complete overview of inventory status for immediate action.
     *
     * @group Inventory Management
     * @authenticated
     *
     * @response 200 scenario="Inventory overview retrieved successfully" {
     *   "message": "Inventory overview retrieved successfully.",
     *   "data": {
     *     "low_stock_items": [
     *       {
     *         "type": "product",
     *         "id": 15,
     *         "name": "Wireless Bluetooth Headphones",
     *         "current_stock": 8,
     *         "threshold": 10,
     *         "vendor": "AudioTech Solutions"
     *       },
     *       {
     *         "type": "variant",
     *         "id": 24,
     *         "name": "Wireless Bluetooth Headphones - Color: White",
     *         "current_stock": 3,
     *         "threshold": 5,
     *         "vendor": "AudioTech Solutions"
     *       }
     *     ],
     *     "out_of_stock_items": [
     *       {
     *         "type": "product",
     *         "id": 22,
     *         "name": "USB-C Cable",
     *         "vendor": "TechCorp"
     *       }
     *     ],
     *     "summary": {
     *       "low_stock_count": 2,
     *       "out_of_stock_count": 1,
     *       "total_issues": 3
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function overview(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_inventory')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $lowStockItems = $this->inventoryService->checkAllStock();
        $outOfStockItems = $this->inventoryService->getOutOfStockItems();

        return $this->ok('Inventory overview retrieved successfully.', [
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'summary' => [
                'low_stock_count' => count($lowStockItems),
                'out_of_stock_count' => count($outOfStockItems),
                'total_issues' => count($lowStockItems) + count($outOfStockItems),
            ]
        ]);
    }

    /**
     * Update product stock threshold
     *
     * Updates the low stock threshold for a specific product. When the product's
     * quantity falls to or below this threshold, alerts will be triggered.
     *
     * @group Inventory Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product to update. Example: 15
     *
     * @bodyParam low_stock_threshold integer required The new threshold value (minimum 0). Example: 15
     *
     * @response 200 scenario="Product threshold updated successfully" {
     *   "message": "Product stock threshold updated successfully.",
     *   "data": {
     *     "id": 15,
     *     "name": "Wireless Bluetooth Headphones",
     *     "current_stock": 25,
     *     "old_threshold": 10,
     *     "new_threshold": 15
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\Product] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The low stock threshold field is required.",
     *     "The low stock threshold must be at least 0."
     *   ]
     * }
     */
    public function updateProductThreshold(Request $request, Product $product)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_inventory')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'low_stock_threshold' => 'required|integer|min:0'
        ]);

        $oldThreshold = $product->low_stock_threshold;
        $product->update([
            'low_stock_threshold' => $request->low_stock_threshold
        ]);

        $this->inventoryService->checkProductStock($product);

        return $this->ok('Product stock threshold updated successfully.', [
            'id' => $product->id,
            'name' => $product->name,
            'current_stock' => $product->quantity,
            'old_threshold' => $oldThreshold,
            'new_threshold' => $product->low_stock_threshold,
        ]);
    }

    /**
     * Update variant stock threshold
     *
     * Updates the low stock threshold for a specific product variant. When the variant's
     * quantity falls to or below this threshold, alerts will be triggered.
     *
     * @group Inventory Management
     * @authenticated
     *
     * @urlParam variant integer required The ID of the product variant to update. Example: 24
     *
     * @bodyParam low_stock_threshold integer required The new threshold value (minimum 0). Example: 8
     *
     * @response 200 scenario="Variant threshold updated successfully" {
     *   "message": "Variant stock threshold updated successfully.",
     *   "data": {
     *     "id": 24,
     *     "name": "Wireless Bluetooth Headphones - Color: White",
     *     "current_stock": 20,
     *     "old_threshold": 5,
     *     "new_threshold": 8
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Variant not found" {
     *   "message": "No query results for model [App\\Models\\ProductVariant] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The low stock threshold field is required.",
     *     "The low stock threshold must be at least 0."
     *   ]
     * }
     */
    public function updateVariantThreshold(Request $request, ProductVariant $variant)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_inventory')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'low_stock_threshold' => 'required|integer|min:0'
        ]);

        $oldThreshold = $variant->low_stock_threshold;
        $variant->update([
            'low_stock_threshold' => $request->low_stock_threshold
        ]);

        $this->inventoryService->checkVariantStock($variant);

        return $this->ok('Variant stock threshold updated successfully.', [
            'id' => $variant->id,
            'name' => $variant->product->name . ' - ' . $variant->productAttribute->name . ': ' . $variant->value,
            'current_stock' => $variant->quantity,
            'old_threshold' => $oldThreshold,
            'new_threshold' => $variant->low_stock_threshold,
        ]);
    }

    /**
     * Trigger manual inventory check
     *
     * Manually triggers an inventory check and sends alerts if any items are
     * below their thresholds. Useful for testing or immediate inventory review.
     *
     * @group Inventory Management
     * @authenticated
     *
     * @response 200 scenario="Manual check completed with alerts" {
     *   "message": "Inventory check completed. Alerts sent for 3 items.",
     *   "data": {
     *     "alerts_sent": true,
     *     "low_stock_count": 3,
     *     "items_checked": 156
     *   }
     * }
     *
     * @response 200 scenario="Manual check completed - no alerts needed" {
     *   "message": "Inventory check completed. No alerts needed.",
     *   "data": {
     *     "alerts_sent": false,
     *     "low_stock_count": 0,
     *     "items_checked": 156
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function manualCheck(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_inventory')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $lowStockItems = $this->inventoryService->checkAllStock();
        $totalProducts = Product::count();
        $totalVariants = ProductVariant::count();

        if (!empty($lowStockItems)) {
            $this->inventoryService->checkAndAlert();

            return $this->ok("Inventory check completed. Alerts sent for " . count($lowStockItems) . " items.", [
                'alerts_sent' => true,
                'low_stock_count' => count($lowStockItems),
                'items_checked' => $totalProducts + $totalVariants,
            ]);
        }

        return $this->ok('Inventory check completed. No alerts needed.', [
            'alerts_sent' => false,
            'low_stock_count' => 0,
            'items_checked' => $totalProducts + $totalVariants,
        ]);
    }

    /**
     * Bulk update stock thresholds
     *
     * Updates stock thresholds for multiple products and variants in a single request.
     * Useful for bulk inventory management operations.
     *
     * @group Inventory Management
     * @authenticated
     *
     * @bodyParam products array optional Array of product threshold updates.
     * @bodyParam products.*.id integer required Product ID. Example: 15
     * @bodyParam products.*.low_stock_threshold integer required New threshold value. Example: 20
     * @bodyParam variants array optional Array of variant threshold updates.
     * @bodyParam variants.*.id integer required Variant ID. Example: 24
     * @bodyParam variants.*.low_stock_threshold integer required New threshold value. Example: 10
     *
     * @response 200 scenario="Bulk update completed successfully" {
     *   "message": "Bulk threshold update completed successfully.",
     *   "data": {
     *     "updated_products": 3,
     *     "updated_variants": 5,
     *     "total_updated": 8,
     *     "new_alerts_triggered": 2
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The products.0.id field is required.",
     *     "The variants.1.low_stock_threshold must be at least 0."
     *   ]
     * }
     */
    public function bulkUpdateThresholds(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_inventory')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'products' => 'array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.low_stock_threshold' => 'required|integer|min:0',
            'variants' => 'array',
            'variants.*.id' => 'required|exists:product_variants,id',
            'variants.*.low_stock_threshold' => 'required|integer|min:0',
        ]);

        $updatedProducts = 0;
        $updatedVariants = 0;
        $newAlertsTriggered = 0;

        if ($request->has('products')) {
            foreach ($request->products as $productData) {
                $product = Product::find($productData['id']);
                if ($product) {
                    $product->update(['low_stock_threshold' => $productData['low_stock_threshold']]);
                    $updatedProducts++;

                    if ($product->quantity <= $product->low_stock_threshold && $product->quantity > 0) {
                        $this->inventoryService->checkProductStock($product);
                        $newAlertsTriggered++;
                    }
                }
            }
        }

        if ($request->has('variants')) {
            foreach ($request->variants as $variantData) {
                $variant = ProductVariant::find($variantData['id']);
                if ($variant) {
                    $variant->update(['low_stock_threshold' => $variantData['low_stock_threshold']]);
                    $updatedVariants++;

                    if ($variant->quantity <= $variant->low_stock_threshold && $variant->quantity > 0) {
                        $this->inventoryService->checkVariantStock($variant);
                        $newAlertsTriggered++;
                    }
                }
            }
        }

        return $this->ok('Bulk threshold update completed successfully.', [
            'updated_products' => $updatedProducts,
            'updated_variants' => $updatedVariants,
            'total_updated' => $updatedProducts + $updatedVariants,
            'new_alerts_triggered' => $newAlertsTriggered,
        ]);
    }
}
