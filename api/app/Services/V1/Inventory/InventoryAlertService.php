<?php

namespace App\Services\V1\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InventoryAlertService
{
    protected int $alertCooldownMinutes = 60;

    public function checkAllStock(): array
    {
        $lowStockItems = [];

        $lowStockProducts = Product::where('quantity', '>', 0)
            ->whereRaw('quantity <= low_stock_threshold')
            ->with(['vendor', 'category'])
            ->get();

        foreach ($lowStockProducts as $product) {
            $lowStockItems[] = [
                'type' => 'product',
                'id' => $product->id,
                'name' => $product->name,
                'current_stock' => $product->quantity,
                'threshold' => $product->low_stock_threshold,
                'vendor' => $product->vendor->name ?? 'No vendor',
            ];
        }

        $lowStockVariants = ProductVariant::where('quantity', '>', 0)
            ->whereRaw('quantity <= low_stock_threshold')
            ->with(['product.vendor', 'productAttribute'])
            ->get();

        foreach ($lowStockVariants as $variant) {
            $lowStockItems[] = [
                'type' => 'variant',
                'id' => $variant->id,
                'name' => $variant->product->name . ' - ' . $variant->productAttribute->name . ': ' . $variant->value,
                'current_stock' => $variant->quantity,
                'threshold' => $variant->low_stock_threshold,
                'vendor' => $variant->product->vendor->name ?? 'No vendor',
            ];
        }

        return $lowStockItems;
    }

    public function checkAndAlert(): void
    {
        $lowStockItems = $this->checkAllStock();

        if (empty($lowStockItems)) {
            return;
        }

        $cacheKey = 'inventory_alert_sent';
        if (Cache::has($cacheKey)) {
            return;
        }

        $this->sendAlert($lowStockItems);

        Cache::put($cacheKey, true, now()->addMinutes($this->alertCooldownMinutes));

        Log::info('Inventory alert sent', [
            'low_stock_count' => count($lowStockItems),
            'items' => $lowStockItems
        ]);
    }

    public function checkProductStock(Product $product): void
    {
        if ($product->quantity <= $product->low_stock_threshold && $product->quantity > 0) {
            $this->sendSingleItemAlert([
                'type' => 'product',
                'id' => $product->id,
                'name' => $product->name,
                'current_stock' => $product->quantity,
                'threshold' => $product->low_stock_threshold,
                'vendor' => $product->vendor->name ?? 'No vendor',
            ]);
        }
    }

    public function checkVariantStock(ProductVariant $variant): void
    {
        if ($variant->quantity <= $variant->low_stock_threshold && $variant->quantity > 0) {
            $this->sendSingleItemAlert([
                'type' => 'variant',
                'id' => $variant->id,
                'name' => $variant->product->name . ' - ' . $variant->productAttribute->name . ': ' . $variant->value,
                'current_stock' => $variant->quantity,
                'threshold' => $variant->low_stock_threshold,
                'vendor' => $variant->product->vendor->name ?? 'No vendor',
            ]);
        }
    }

    protected function sendSingleItemAlert(array $item): void
    {
        $cacheKey = "inventory_alert_item_{$item['type']}_{$item['id']}";

        if (Cache::has($cacheKey)) {
            return;
        }

        $adminEmails = $this->getAdminEmails();

        foreach ($adminEmails as $email) {
            try {
                Mail::raw($this->buildSingleItemEmailContent($item), function ($message) use ($email, $item) {
                    $message->to($email)
                        ->subject("Low Stock Alert: {$item['name']}")
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            } catch (\Exception $e) {
                Log::error('Failed to send single item inventory alert', [
                    'email' => $email,
                    'item' => $item,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Cache::put($cacheKey, true, now()->addHours(4));
    }

    protected function buildEmailContent(array $lowStockItems): string
    {
        $content = "Low Stock Alert\n\n";
        $content .= "The following items are running low on stock:\n\n";

        foreach ($lowStockItems as $item) {
            $content .= "• {$item['name']}\n";
            $content .= "  Current Stock: {$item['current_stock']}\n";
            $content .= "  Threshold: {$item['threshold']}\n";
            $content .= "  Vendor: {$item['vendor']}\n";
            $content .= "  Type: " . ucfirst($item['type']) . "\n\n";
        }

        $content .= "Please restock these items as soon as possible.\n\n";
        $content .= "Best regards,\n";
        $content .= config('app.name') . " Inventory System";

        return $content;
    }

    protected function buildSingleItemEmailContent(array $item): string
    {
        $content = "Low Stock Alert\n\n";
        $content .= "The following item is running low on stock:\n\n";
        $content .= "• {$item['name']}\n";
        $content .= "  Current Stock: {$item['current_stock']}\n";
        $content .= "  Threshold: {$item['threshold']}\n";
        $content .= "  Vendor: {$item['vendor']}\n";
        $content .= "  Type: " . ucfirst($item['type']) . "\n\n";
        $content .= "Please restock this item as soon as possible.\n\n";
        $content .= "Best regards,\n";
        $content .= config('app.name') . " Inventory System";

        return $content;
    }

    public function getOutOfStockItems(): array
    {
        $outOfStockItems = [];

        $outOfStockProducts = Product::where('quantity', 0)
            ->with(['vendor', 'category'])
            ->get();

        foreach ($outOfStockProducts as $product) {
            $outOfStockItems[] = [
                'type' => 'product',
                'id' => $product->id,
                'name' => $product->name,
                'vendor' => $product->vendor->name ?? 'No vendor',
            ];
        }

        $outOfStockVariants = ProductVariant::where('quantity', 0)
            ->with(['product.vendor', 'productAttribute'])
            ->get();

        foreach ($outOfStockVariants as $variant) {
            $outOfStockItems[] = [
                'type' => 'variant',
                'id' => $variant->id,
                'name' => $variant->product->name . ' - ' . $variant->productAttribute->name . ': ' . $variant->value,
                'vendor' => $variant->product->vendor->name ?? 'No vendor',
            ];
        }

        return $outOfStockItems;
    }

    protected function sendAlert(array $lowStockItems): void
    {
        $adminEmails = $this->getAdminEmails();

        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)->send(new LowStockAlertMail([
                    'items' => $lowStockItems,
                    'total_items' => count($lowStockItems),
                    'urgent_items' => collect($lowStockItems)->where('current_stock', '<=', 0)->count(),
                    'generated_at' => now()->format('M j, Y g:i A')
                ]));

                Log::info('Low stock alert email sent', [
                    'email' => $email,
                    'items_count' => count($lowStockItems)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to send inventory alert email', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function getAdminEmails(): array
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super admin', 'admin']);
        })->pluck('email')->toArray();
    }
}
