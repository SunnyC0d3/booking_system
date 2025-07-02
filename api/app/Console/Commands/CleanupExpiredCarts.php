<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredCarts extends Command
{
    protected $signature = 'cart:cleanup-expired {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up expired shopping carts and their items';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting expired cart cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $expiredCarts = Cart::expired()->with('cartItems');

        $cartCount = $expiredCarts->count();
        $itemCount = $expiredCarts->get()->sum(function ($cart) {
            return $cart->cartItems->count();
        });

        if ($cartCount === 0) {
            $this->info('No expired carts found.');
            return 0;
        }

        $this->info("Found {$cartCount} expired carts with {$itemCount} total items.");

        if ($dryRun) {
            $this->table(
                ['Cart ID', 'User ID', 'Session ID', 'Items Count', 'Expired At'],
                $expiredCarts->get()->map(function ($cart) {
                    return [
                        $cart->id,
                        $cart->user_id ?? 'Guest',
                        $cart->session_id ? substr($cart->session_id, 0, 8) . '...' : 'N/A',
                        $cart->cartItems->count(),
                        $cart->expires_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );

            $this->info('Use without --dry-run to actually delete these carts.');
            return 0;
        }

        if (!$this->confirm("Are you sure you want to delete {$cartCount} expired carts?")) {
            $this->info('Cleanup cancelled.');
            return 0;
        }

        $deleted = $expiredCarts->delete();

        Log::info('Expired carts cleaned up', [
            'carts_deleted' => $deleted,
            'items_deleted' => $itemCount,
        ]);

        $this->info("Successfully deleted {$deleted} expired carts and {$itemCount} cart items.");

        return 0;
    }
}
