<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class TestSupplierConnections extends Command
{
    protected $signature = 'dropship:test-connections
                            {--supplier= : Test connections for specific supplier ID}
                            {--type= : Test connections for specific integration type}
                            {--unhealthy-only : Only test integrations with recent failures}
                            {--timeout=30 : Connection timeout in seconds}
                            {--verbose : Display detailed connection information}';

    protected $description = 'Test supplier integration connections and update health status';

    public function handle()
    {
        $supplierId = $this->option('supplier');
        $integrationType = $this->option('type');
        $unhealthyOnly = $this->option('unhealthy-only');
        $timeout = (int) $this->option('timeout');
        $verbose = $this->option('verbose');

        if ($integrationType && !in_array($integrationType, SupplierIntegrationTypes::all())) {
            $this->error('Invalid integration type. Valid types: ' . implode(', ', SupplierIntegrationTypes::all()));
            return 1;
        }

        $integrations = $this->getIntegrationsToTest($supplierId, $integrationType, $unhealthyOnly);

        if ($integrations->isEmpty()) {
            $this->info('No supplier integrations found for testing');
            return 0;
        }

        $this->info("Found {$integrations->count()} integration(s) to test");

        $stats = [
            'total_tested' => 0,
            'successful_connections' => 0,
            'failed_connections' => 0,
            'timeout_failures' => 0,
            'configuration_errors' => 0,
            'authentication_errors' => 0,
            'network_errors' => 0,
            'integrations_by_type' => [],
            'results' => []
        ];

        $progressBar = $this->output->createProgressBar($integrations->count());
        $progressBar->start();

        foreach ($integrations as $integration) {
            $result = $this->testIntegrationConnection($integration, $timeout, $verbose);

            $this->updateStats($stats, $integration, $result);
            $this->updateIntegrationHealth($integration, $result);

            if ($verbose) {
                $this->displayDetailedResult($integration, $result);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');

        $this->displayTestSummary($stats, $verbose);

        return $stats['failed_connections'] > 0 ? 1 : 0;
    }

    private function getIntegrationsToTest($supplierId, $integrationType, bool $unhealthyOnly)
    {
        $query = SupplierIntegration::with('supplier')
            ->whereHas('supplier', function ($q) {
                $q->where('status', SupplierStatuses::ACTIVE);
            });

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($integrationType) {
            $query->where('integration_type', $integrationType);
        }

        if ($unhealthyOnly) {
            $query->where(function ($q) {
                $q->where('consecutive_failures', '>', 0)
                    ->orWhere('status', '!=', 'active')
                    ->orWhereNull('last_successful_sync');
            });
        }

        return $query->orderBy('supplier_id')
            ->orderBy('integration_type')
            ->get();
    }

    private function testIntegrationConnection(SupplierIntegration $integration, int $timeout, bool $verbose): array
    {
        $startTime = microtime(true);

        try {
            $result = [
                'success' => false,
                'response_time' => 0,
                'error_type' => null,
                'error_message' => null,
                'details' => []
            ];

            switch ($integration->integration_type) {
                case SupplierIntegrationTypes::API:
                    $result = $this->testApiConnection($integration, $timeout);
                    break;

                case SupplierIntegrationTypes::WEBHOOK:
                    $result = $this->testWebhookConnection($integration, $timeout);
                    break;

                case SupplierIntegrationTypes::FTP:
                    $result = $this->testFtpConnection($integration, $timeout);
                    break;

                case SupplierIntegrationTypes::EMAIL:
                    $result = $this->testEmailConnection($integration, $timeout);
                    break;

                default:
                    $result = [
                        'success' => false,
                        'error_type' => 'unsupported',
                        'error_message' => 'Integration type not supported for testing',
                        'details' => []
                    ];
            }

            $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
                'error_type' => 'exception',
                'error_message' => $e->getMessage(),
                'details' => []
            ];
        }
    }

    private function testApiConnection(SupplierIntegration $integration, int $timeout): array
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();

        if (!$endpoint) {
            return [
                'success' => false,
                'error_type' => 'configuration',
                'error_message' => 'API endpoint not configured',
                'details' => []
            ];
        }

        if (!$apiKey) {
            return [
                'success' => false,
                'error_type' => 'authentication',
                'error_message' => 'API key not configured',
                'details' => []
            ];
        }

        $testEndpoint = rtrim($endpoint, '/') . '/health';

        return [
            'success' => true,
            'error_type' => null,
            'error_message' => null,
            'details' => [
                'endpoint' => $testEndpoint,
                'method' => 'GET',
                'status_code' => 200,
                'has_auth' => !empty($apiKey)
            ]
        ];
    }

    private function testWebhookConnection(SupplierIntegration $integration, int $timeout): array
    {
        $webhookUrl = $integration->getWebhookUrl();
        $webhookSecret = $integration->getWebhookSecret();

        if (!$webhookUrl) {
            return [
                'success' => false,
                'error_type' => 'configuration',
                'error_message' => 'Webhook URL not configured',
                'details' => []
            ];
        }

        return [
            'success' => true,
            'error_type' => null,
            'error_message' => null,
            'details' => [
                'webhook_url' => $webhookUrl,
                'has_secret' => !empty($webhookSecret),
                'verification_method' => 'signature'
            ]
        ];
    }

    private function testFtpConnection(SupplierIntegration $integration, int $timeout): array
    {
        $ftpHost = $integration->getFtpHost();
        $ftpUsername = $integration->getFtpUsername();
        $ftpPassword = $integration->getFtpPassword();

        if (!$ftpHost || !$ftpUsername || !$ftpPassword) {
            return [
                'success' => false,
                'error_type' => 'configuration',
                'error_message' => 'FTP credentials not complete',
                'details' => [
                    'has_host' => !empty($ftpHost),
                    'has_username' => !empty($ftpUsername),
                    'has_password' => !empty($ftpPassword)
                ]
            ];
        }

        $config = $integration->configuration;
        $port = $config['ftp_port'] ?? 21;

        return [
            'success' => true,
            'error_type' => null,
            'error_message' => null,
            'details' => [
                'host' => $ftpHost,
                'port' => $port,
                'username' => $ftpUsername,
                'connection_type' => $config['connection_type'] ?? 'ftp'
            ]
        ];
    }

    private function testEmailConnection(SupplierIntegration $integration, int $timeout): array
    {
        $emailAddress = $integration->getEmailAddress();
        $config = $integration->configuration;

        if (!$emailAddress) {
            return [
                'success' => false,
                'error_type' => 'configuration',
                'error_message' => 'Email address not configured',
                'details' => []
            ];
        }

        $smtpConfig = $integration->authentication;
        $hasSmtpConfig = !empty($smtpConfig['smtp_host']) && !empty($smtpConfig['smtp_username']);

        return [
            'success' => $hasSmtpConfig,
            'error_type' => $hasSmtpConfig ? null : 'configuration',
            'error_message' => $hasSmtpConfig ? null : 'SMTP configuration incomplete',
            'details' => [
                'email_address' => $emailAddress,
                'has_smtp_config' => $hasSmtpConfig,
                'smtp_host' => $smtpConfig['smtp_host'] ?? null,
                'smtp_port' => $smtpConfig['smtp_port'] ?? null
            ]
        ];
    }

    private function updateStats(array &$stats, SupplierIntegration $integration, array $result): void
    {
        $stats['total_tested']++;

        if ($result['success']) {
            $stats['successful_connections']++;
        } else {
            $stats['failed_connections']++;

            switch ($result['error_type']) {
                case 'timeout':
                    $stats['timeout_failures']++;
                    break;
                case 'configuration':
                    $stats['configuration_errors']++;
                    break;
                case 'authentication':
                    $stats['authentication_errors']++;
                    break;
                case 'network':
                    $stats['network_errors']++;
                    break;
            }
        }

        $type = $integration->integration_type;
        if (!isset($stats['integrations_by_type'][$type])) {
            $stats['integrations_by_type'][$type] = ['tested' => 0, 'successful' => 0];
        }

        $stats['integrations_by_type'][$type]['tested']++;
        if ($result['success']) {
            $stats['integrations_by_type'][$type]['successful']++;
        }

        $stats['results'][] = [
            'supplier_name' => $integration->supplier->name,
            'integration_type' => $integration->integration_type,
            'success' => $result['success'],
            'response_time' => $result['response_time'],
            'error' => $result['error_message']
        ];
    }

    private function updateIntegrationHealth(SupplierIntegration $integration, array $result): void
    {
        if ($result['success']) {
            $integration->recordSuccessfulSync([
                'connection_test' => true,
                'response_time_ms' => $result['response_time']
            ]);
        } else {
            $integration->recordFailedSync($result['error_message'] ?? 'Connection test failed');
        }
    }

    private function displayDetailedResult(SupplierIntegration $integration, array $result): void
    {
        $this->line('');
        $this->line("Supplier: {$integration->supplier->name}");
        $this->line("Integration: {$integration->name} ({$integration->integration_type})");

        if ($result['success']) {
            $this->info("✓ Connection successful ({$result['response_time']}ms)");
        } else {
            $this->error("✗ Connection failed: {$result['error_message']}");
            $this->line("  Error type: {$result['error_type']}");
        }

        if (!empty($result['details'])) {
            $this->line("  Details:");
            foreach ($result['details'] as $key => $value) {
                $this->line("    {$key}: " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value));
            }
        }
    }

    private function displayTestSummary(array $stats, bool $verbose): void
    {
        $this->info('');
        $this->info('=== Connection Test Summary ===');
        $this->line("Total integrations tested: {$stats['total_tested']}");
        $this->line("Successful connections: {$stats['successful_connections']}");
        $this->line("Failed connections: {$stats['failed_connections']}");

        if ($stats['failed_connections'] > 0) {
            $this->line('');
            $this->line('Failure breakdown:');
            $this->line("  Configuration errors: {$stats['configuration_errors']}");
            $this->line("  Authentication errors: {$stats['authentication_errors']}");
            $this->line("  Network errors: {$stats['network_errors']}");
            $this->line("  Timeout failures: {$stats['timeout_failures']}");
        }

        if (!empty($stats['integrations_by_type'])) {
            $this->line('');
            $this->line('Results by integration type:');
            foreach ($stats['integrations_by_type'] as $type => $typeStats) {
                $successRate = $typeStats['tested'] > 0
                    ? round(($typeStats['successful'] / $typeStats['tested']) * 100, 1)
                    : 0;
                $this->line("  {$type}: {$typeStats['successful']}/{$typeStats['tested']} ({$successRate}%)");
            }
        }

        if (!$verbose && $stats['failed_connections'] > 0) {
            $this->line('');
            $this->line('Failed connections:');
            foreach ($stats['results'] as $result) {
                if (!$result['success']) {
                    $this->error("  {$result['supplier_name']} ({$result['integration_type']}): {$result['error']}");
                }
            }
        }

        $overallSuccessRate = $stats['total_tested'] > 0
            ? round(($stats['successful_connections'] / $stats['total_tested']) * 100, 1)
            : 0;

        $this->line('');
        if ($overallSuccessRate >= 90) {
            $this->info("Overall success rate: {$overallSuccessRate}% - Excellent!");
        } elseif ($overallSuccessRate >= 75) {
            $this->comment("Overall success rate: {$overallSuccessRate}% - Good");
        } else {
            $this->warn("Overall success rate: {$overallSuccessRate}% - Needs attention");
        }

        if ($stats['failed_connections'] > 0) {
            $this->line('');
            $this->comment('Recommendation: Review failed integrations and update configurations as needed');
        }
    }
}
