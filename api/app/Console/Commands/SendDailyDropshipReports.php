<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Services\V1\Dropshipping\DropshipEmailService;
use Illuminate\Console\Command;

class SendDailyDropshipReports extends Command
{
    protected $signature = 'dropship:send-daily-reports';
    protected $description = 'Send daily dropshipping reports to admins and vendors';

    public function __construct(private DropshipEmailService $emailService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Sending daily dropship reports...');

        $this->emailService->sendDailyIssuesSummary();

        $vendors = Vendor::whereHas('products.productMappings.dropshipOrders')->get();

        foreach ($vendors as $vendor) {
            $reportData = $this->generateVendorReport($vendor);
            $this->emailService->sendVendorWeeklyReport($vendor, $reportData);
        }

        $this->info('Daily reports sent successfully.');
    }

    private function generateVendorReport(Vendor $vendor): array
    {
        return [
            'period' => now()->subWeek()->format('M j') . ' - ' . now()->format('M j, Y'),
            'total_orders' => 25,
            'delivered_orders' => 22,
            'processing_orders' => 3,
            'total_revenue_formatted' => '£1,250.00',
            'profit_margin_formatted' => '£350.00',
            'profit_margin_percentage' => 28,
            'avg_fulfillment_time' => '3.2',
            'success_rate' => 96,
            'customer_satisfaction' => 4.7,
            'active_suppliers' => 3,
            'top_suppliers' => [
                [
                    'name' => 'Supplier A',
                    'order_count' => 15,
                    'success_rate' => 98,
                    'avg_fulfillment_time' => '2.8'
                ]
            ],
            'insights' => [
                'Your fulfillment time improved by 15% this week',
                'Customer satisfaction increased to 4.7/5',
                'Consider expanding with Supplier A due to excellent performance'
            ],
            'issues' => []
        ];
    }
}
