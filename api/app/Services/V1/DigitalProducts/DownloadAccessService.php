<?php

namespace App\Services\V1\DigitalProducts;

use App\Models\DownloadAccess;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\ProductFile;
use App\Models\DownloadAttempt;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class DownloadAccessService
{
    public function createAccess(User $user, Product $product, Order $order, ?ProductFile $file = null): DownloadAccess
    {
        if (!$product->isDigital()) {
            throw new Exception('Cannot create download access for non-digital products');
        }

        $downloadAccess = DownloadAccess::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'product_file_id' => $file?->id,
            'access_token' => DownloadAccess::generateToken(),
            'download_limit' => $product->download_limit,
            'expires_at' => now()->addDays($product->download_expiry_days),
            'status' => 'active',
            'metadata' => [
                'created_by_purchase' => true,
                'original_ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ],
        ]);

        Log::info('Download access created', [
            'access_id' => $downloadAccess->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'expires_at' => $downloadAccess->expires_at
        ]);

        return $downloadAccess;
    }

    public function validateAccess(string $accessToken, Request $request): DownloadAccess
    {
        $downloadAccess = DownloadAccess::where('access_token', $accessToken)
            ->with(['user', 'product', 'productFile'])
            ->first();

        if (!$downloadAccess) {
            throw new Exception('Invalid download token', 404);
        }

        if (!$downloadAccess->isValid()) {
            $reason = $this->getInvalidReason($downloadAccess);
            throw new Exception("Download access invalid: {$reason}", 403);
        }

        if (!$downloadAccess->canDownloadFromIp($request->ip())) {
            throw new Exception('Download not allowed from this IP address', 403);
        }

        return $downloadAccess;
    }

    public function recordDownloadAttempt(DownloadAccess $downloadAccess, ProductFile $productFile, Request $request): DownloadAttempt
    {
        $downloadAttempt = DownloadAttempt::create([
            'download_access_id' => $downloadAccess->id,
            'user_id' => $downloadAccess->user_id,
            'product_file_id' => $productFile->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'started',
            'bytes_downloaded' => 0,
            'total_file_size' => $productFile->file_size,
            'started_at' => now(),
            'headers' => [
                'Accept' => $request->header('Accept'),
                'Accept-Encoding' => $request->header('Accept-Encoding'),
                'Range' => $request->header('Range'),
                'User-Agent' => $request->userAgent(),
            ],
        ]);

        Log::info('Download attempt started', [
            'attempt_id' => $downloadAttempt->id,
            'access_id' => $downloadAccess->id,
            'file_id' => $productFile->id,
            'ip' => $request->ip()
        ]);

        return $downloadAttempt;
    }

    public function markDownloadComplete(DownloadAccess $downloadAccess, DownloadAttempt $downloadAttempt): void
    {
        $downloadAttempt->markAsCompleted();
        $downloadAccess->recordDownload();

        Log::info('Download completed successfully', [
            'attempt_id' => $downloadAttempt->id,
            'access_id' => $downloadAccess->id,
            'remaining_downloads' => $downloadAccess->remaining_downloads
        ]);
    }

    public function markDownloadFailed(DownloadAttempt $downloadAttempt, string $reason): void
    {
        $downloadAttempt->markAsFailed($reason);

        Log::warning('Download failed', [
            'attempt_id' => $downloadAttempt->id,
            'reason' => $reason,
            'bytes_downloaded' => $downloadAttempt->bytes_downloaded
        ]);
    }

    public function revokeAccess(DownloadAccess $downloadAccess, string $reason = null): void
    {
        $downloadAccess->update([
            'status' => 'revoked',
            'metadata' => array_merge($downloadAccess->metadata ?? [], [
                'revoked_at' => now()->toISOString(),
                'revoked_reason' => $reason ?? 'Access revoked by administrator',
            ]),
        ]);

        Log::info('Download access revoked', [
            'access_id' => $downloadAccess->id,
            'reason' => $reason
        ]);
    }

    public function extendAccess(DownloadAccess $downloadAccess, int $additionalDays): void
    {
        $newExpiryDate = $downloadAccess->expires_at->addDays($additionalDays);

        $downloadAccess->update([
            'expires_at' => $newExpiryDate,
            'metadata' => array_merge($downloadAccess->metadata ?? [], [
                'extended_at' => now()->toISOString(),
                'extended_days' => $additionalDays,
            ]),
        ]);

        Log::info('Download access extended', [
            'access_id' => $downloadAccess->id,
            'additional_days' => $additionalDays,
            'new_expiry' => $newExpiryDate
        ]);
    }

    public function increaseDownloadLimit(DownloadAccess $downloadAccess, int $additionalDownloads): void
    {
        $downloadAccess->update([
            'download_limit' => $downloadAccess->download_limit + $additionalDownloads,
            'metadata' => array_merge($downloadAccess->metadata ?? [], [
                'limit_increased_at' => now()->toISOString(),
                'additional_downloads' => $additionalDownloads,
            ]),
        ]);

        Log::info('Download limit increased', [
            'access_id' => $downloadAccess->id,
            'additional_downloads' => $additionalDownloads,
            'new_limit' => $downloadAccess->download_limit
        ]);
    }

    public function getAccessAnalytics(DownloadAccess $downloadAccess): array
    {
        $attempts = $downloadAccess->downloadAttempts;

        $totalAttempts = $attempts->count();
        $completedAttempts = $attempts->where('status', 'completed')->count();
        $failedAttempts = $attempts->where('status', 'failed')->count();
        $totalBytesDownloaded = $attempts->where('status', 'completed')->sum('bytes_downloaded');

        $successRate = $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 2) : 0;
        $averageSpeed = $attempts->where('status', 'completed')->avg('download_speed_kbps');
        $averageDuration = $attempts->where('status', 'completed')->avg('duration_seconds');

        return [
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $completedAttempts,
            'failed_attempts' => $failedAttempts,
            'success_rate' => $successRate,
            'total_bytes_downloaded' => $totalBytesDownloaded,
            'total_mb_downloaded' => round($totalBytesDownloaded / 1024 / 1024, 2),
            'average_speed_kbps' => $averageSpeed ? round($averageSpeed, 2) : null,
            'average_duration_seconds' => $averageDuration ? round($averageDuration, 2) : null,
            'unique_ips' => $attempts->pluck('ip_address')->unique()->count(),
            'first_download' => $downloadAccess->first_downloaded_at,
            'last_download' => $downloadAccess->last_downloaded_at,
            'downloads_remaining' => $downloadAccess->remaining_downloads,
            'expires_at' => $downloadAccess->expires_at,
            'is_expired' => $downloadAccess->isExpired(),
        ];
    }

    protected function getInvalidReason(DownloadAccess $downloadAccess): string
    {
        if ($downloadAccess->status !== 'active') {
            return "Status is {$downloadAccess->status}";
        }

        if ($downloadAccess->isExpired()) {
            return 'Access token has expired';
        }

        if (!$downloadAccess->hasDownloadsRemaining()) {
            return 'Download limit exceeded';
        }

        return 'Unknown reason';
    }

    public function getDownloadHistory(User $user, array $filters = []): array
    {
        $query = DownloadAttempt::where('user_id', $user->id)
            ->with(['downloadAccess.product', 'productFile'])
            ->orderBy('started_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_id'])) {
            $query->whereHas('downloadAccess', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        if (isset($filters['date_from'])) {
            $query->where('started_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('started_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 20;
        $attempts = $query->paginate($perPage);

        return [
            'attempts' => $attempts->items(),
            'pagination' => [
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'per_page' => $attempts->perPage(),
                'total' => $attempts->total(),
            ]
        ];
    }
}
