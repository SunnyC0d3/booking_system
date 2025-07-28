<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Constants\SupplierStatuses;
use App\Mail\SupplierHealthReportMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Exception;

class CheckSupplierHealth extends Command
{
    protected $signature = 'supplier:health-check
                            {--supplier= : Check specific supplier ID}
                            {--send-report : Automatically send report to admins}
                            {--alert-threshold=70 : Health score threshold for alerts}';

    protected $description = 'Check supplier integration health and performance metrics';

    public function handle(): int
    {
        $supplierId = $this->option('supplier');
        $sendReport = $this->option('send-report');
        $alertThreshold = (int) $this->option('alert-threshold');

        $suppliers = $supplierId
            ? Supplier::where('id', $supplierId)->get()
            : Supplier::where('status', SupplierStatuses::ACTIVE)->get();

        if ($suppliers->isEmpty()) {
            $this->warn('No suppliers found to check');
            return Command::SUCCESS;
        }

        $this->info("Checking health for {$suppliers->count()} supplier(s)");

        $healthReport = [];
        $unhealthySuppliers = [];

        foreach ($suppliers as $supplier) {
            $health = $this->checkSupplierHealth($supplier);
            $healthReport[] = $health;

            $this->displaySupplierHealth($health);

            if ($health['overall_score'] < $alertThreshold) {
                $unhealthySuppliers[] = $health;
            }
        }

        $this->displaySummary($healthReport, $alertThreshold);

        if ($sendReport || !empty($unhealthySuppliers)) {
            $this->sendHealthReport($healthReport, $unhealthySuppliers);
        }

        return Command::SUCCESS;
    }

