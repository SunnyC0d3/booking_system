<?php

namespace App\Resources\V1;

use App\Constants\SupplierIntegrationTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'integration_type' => $this->integration_type,
            'integration_type_label' => $this->getIntegrationTypeLabel(),
            'name' => $this->name,
            'is_active' => $this->is_active,
            'is_automated' => $this->isAutomated(),
            'is_healthy' => $this->isHealthy(),
            'configuration' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                $this->configuration
            ),
            'authentication' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                function() {
                    $auth = $this->authentication ?? [];
                    if (isset($auth['api_key'])) {
                        $auth['api_key'] = '***' . substr($auth['api_key'], -4);
                    }
                    if (isset($auth['api_secret'])) {
                        $auth['api_secret'] = '***' . substr($auth['api_secret'], -4);
                    }
                    if (isset($auth['webhook_secret'])) {
                        $auth['webhook_secret'] = '***' . substr($auth['webhook_secret'], -4);
                    }
                    if (isset($auth['ftp_password'])) {
                        $auth['ftp_password'] = '***';
                    }
                    if (isset($auth['smtp_password'])) {
                        $auth['smtp_password'] = '***';
                    }
                    return $auth;
                }
            ),
            'status' => $this->status,
            'health_score' => $this->getHealthScore(),
            'health_status' => $this->getHealthStatus(),
            'last_successful_sync' => $this->last_successful_sync,
            'last_failed_sync' => $this->last_failed_sync,
            'last_sync_status' => $this->getLastSyncStatus(),
            'last_sync_time' => $this->getLastSyncTime(),
            'last_sync_ago' => $this->getLastSyncAgo(),
            'consecutive_failures' => $this->consecutive_failures,
            'last_error' => $this->last_error,
            'sync_frequency_minutes' => $this->sync_frequency_minutes,
            'sync_frequency_formatted' => $this->getSyncFrequencyFormatted(),
            'auto_retry_enabled' => $this->auto_retry_enabled,
            'max_retry_attempts' => $this->max_retry_attempts,
            'needs_sync' => $this->needsSync(),
            'can_retry' => $this->canRetry(),
            'has_recent_sync' => $this->hasRecentSync(),
            'webhook_events' => $this->webhook_events,
            'sync_statistics' => $this->getSyncStatistics(),
            'success_rate' => $this->getSuccessRate(),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'configuration_summary' => $this->getConfigurationSummary(),
            'connection_info' => $this->getConnectionInfo(),
            'sync_capabilities' => $this->getSyncCapabilities(),
            'status_indicators' => [
                'is_active' => $this->is_active ? 'active' : 'inactive',
                'health_status' => $this->getHealthStatusIndicator(),
                'sync_status' => $this->getSyncStatusIndicator(),
                'connection_status' => $this->getConnectionStatusIndicator(),
                'error_status' => $this->getErrorStatusIndicator(),
            ],
            'metrics' => [
                'uptime_percentage' => $this->getUptimePercentage(),
                'average_sync_duration' => $this->getAverageSyncDuration(),
                'total_data_transferred' => $this->getTotalDataTransferred(),
                'error_rate' => $this->getErrorRate(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getConfigurationSummary(): array
    {
        $config = $this->configuration ?? [];

        $summary = [
            'type' => $this->integration_type,
            'endpoints_configured' => 0,
            'features' => [],
        ];

        switch ($this->integration_type) {
            case SupplierIntegrationTypes::API:
                $summary['endpoints_configured'] = count($config['endpoints'] ?? []);
                $summary['features'] = [
                    'Real-time stock' => $config['real_time_stock'] ?? false,
                    'Bulk operations' => $config['bulk_operations'] ?? false,
                    'Rate limited' => isset($config['rate_limit']),
                ];
                break;

            case SupplierIntegrationTypes::WEBHOOK:
                $summary['endpoints_configured'] = 1;
                $summary['features'] = [
                    'Event types' => count($this->webhook_events ?? []),
                    'Signature validation' => isset($config['signature_method']),
                    'Auto retry' => $this->auto_retry_enabled,
                ];
                break;

            case SupplierIntegrationTypes::EMAIL:
                $summary['endpoints_configured'] = 1;
                $summary['features'] = [
                    'Template configured' => isset($config['email_template']),
                    'Attachments' => isset($config['attachment_format']),
                    'Confirmation required' => $config['confirmation_required'] ?? false,
                ];
                break;

            case SupplierIntegrationTypes::FTP:
                $summary['endpoints_configured'] = 1;
                $summary['features'] = [
                    'Upload directory' => isset($config['upload_directory']),
                    'Download directory' => isset($config['download_directory']),
                    'File format' => $config['file_format'] ?? 'unknown',
                ];
                break;
        }

        return $summary;
    }

    protected function getConnectionInfo(): array
    {
        $config = $this->configuration ?? [];
        $auth = $this->authentication ?? [];

        switch ($this->integration_type) {
            case SupplierIntegrationTypes::API:
                return [
                    'endpoint' => $config['api_endpoint'] ?? null,
                    'timeout' => $config['timeout'] ?? null,
                    'format' => $config['format'] ?? null,
                    'authenticated' => !empty($auth['api_key']),
                ];

            case SupplierIntegrationTypes::WEBHOOK:
                return [
                    'webhook_url' => $config['webhook_url'] ?? null,
                    'events' => count($this->webhook_events ?? []),
                    'signature_method' => $config['signature_method'] ?? null,
                    'authenticated' => !empty($auth['webhook_secret']),
                ];

            case SupplierIntegrationTypes::EMAIL:
                return [
                    'email_address' => $config['email_address'] ?? null,
                    'smtp_configured' => !empty($auth['smtp_host']),
                    'template' => $config['email_template'] ?? null,
                ];

            case SupplierIntegrationTypes::FTP:
                return [
                    'ftp_host' => $config['ftp_host'] ?? null,
                    'ftp_port' => $config['ftp_port'] ?? null,
                    'connection_type' => $auth['connection_type'] ?? 'ftp',
                    'authenticated' => !empty($auth['ftp_username']),
                ];

            default:
                return [];
        }
    }

    protected function getSyncCapabilities(): array
    {
        $capabilities = [
            'automated_sync' => $this->isAutomated(),
            'manual_sync' => true,
            'real_time_updates' => false,
            'bulk_operations' => false,
            'webhook_support' => false,
        ];

        $config = $this->configuration ?? [];

        switch ($this->integration_type) {
            case SupplierIntegrationTypes::API:
                $capabilities['real_time_updates'] = $config['real_time_stock'] ?? false;
                $capabilities['bulk_operations'] = $config['bulk_operations'] ?? false;
                break;

            case SupplierIntegrationTypes::WEBHOOK:
                $capabilities['webhook_support'] = true;
                $capabilities['real_time_updates'] = in_array('stock.real_time', $this->webhook_events ?? []);
                break;
        }

        return $capabilities;
    }

    protected function getHealthStatusIndicator(): string
    {
        $score = $this->getHealthScore();

        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        if ($score >= 30) return 'poor';
        return 'critical';
    }

    protected function getSyncStatusIndicator(): string
    {
        if (!$this->last_successful_sync && !$this->last_failed_sync) {
            return 'never_synced';
        }

        if ($this->hasRecentSync(24)) {
            return 'recent';
        }

        if ($this->hasRecentSync(168)) { // 7 days
            return 'current';
        }

        return 'outdated';
    }

    protected function getConnectionStatusIndicator(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        if ($this->consecutive_failures >= 3) {
            return 'failed';
        }

        if ($this->consecutive_failures > 0) {
            return 'unstable';
        }

        return 'stable';
    }

    protected function getErrorStatusIndicator(): string
    {
        if ($this->consecutive_failures === 0) {
            return 'no_errors';
        }

        if ($this->consecutive_failures <= 2) {
            return 'minor_errors';
        }

        return 'major_errors';
    }

    protected function getUptimePercentage(): float
    {
        $stats = $this->getSyncStatistics();
        $total = $stats['total_syncs'] ?? 0;

        if ($total === 0) {
            return 100.0;
        }

        $successful = $stats['successful_syncs'] ?? 0;
        return round(($successful / $total) * 100, 2);
    }

    protected function getAverageSyncDuration(): ?int
    {
        $stats = $this->getSyncStatistics();
        return $stats['average_sync_duration'] ?? null;
    }

    protected function getTotalDataTransferred(): ?float
    {
        $stats = $this->getSyncStatistics();
        return $stats['data_transferred_mb'] ?? null;
    }

    protected function getErrorRate(): float
    {
        $stats = $this->getSyncStatistics();
        $total = $stats['total_syncs'] ?? 0;

        if ($total === 0) {
            return 0.0;
        }

        $failed = $stats['failed_syncs'] ?? 0;
        return round(($failed / $total) * 100, 2);
    }
}
