<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\Product;
use App\Services\V1\DigitalProducts\DigitalProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DigitalProductController extends Controller
{
    use ApiResponses;

    protected DigitalProductService $digitalProductService;

    public function __construct(DigitalProductService $digitalProductService)
    {
        $this->digitalProductService = $digitalProductService;
    }

    /**
     * Display a listing of digital products
     *
     * Retrieve all digital products with filtering options. Supports pagination and various filter criteria
     * including product type, license requirements, and search terms.
     *
     * @group Digital Product Management
     * @authenticated
     *
     * @queryParam page integer optional Page number for pagination. Example: 1
     * @queryParam per_page integer optional Number of items per page (max 100). Example: 15
     * @queryParam search string optional Search in product names and descriptions. Example: "software"
     * @queryParam product_type string optional Filter by product type (digital, hybrid). Example: digital
     * @queryParam requires_license boolean optional Filter by license requirement. Example: true
     * @queryParam status string optional Filter by status (active, inactive). Example: active
     * @queryParam sort_by string optional Sort field (name, created_at, price). Example: name
     * @queryParam sort_direction string optional Sort direction (asc, desc). Example: desc
     *
     * @response 200 scenario="Digital products retrieved successfully" {
     *   "data": {
     *     "products": [
     *       {
     *         "id": 1,
     *         "name": "ProjectManager Pro",
     *         "description": "Professional project management software",
     *         "price": 9999,
     *         "price_formatted": "£99.99",
     *         "product_type": "digital",
     *         "requires_license": true,
     *         "download_limit": 3,
     *         "download_expiry_days": 365,
     *         "supported_platforms": ["Windows", "macOS", "Linux"],
     *         "latest_version": "2.1.5",
     *         "file_count": 4,
     *         "total_file_size": "125.5 MB",
     *         "created_at": "2024-01-15T10:30:00Z"
     *       }
     *     ],
     *     "pagination": {
     *       "current_page": 1,
     *       "total": 25,
     *       "per_page": 15,
     *       "last_page": 2
     *     }
     *   },
     *   "message": "Digital products retrieved successfully.",
     *   "status": 200
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'product_type' => $request->get('product_type'),
                'requires_license' => $request->boolean('requires_license'),
                'status' => $request->get('status'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_direction' => $request->get('sort_direction', 'desc'),
                'per_page' => min($request->get('per_page', 15), 100),
            ];

            $products = $this->digitalProductService->getDigitalProducts($filters, $request->user());

            return $this->ok(
                'Digital products retrieved successfully.',
                $products
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display a specific digital product
     *
     * Retrieve detailed information about a specific digital product including files,
     * download statistics, license information, and recent activity.
     *
     * @group Digital Product Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the digital product. Example: 1
     *
     * @response 200 scenario="Digital product retrieved successfully" {
     *   "data": {
     *     "id": 1,
     *     "name": "ProjectManager Pro",
     *     "description": "Professional project management software",
     *     "price": 9999,
     *     "price_formatted": "£99.99",
     *     "product_type": "digital",
     *     "requires_license": true,
     *     "auto_deliver": true,
     *     "download_limit": 3,
     *     "download_expiry_days": 365,
     *     "supported_platforms": ["Windows", "macOS", "Linux"],
     *     "system_requirements": {
     *       "os": "Windows 10/11, macOS 10.14+",
     *       "ram": "4GB minimum, 8GB recommended"
     *     },
     *     "latest_version": "2.1.5",
     *     "version_control_enabled": true,
     *     "files": [
     *       {
     *         "id": 1,
     *         "name": "Windows Installer",
     *         "file_size_formatted": "45.2 MB",
     *         "version": "2.1.5",
     *         "is_primary": true,
     *         "download_count": 127
     *       }
     *     ],
     *     "download_stats": {
     *       "total_downloads": 127,
     *       "active_accesses": 8,
     *       "expired_accesses": 15
     *     },
     *     "license_stats": {
     *       "total_licenses": 23,
     *       "active_licenses": 18,
     *       "expired_licenses": 5
     *     }
     *   },
     *   "message": "Digital product retrieved successfully.",
     *   "status": 200
     * }
     *
     * @response 404 scenario="Digital product not found" {
     *   "message": "Digital product not found or not accessible.",
     *   "status": 404
     * }
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        try {
            if (!$product->isDigital()) {
                return $this->error('Product is not a digital product.', 400);
            }

            $productData = $this->digitalProductService->getDigitalProductDetails($product, $request->user());

            return $this->ok(
                'Digital product retrieved successfully.',
                $productData
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get digital product statistics and analytics
     *
     * Retrieve comprehensive analytics for digital products including download trends,
     * revenue metrics, license utilization, and performance indicators.
     *
     * @group Digital Product Management
     * @authenticated
     *
     * @queryParam product_id integer optional Filter statistics for specific product. Example: 1
     * @queryParam period string optional Time period (7days, 30days, 90days, 1year). Example: 30days
     * @queryParam vendor_id integer optional Filter by vendor (admin only). Example: 5
     *
     * @response 200 scenario="Statistics retrieved successfully" {
     *   "data": {
     *     "overview": {
     *       "total_digital_products": 25,
     *       "total_downloads": 1547,
     *       "total_revenue": 15847.99,
     *       "active_licenses": 328
     *     },
     *     "downloads": {
     *       "total": 1547,
     *       "this_period": 284,
     *       "average_per_product": 61.9,
     *       "most_downloaded_product": {
     *         "name": "ProjectManager Pro",
     *         "downloads": 127
     *       }
     *     },
     *     "license_usage": {
     *       "total_issued": 328,
     *       "active": 298,
     *       "expired": 25,
     *       "revoked": 5,
     *       "utilization_rate": 85.2
     *     },
     *     "trends": {
     *       "daily_downloads": [
     *         {"date": "2024-01-20", "downloads": 15},
     *         {"date": "2024-01-21", "downloads": 22}
     *       ]
     *     }
     *   },
     *   "message": "Digital product statistics retrieved successfully.",
     *   "status": 200
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'product_id' => $request->get('product_id'),
                'period' => $request->get('period', '30days'),
                'vendor_id' => $request->get('vendor_id'),
            ];

            $statistics = $this->digitalProductService->getDigitalProductStatistics($filters, $request->user());

            return $this->ok(
                'Digital product statistics retrieved successfully.',
                $statistics
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get user's digital product library
     *
     * Retrieve all digital products that a user has access to, including download links,
     * license keys, and access status. Useful for customer download portals.
     *
     * @group Digital Product Management
     * @authenticated
     *
     * @queryParam user_id integer optional Specific user ID (admin/vendor only). Example: 15
     * @queryParam status string optional Filter by access status (active, expired, revoked). Example: active
     * @queryParam product_type string optional Filter by product type. Example: digital
     * @queryParam search string optional Search in product names. Example: "software"
     *
     * @response 200 scenario="User library retrieved successfully" {
     *   "data": {
     *     "products": [
     *       {
     *         "product": {
     *           "id": 1,
     *           "name": "ProjectManager Pro",
     *           "latest_version": "2.1.5"
     *         },
     *         "access": {
     *           "token": "abc123def456",
     *           "status": "active",
     *           "downloads_remaining": 2,
     *           "expires_at": "2024-12-15T23:59:59Z"
     *         },
     *         "license": {
     *           "key": "PROJ-XXXX-XXXX-XXXX",
     *           "type": "single_use",
     *           "activations_remaining": 1
     *         },
     *         "files": [
     *           {
     *             "name": "Windows Installer",
     *             "size": "45.2 MB",
     *             "download_url": "/api/v1/digital/download/abc123def456"
     *           }
     *         ]
     *       }
     *     ]
     *   },
     *   "message": "User digital library retrieved successfully.",
     *   "status": 200
     * }
     */
    public function userLibrary(Request $request): JsonResponse
    {
        try {
            $filters = [
                'user_id' => $request->get('user_id'),
                'status' => $request->get('status'),
                'product_type' => $request->get('product_type'),
                'search' => $request->get('search'),
                'per_page' => min($request->get('per_page', 15), 100),
            ];

            $userId = $filters['user_id'] ?? $request->user()->id;

            // Permission check for accessing other users' libraries
            if ($userId !== $request->user()->id && !$request->user()->hasPermission('view_all_digital_products')) {
                return $this->error('Insufficient permissions to view other users\' libraries.', 403);
            }

            $library = $this->digitalProductService->getUserDigitalProducts(
                \App\Models\User::findOrFail($userId),
                $filters
            );

            return $this->ok(
                'User digital library retrieved successfully.',
                $library
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Cleanup expired digital product access
     *
     * Clean up expired download access tokens and license keys. This is typically
     * run as a scheduled task but can be triggered manually by administrators.
     *
     * @group Digital Product Management
     * @authenticated
     *
     * @response 200 scenario="Cleanup completed successfully" {
     *   "data": {
     *     "expired_accesses": 12,
     *     "expired_licenses": 8,
     *     "total_cleaned": 20
     *   },
     *   "message": "Digital product cleanup completed successfully.",
     *   "status": 200
     * }
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            if (!$request->user()->hasPermission('manage_digital_products')) {
                return $this->error('Insufficient permissions to perform cleanup.', 403);
            }

            $result = $this->digitalProductService->cleanupExpiredAccesses();

            return $this->ok(
                'Digital product cleanup completed successfully.',
                $result
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
