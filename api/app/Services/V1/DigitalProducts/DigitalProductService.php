<?php

namespace App\Services\V1\DigitalProducts;

use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\DownloadAccess;
use App\Models\LicenseKey;
use App\Models\ProductFile;
use App\Services\V1\Emails\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DigitalProductDeliveredMail;
use Exception;

class DigitalProductService
{
    protected DownloadAccessService $downloadAccessService;
    protected LicenseKeyService $licenseKeyService;
    protected Email $emailService;

    public function __construct(
        DownloadAccessService $downloadAccessService,
        LicenseKeyService $licenseKeyService,
        Email $emailService
    ) {
        $this->downloadAccessService = $downloadAccessService;
        $this->licenseKeyService = $licenseKeyService;
        $this->emailService = $emailService;
    }

    public function processDigitalPurchase(Order $order): array
    {
        $results = [];
        $errors = [];

        DB::transaction(function () use ($order, &$results, &$errors) {
            foreach ($order->orderItems as $orderItem) {
                $product = $orderItem->product;

                if (!$product->isDigital()) {
                    continue;
                }

                try {
                    $result = $this->processDigitalProduct($product, $order, $orderItem->quantity);
                    $results[] = $result;

                    if ($product->hasAutoDelivery()) {
                        $this->deliverDigitalProduct($product, $order, $result);
                    }

                } catch (Exception $e) {
                    $errors[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'error' => $e->getMessage()
                    ];

                    Log::error('Failed to process digital product purchase', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });

        Log::info('Digital purchase processing completed', [
            'order_id' => $order->id,
            'successful_products' => count($results),
            'failed_products' => count($errors)
        ]);

        return [
            'successful' => $results,
            'failed' => $errors,
            'success_count' => count($results),
            'error_count' => count($errors)
        ];
    }

    public function processDigitalProduct(Product $product, Order $order, int $quantity = 1): array
    {
        $downloadAccesses = [];
        $licenseKeys = [];

        for ($i = 0; $i < $quantity; $i++) {
            $downloadAccess = $this->downloadAccessService->createAccess(
                $order->user,
                $product,
                $order
            );
            $downloadAccesses[] = $downloadAccess;

            if ($product->requiresLicense()) {
                $licenseKey = $this->licenseKeyService->generateLicense(
                    $product,
                    $order->user,
                    $order
                );
                $licenseKeys[] = $licenseKey;
            }
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'download_accesses' => $downloadAccesses,
            'license_keys' => $licenseKeys,
            'requires_license' => $product->requiresLicense(),
            'auto_delivery' => $product->hasAutoDelivery()
        ];
    }

    public function deliverDigitalProduct(Product $product, Order $order, array $productData): void
    {
        try {
            $emailData = [
                'user' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'order' => [
                    'id' => $order->id,
                    'total_formatted' => $order->getTotalFormattedAttribute(),
                    'created_at' => $order->created_at->format('M j, Y g:i A'),
                ],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'type' => $product->getProductTypeLabel(),
                    'requires_license' => $product->requiresLicense(),
                ],
                'download_accesses' => collect($productData['download_accesses'])->map(function ($access) {
                    return [
                        'access_token' => $access->access_token,
                        'download_limit' => $access->download_limit,
                        'expires_at' => $access->expires_at->format('M j, Y g:i A'),
                        'download_url' => route('digital.download', $access->access_token),
                    ];
                }),
                'license_keys' => collect($productData['license_keys'])->map(function ($license) {
                    return [
                        'license_key' => $license->license_key,
                        'type' => $license->getTypeLabelAttribute(),
                        'activation_limit' => $license->activation_limit,
                        'expires_at' => $license->expires_at?->format('M j, Y g:i A'),
                    ];
                }),
                'files' => $product->activeProductFiles->map(function ($file) {
                    return [
                        'name' => $file->name,
                        'size' => $file->file_size_formatted,
                        'version' => $file->version,
                        'description' => $file->description,
                        'is_primary' => $file->is_primary,
                    ];
                }),
            ];

            Mail::to($order->user->email)->send(new DigitalProductDeliveredMail($emailData));

            Log::info('Digital product delivered successfully', [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'user_id' => $order->user->id,
                'download_accesses_count' => count($productData['download_accesses']),
                'license_keys_count' => count($productData['license_keys'])
            ]);

        } catch (Exception $e) {
            Log::error('Failed to deliver digital product', [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to deliver digital product: ' . $e->getMessage());
        }
    }

    public function getUserDigitalProducts(User $user, array $filters = []): array
    {
        $query = DownloadAccess::where('user_id', $user->id)
            ->with(['product', 'productFile', 'order'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_type'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('product_type', $filters['product_type']);
            });
        }

        if (isset($filters['search'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $downloadAccesses = $query->paginate($perPage);

        return [
            'downloads' => $downloadAccesses->items(),
            'pagination' => [
                'current_page' => $downloadAccesses->currentPage(),
                'last_page' => $downloadAccesses->lastPage(),
                'per_page' => $downloadAccesses->perPage(),
                'total' => $downloadAccesses->total(),
            ]
        ];
    }

    public function getUserLicenseKeys(User $user, array $filters = []): array
    {
        $query = LicenseKey::where('user_id', $user->id)
            ->with(['product', 'order'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $licenseKeys = $query->paginate($perPage);

        return [
            'licenses' => $licenseKeys->items(),
            'pagination' => [
                'current_page' => $licenseKeys->currentPage(),
                'last_page' => $licenseKeys->lastPage(),
                'per_page' => $licenseKeys->perPage(),
                'total' => $licenseKeys->total(),
            ]
        ];
    }

    public function getDigitalProductAnalytics(Product $product = null): array
    {
        $baseQuery = $product
            ? fn($model) => $model::where('product_id', $product->id)
            : fn($model) => $model::query();

        $totalDownloadAccesses = $baseQuery(DownloadAccess::class)->count();
        $activeDownloadAccesses = $baseQuery(DownloadAccess::class)->where('status', 'active')->count();
        $totalLicenseKeys = $baseQuery(LicenseKey::class)->count();
        $activeLicenseKeys = $baseQuery(LicenseKey::class)->where('status', 'active')->count();

        $downloadStats = $baseQuery(DownloadAccess::class)
            ->selectRaw('
                COUNT(*) as total_accesses,
                SUM(downloads_used) as total_downloads,
                AVG(downloads_used) as avg_downloads_per_access,
                COUNT(CASE WHEN downloads_used >= download_limit THEN 1 END) as fully_utilized
            ')
            ->first();

        $recentActivity = $baseQuery(DownloadAccess::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'download_accesses' => [
                'total' => $totalDownloadAccesses,
                'active' => $activeDownloadAccesses,
                'expired' => $baseQuery(DownloadAccess::class)->where('status', 'expired')->count(),
                'revoked' => $baseQuery(DownloadAccess::class)->where('status', 'revoked')->count(),
            ],
            'downloads' => [
                'total_downloads' => $downloadStats->total_downloads ?? 0,
                'average_per_access' => round($downloadStats->avg_downloads_per_access ?? 0, 2),
                'fully_utilized_accesses' => $downloadStats->fully_utilized ?? 0,
                'utilization_rate' => $totalDownloadAccesses > 0
                    ? round(($downloadStats->fully_utilized / $totalDownloadAccesses) * 100, 2)
                    : 0,
            ],
            'license_keys' => [
                'total' => $totalLicenseKeys,
                'active' => $activeLicenseKeys,
                'expired' => $baseQuery(LicenseKey::class)->where('status', 'expired')->count(),
                'revoked' => $baseQuery(LicenseKey::class)->where('status', 'revoked')->count(),
            ],
            'activity' => [
                'recent_accesses_30_days' => $recentActivity,
                'avg_daily_new_accesses' => round($recentActivity / 30, 2),
            ]
        ];
    }

    public function cleanupExpiredAccesses(): array
    {
        $expiredAccesses = DownloadAccess::where('expires_at', '<', now())
            ->where('status', 'active')
            ->get();

        $cleanedCount = 0;

        foreach ($expiredAccesses as $access) {
            $access->update(['status' => 'expired']);
            $cleanedCount++;
        }

        $expiredLicenses = LicenseKey::where('expires_at', '<', now())
            ->where('status', 'active')
            ->get();

        $expiredLicenseCount = 0;

        foreach ($expiredLicenses as $license) {
            $license->update(['status' => 'expired']);
            $expiredLicenseCount++;
        }

        Log::info('Expired digital product access cleanup completed', [
            'expired_accesses' => $cleanedCount,
            'expired_licenses' => $expiredLicenseCount
        ]);

        return [
            'expired_accesses' => $cleanedCount,
            'expired_licenses' => $expiredLicenseCount,
            'total_cleaned' => $cleanedCount + $expiredLicenseCount
        ];
    }

    public function validateDigitalProductPurchase(Product $product, User $user): bool
    {
        if (!$product->isDigital()) {
            throw new Exception('Product is not a digital product');
        }

        if (!$product->hasDigitalFiles()) {
            throw new Exception('Digital product has no files available for download');
        }

        $primaryFile = $product->primaryProductFile;
        if (!$primaryFile || !$primaryFile->canBeDownloaded()) {
            throw new Exception('Primary digital file is not available for download');
        }

        return true;
    }
}