    private function checkSupplierHealth(Supplier $supplier): array
    {
        $integration = $supplier->getActiveIntegration();

        // Calculate various health metrics
        $orderStats = $this->getOrderStats($supplier);
        $integrationHealth = $this->getIntegrationHealth($integration);
        $responseTime = $this->getAverageResponseTime($supplier);
        $errorRate = $this->getErrorRate($supplier);

        // Calculate overall health score (0-100)
        $overallScore = $this->calculateOverallScore([
            'order_success_rate' => $orderStats['success_rate'],
            'integration_uptime' => $integrationHealth['uptime'],
            'response_time_score' => $responseTime['score'],
            'error_rate_score' => 100 - ($errorRate * 10) // Convert error rate to score
        ]);

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'overall_score' => $overallScore,
            'health_status' => $this->getHealthStatus($overallScore),
            'order_stats' => $orderStats,
            'integration_health' => $integrationHealth,
            'response_time' => $responseTime,
            'error_rate' => $errorRate,
            'last_check' => now(),
            'recommendations' => $this->getRecommendations($overallScore, $orderStats, $integrationHealth)
        ];
    }

    private function getOrderStats(Supplier $supplier): array
    {
        $totalOrders = $supplier->dropshipOrders()->count();
        $successfulOrders = $supplier->dropshipOrders()
            ->whereIn('status', ['delivered', 'shipped_by_supplier'])
            ->count();

        return [
            'total_orders' => $totalOrders,
            'successful_orders' => $successfulOrders,
            'success_rate' => $totalOrders > 0 ? round(($successfulOrders / $totalOrders) * 100, 2) : 0,
            'avg_fulfillment_time' => $supplier->getAverageFulfillmentTime()
        ];
    }

    private function getIntegrationHealth(SupplierIntegration $integration = null): array
    {
        if (!$integration) {
            return [
                'status' => 'no_integration',
                'uptime' => 0,
                'last_successful_sync' => null,
                'failed_attempts' => 0
            ];
        }

        $recentAttempts = $integration->sync_attempts ?? 0;
        $recentFailures = $integration->failed_attempts ?? 0;
        $uptime = $recentAttempts > 0 ? ((($recentAttempts - $recentFailures) / $recentAttempts) * 100) : 100;

        return [
            'status' => $integration->is_active ? 'active' : 'inactive',
            'uptime' => round($uptime, 2),
            'last_successful_sync' => $integration->last_successful_sync_at,
            'failed_attempts' => $recentFailures
        ];
    }

    private function getAverageResponseTime(Supplier $supplier): array
    {
        // Mock implementation - in real app, you'd track actual response times
        $avgTime = rand(500, 5000); // milliseconds

        $score = match(true) {
            $avgTime < 1000 => 100,
            $avgTime < 2000 => 80,
            $avgTime < 3000 => 60,
            $avgTime < 5000 => 40,
            default => 20
        };

        return [
            'avg_response_time_ms' => $avgTime,
            'score' => $score,
            'status' => $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : 'poor')
        ];
    }

    private function getErrorRate(Supplier $supplier): float
    {
        $totalOrders = $supplier->dropshipOrders()->count();
        $failedOrders = $supplier->dropshipOrders()
            ->where('status', 'failed')
            ->count();

        return $totalOrders > 0 ? round(($failedOrders / $totalOrders) * 100, 2) : 0;
    }

    private function calculateOverallScore(array $metrics): int
    {
        $weights = [
            'order_success_rate' => 0.4,
            'integration_uptime' => 0.3,
            'response_time_score' => 0.2,
            'error_rate_score' => 0.1
        ];

        $weightedScore = 0;
        foreach ($metrics as $metric => $value) {
            $weightedScore += ($value * $weights[$metric]);
        }

        return min(100, max(0, round($weightedScore)));
    }

    private function getHealthStatus(int $score): string
    {
        return match(true) {
            $score >= 90 => 'excellent',
            $score >= 80 => 'good',
            $score >= 70 => 'fair',
            $score >= 60 => 'poor',
            default => 'critical'
        };
    }

    private function getRecommendations(int $score, array $orderStats, array $integrationHealth): array
    {
        $recommendations = [];

        if ($score < 70) {
            $recommendations[] = 'Overall supplier health needs immediate attention';
        }

        if ($orderStats['success_rate'] < 80) {
            $recommendations[] = 'Order success rate is below acceptable threshold';
        }

        if ($integrationHealth['uptime'] < 95) {
            $recommendations[] = 'Integration reliability needs improvement';
        }

        if ($integrationHealth['failed_attempts'] > 5) {
            $recommendations[] = 'Consider reviewing integration configuration';
        }

        return $recommendations;
    }

    private function displaySupplierHealth(array $health): void
    {
        $status = $health['health_status'];
        $color = match($status) {
            'excellent' => 'info',
            'good' => 'info',
            'fair' => 'comment',
            'poor' => 'warn',
            'critical' => 'error'
        };

        $this->line('');
        $this->{$color}("🏪 {$health['supplier_name']} - Score: {$health['overall_score']}/100 ({$status})");
        $this->line("   Orders: {$health['order_stats']['total_orders']} total, {$health['order_stats']['success_rate']}% success");
        $this->line("   Integration: {$health['integration_health']['uptime']}% uptime");
        $this->line("   Error Rate: {$health['error_rate']}%");

        if (!empty($health['recommendations'])) {
            $this->line("   Recommendations:");
            foreach ($health['recommendations'] as $rec) {
                $this->line("   - {$rec}");
            }
        }
    }

    private function displaySummary(array $healthReport, int $threshold): void
    {
        $totalSuppliers = count($healthReport);
        $healthySuppliers = collect($healthReport)->where('overall_score', '>=', $threshold)->count();
        $unhealthySuppliers = $totalSuppliers - $healthySuppliers;
        $avgScore = collect($healthReport)->avg('overall_score');

        $this->line('');
        $this->info('=== Health Check Summary ===');
        $this->line("Total suppliers checked: {$totalSuppliers}");
        $this->line("Healthy suppliers (>= {$threshold}): {$healthySuppliers}");

        if ($unhealthySuppliers > 0) {
            $this->warn("Unhealthy suppliers: {$unhealthySuppliers}");
        } else {
            $this->info("Unhealthy suppliers: {$unhealthySuppliers}");
        }

        $this->line("Average health score: " . round($avgScore, 1));
    }

    private function sendHealthReport(array $healthReport, array $unhealthySuppliers): void
    {
        try {
            $adminEmails = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['super admin', 'admin']);
            })->pluck('email')->toArray();

            $emailData = [
                'health_report' => $healthReport,
                'unhealthy_suppliers' => $unhealthySuppliers,
                'summary' => [
                    'total_suppliers' => count($healthReport),
                    'unhealthy_count' => count($unhealthySuppliers),
                    'avg_score' => round(collect($healthReport)->avg('overall_score'), 1),
                    'generated_at' => now()->format('M j, Y g:i A')
                ]
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new SupplierHealthReportMail($emailData));
            }

            $this->info('Health report sent to administrators');

        } catch (Exception $e) {
            $this->error("Failed to send health report: {$e->getMessage()}");
        }
    }
}
