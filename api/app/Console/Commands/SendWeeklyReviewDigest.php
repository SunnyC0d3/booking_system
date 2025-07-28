<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vendor;
use App\Services\V1\Reviews\ReviewNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyReviewDigest extends Command
{
    protected $signature = 'reviews:send-digest
                            {--vendor= : Send digest to specific vendor ID}
                            {--force : Send digest even if no new reviews}';

    protected $description = 'Send weekly review digest to vendors';

    protected ReviewNotificationService $notificationService;

    public function __construct(ReviewNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle(): int
    {
        $vendorId = $this->option('vendor');
        $force = $this->option('force');

        if ($vendorId) {
            $vendor = Vendor::find($vendorId);
            if (!$vendor) {
                $this->error("Vendor with ID {$vendorId} not found");
                return Command::FAILURE;
            }
            $vendorUsers = collect([$vendor->user]);
        } else {
            $vendorUsers = User::whereHas('roles', function($query) {
                $query->where('name', 'vendor');
            })->whereHas('vendors')->get();
        }

        if ($vendorUsers->isEmpty()) {
            $this->warn('No vendor users found');
            return Command::SUCCESS;
        }

        $this->info("Sending weekly review digest to {$vendorUsers->count()} vendor(s)");

        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($vendorUsers as $vendorUser) {
            try {
                // Check if vendor has reviews to digest
                if (!$force && !$this->hasReviewsToDigest($vendorUser)) {
                    $this->line("Skipping {$vendorUser->name} - no new reviews");
                    $skipped++;
                    continue;
                }

                $this->notificationService->sendWeeklyReviewDigest($vendorUser);
                $sent++;
                $this->line("✓ Sent digest to {$vendorUser->name}");

            } catch (\Exception $e) {
                $this->error("Failed to send digest to {$vendorUser->name}: {$e->getMessage()}");
                $errors++;

                Log::error('Failed to send weekly review digest', [
                    'vendor_user_id' => $vendorUser->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->displaySummary($sent, $skipped, $errors);

        return Command::SUCCESS;
    }

    private function hasReviewsToDigest(User $vendorUser): bool
    {
        $vendor = $vendorUser->vendors()->first();
        if (!$vendor) {
            return false;
        }

        $weekAgo = now()->subWeek();
        return $vendor->products()
            ->whereHas('reviews', function($query) use ($weekAgo) {
                $query->where('created_at', '>=', $weekAgo)
                    ->where('is_approved', true);
            })
            ->exists();
    }

    private function displaySummary(int $sent, int $skipped, int $errors): void
    {
        $this->line('');
        $this->info('=== Weekly Digest Summary ===');
        $this->line("Digests sent: {$sent}");
        $this->line("Vendors skipped: {$skipped}");

        if ($errors > 0) {
            $this->error("Errors encountered: {$errors}");
        } else {
            $this->line("Errors encountered: {$errors}");
        }

        Log::info('Weekly review digest completed', [
            'sent' => $sent,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }
}
