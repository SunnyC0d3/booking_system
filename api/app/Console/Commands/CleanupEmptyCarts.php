<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupEmptyCarts extends Command
{
    protected $signature = 'cart:cleanup-empty
                            {--days=7 : Delete empty carts older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up empty shopping carts older than specified days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Starting empty cart cleanup (older than {$days} days)...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $emptyCarts = Cart::whereDoesntHave('cartItems')
            ->where('updated_at', '<', now()->subDays($days));

        $cartCount = $emptyCarts->count();

        if ($cartCount === 0) {
            $this->info("No empty carts older than {$days} days found.");
            return 0;
        }

        $this->info("Found {$cartCount} empty carts older than {$days} days.");

        if ($dryRun) {
            $this->table(
                ['Cart ID', 'User ID', 'Session ID', 'Last Updated'],
                $emptyCarts->get()->map(function ($cart) {
                    return [
                        $cart->id,
                        $cart->user_id ?? 'Guest',
                        $cart->session_id ? substr($cart->session_id, 0, 8) . '...' : 'N/A',
                        $cart->updated_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );

            $this->info('Use without --dry-run to actually delete these carts.');
            return 0;
        }

        if (!$this->confirm("Are you sure you want to delete {$cartCount} empty carts?")) {
            $this->info('Cleanup cancelled.');
            return 0;
        }

        $deleted = $emptyCarts->delete();

        Log::info('Empty carts cleaned up', [
            'carts_deleted' => $deleted,
            'days_threshold' => $days,
        ]);

        $this->info("Successfully deleted {$deleted} empty carts.");

        return 0;
    }
}
