<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Mail\OrderCancelledMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutoCancelAbandonedOrders extends Command
{
    protected $signature = 'orders:auto-cancel-abandoned
                            {--hours=24 : Cancel orders older than this many hours}
                            {--limit=100 : Maximum number of orders to process}
                            {--dry-run : Show what would be cancelled without taking action}';

    protected $description = 'Automatically cancel orders that have been abandoned for too long';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no orders will be cancelled');
        }

        $cutoffTime = now()->subHours($hours);

        $abandonedOrders = Order::whereHas('status', function($query) {
            $query->whereIn('name', ['pending_payment', 'cart_abandoned']);
        })
            ->where('created_at', '<', $cutoffTime)
            ->whereDoesntHave('payments', function($query) {
                $query->whereIn('status', ['paid', 'pending']);
            })
            ->with(['user', 'status'])
            ->limit($limit)
            ->get();

        if ($abandonedOrders->isEmpty()) {
            $this->info('No abandoned orders found to cancel');
            return Command::SUCCESS;
        }

        $this->info("Found {$abandonedOrders->count()} abandoned orders to cancel");

        $cancelled = 0;
        $errors = 0;

        foreach ($abandonedOrders as $order) {
            try {
                if (!$dryRun) {
                    $this->cancelOrder($order);
                    $cancelled++;
                    $this->line("✓ Cancelled order #{$order->id}");
                } else {
                    $this->line("Would cancel order #{$order->id} (created {$order->created_at->diffForHumans()})");
                    $cancelled++;
                }

            } catch (\Exception $e) {
                $this->error("Failed to cancel order #{$order->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->displaySummary($cancelled, $errors, $dryRun);

        return Command::SUCCESS;
    }

    private function cancelOrder(Order $order): void
    {
        // Update order status to cancelled
        $cancelledStatus = OrderStatus::where('name', 'cancelled')->first();
        $order->update([
            'status_id' => $cancelledStatus->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Auto-cancelled due to abandonment'
        ]);

        // Send cancellation email to customer if they have an email
        if ($order->user && $order->user->email) {
            try {
                Mail::to($order->user->email)->send(new OrderCancelledMail([
                    'order' => $order,
                    'reason' => 'abandoned',
                    'auto_cancelled' => true
                ]));
            } catch (\Exception $e) {
                Log::warning('Failed to send order cancellation email', [
                    'order_id' => $order->id,
                    'user_email' => $order->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Order auto-cancelled due to abandonment', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'hours_since_creation' => $order->created_at->diffInHours(now())
        ]);
    }

    private function displaySummary(int $cancelled, int $errors, bool $dryRun): void
    {
        $this->line('');
        $this->info('=== Auto-Cancellation Summary ===');

        if ($dryRun) {
            $this->line("Orders that would be cancelled: {$cancelled}");
        } else {
            $this->line("Orders cancelled: {$cancelled}");
        }

        if ($errors > 0) {
            $this->error("Errors encountered: {$errors}");
        } else {
            $this->line("Errors encountered: {$errors}");
        }

        if (!$dryRun && $cancelled > 0) {
            Log::info('Auto-cancellation of abandoned orders completed', [
                'cancelled' => $cancelled,
                'errors' => $errors
            ]);
        }
    }
}
