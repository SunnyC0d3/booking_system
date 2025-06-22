<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Users\Vendor;
use App\Models\Vendor as DB;
use Illuminate\Http\Request;
use App\Requests\V1\StoreVendorRequest;
use App\Requests\V1\UpdateVendorRequest;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Filters\V1\VendorFilter;
use \Exception;

class VendorController extends Controller
{
    use ApiResponses;

    private $vendor;

    public function __construct(Vendor $vendor)
    {
        $this->vendor = $vendor;
    }

    /**
     * Retrieve a paginated list of vendors with advanced filtering
     *
     * Get a comprehensive paginated list of all vendors in the system with advanced filtering capabilities.
     * This endpoint provides administrators with complete vendor information including business details,
     * associated user accounts, product counts, media assets, and vendor status. Essential for vendor
     * management, marketplace oversight, business analytics, and partner relationship management.
     * Supports powerful filtering and search functionality to help administrators efficiently manage
     * vendor partnerships and monitor marketplace activity.
     *
     * @group Vendor Management
     * @authenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[name] string optional Filter vendors by business name (partial match supported). Example: tech
     * @queryParam filter[search] string optional Search across vendor names and descriptions. Example: electronics hardware
     * @queryParam filter[user] string optional Filter by user ID(s) who own vendors. Single ID or comma-separated multiple IDs. Example: 45,67,89
     * @queryParam filter[created_at] string optional Filter by vendor registration date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[updated_at] string optional Filter by last update date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2024-12-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Options: user,products,media. Example: user,products
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of vendors per page (max 100). Default: 15. Example: 25
     * @queryParam sort string optional Sort vendors. Prefix with '-' for descending. Available: name, created_at, updated_at. Example: -created_at,name
     *
     * @response 200 scenario="Vendors retrieved successfully" {
     *     "message": "Vendors retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 12,
     *                 "name": "TechCraft Solutions",
     *                 "description": "Innovative technology solutions for modern businesses. We specialize in custom software development, cloud services, and digital transformation consulting with over 10 years of industry experience.",
     *                 "user": {
     *                     "id": 45,
     *                     "name": "Sarah Mitchell",
     *                     "email": "sarah@techcraft.com",
     *                     "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *                     "created_at": "2024-12-15T10:30:00.000000Z",
     *                     "updated_at": "2025-01-16T09:15:00.000000Z"
     *                 },
     *                 "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *                 "media": [
     *                     {
     *                         "id": 78,
     *                         "url": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *                         "name": "TechCraft Logo",
     *                         "file_name": "techcraft-logo.jpg",
     *                         "mime_type": "image/jpeg",
     *                         "size": 156789
     *                     }
     *                 ],
     *                 "products_count": 23,
     *                 "created_at": "2025-01-05T14:20:00.000000Z",
     *                 "updated_at": "2025-01-15T16:45:00.000000Z",
     *                 "deleted_at": null
     *             },
     *             {
     *                 "id": 8,
     *                 "name": "AudioTech Solutions",
     *                 "description": "Premium audio equipment manufacturer specializing in wireless technology and high-fidelity sound systems for both consumer and professional markets.",
     *                 "user": {
     *                     "id": 23,
     *                     "name": "James Thompson",
     *                     "email": "james@audiotech.com",
     *                     "email_verified_at": "2025-01-08T12:00:00.000000Z",
     *                     "created_at": "2024-11-20T16:45:00.000000Z",
     *                     "updated_at": "2025-01-14T11:30:00.000000Z"
     *                 },
     *                 "logo": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *                 "media": [
     *                     {
     *                         "id": 56,
     *                         "url": "https://yourapi.com/storage/vendor-logos/audiotech-logo.jpg",
     *                         "name": "AudioTech Logo",
     *                         "file_name": "audiotech-logo.jpg",
     *                         "mime_type": "image/jpeg",
     *                         "size": 198432
     *                     }
     *                 ],
     *                 "products_count": 47,
     *                 "created_at": "2024-12-10T11:15:00.000000Z",
     *                 "updated_at": "2025-01-14T16:20:00.000000Z",
     *                 "deleted_at": null
     *             },
     *             {
     *                 "id": 15,
     *                 "name": "Green Earth Organics",
     *                 "description": "Sustainable organic products for health-conscious consumers. We source directly from certified organic farms and focus on environmentally responsible packaging.",
     *                 "user": {
     *                     "id": 67,
     *                     "name": "Maria Rodriguez",
     *                     "email": "maria@greenearth.com",
     *                     "email_verified_at": "2025-01-12T09:30:00.000000Z",
     *                     "created_at": "2025-01-12T09:00:00.000000Z",
     *                     "updated_at": "2025-01-15T14:45:00.000000Z"
     *                 },
     *                 "logo": "",
     *                 "media": [],
     *                 "products_count": 12,
     *                 "created_at": "2025-01-12T10:30:00.000000Z",
     *                 "updated_at": "2025-01-15T14:45:00.000000Z",
     *                 "deleted_at": null
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 156,
     *         "last_page": 11,
     *         "from": 1,
     *         "to": 15,
     *         "path": "https://yourapi.com/api/v1/admin/vendors",
     *         "first_page_url": "https://yourapi.com/api/v1/admin/vendors?page=1",
     *         "last_page_url": "https://yourapi.com/api/v1/admin/vendors?page=11",
     *         "next_page_url": "https://yourapi.com/api/v1/admin/vendors?page=2",
     *         "prev_page_url": null
     *     }
     * }
     *
     * @response 200 scenario="Filtered vendors by user" {
     *     "message": "Vendors retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 12,
     *                 "name": "TechCraft Solutions",
     *                 "description": "Innovative technology solutions for modern businesses.",
     *                 "user": {
     *                     "id": 45,
     *                     "name": "Sarah Mitchell",
     *                     "email": "sarah@techcraft.com"
     *                 },
     *                 "products_count": 23,
     *                 "created_at": "2025-01-05T14:20:00.000000Z"
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 1,
     *         "last_page": 1
     *     }
     * }
     *
     * @response 200 scenario="No vendors found" {
     *     "message": "Vendors retrieved successfully.",
     *     "data": {
     *         "data": [],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 0,
     *         "last_page": 1,
     *         "from": null,
     *         "to": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *     "errors": [
     *         "The filter.user field must contain only comma-separated numbers.",
     *         "The filter.created_at field must be a valid date or date range in YYYY-MM-DD format.",
     *         "The filter.include field contains invalid relationships. Available: user,products,media"
     *     ]
     * }
     */
    public function index(Request $request, VendorFilter $filter)
    {
        try {
            return $this->vendor->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new vendor profile
     *
     * Register a new vendor in the marketplace with complete business profile information. This endpoint
     * allows administrators to onboard new business partners with detailed company information, branding
     * assets, and user account associations. The created vendor can immediately begin listing products
     * and managing their marketplace presence. Supports secure logo upload with comprehensive validation.
     *
     * @group Vendor Management
     * @authenticated
     *
     * @bodyParam user_id integer required The ID of the user account that will own this vendor profile. Must be a valid, existing user. Example: 45
     * @bodyParam name string required The vendor's business name. Will be used for public display and branding. Example: TechCraft Solutions Ltd
     * @bodyParam description string required Detailed description of the vendor's business, products, or services. Maximum 1000 characters. Example: Leading provider of innovative technology solutions for modern businesses. We specialize in custom software development, cloud services, and digital transformation consulting.
     * @bodyParam logo file optional Business logo file. Must be an image (JPG, JPEG, PNG) with maximum size of 2MB. Recommended dimensions: 200x200px minimum.
     *
     * @response 200 scenario="Vendor created successfully" {
     *     "message": "Vendor created successfully!",
     *     "data": {
     *         "id": 25,
     *         "name": "TechCraft Solutions Ltd",
     *         "description": "Leading provider of innovative technology solutions for modern businesses. We specialize in custom software development, cloud services, and digital transformation consulting.",
     *         "user": {
     *             "id": 45,
     *             "name": "Sarah Mitchell",
     *             "email": "sarah@techcraft.com",
     *             "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *             "created_at": "2024-12-15T10:30:00.000000Z",
     *             "updated_at": "2025-01-16T09:15:00.000000Z"
     *         },
     *         "logo": "https://yourapi.com/storage/vendor-logos/secure-logo-hash123.jpg",
     *         "media": [
     *             {
     *                 "id": 89,
     *                 "url": "https://yourapi.com/storage/vendor-logos/secure-logo-hash123.jpg",
     *                 "name": "TechCraft Solutions Logo",
     *                 "file_name": "secure-logo-hash123.jpg",
     *                 "mime_type": "image/jpeg",
     *                 "size": 187456
     *             }
     *         ],
     *         "products_count": 0,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z",
     *         "deleted_at": null
     *     }
     * }
     *
     * @response 200 scenario="Vendor created without logo" {
     *     "message": "Vendor created successfully!",
     *     "data": {
     *         "id": 26,
     *         "name": "Green Earth Organics",
     *         "description": "Sustainable organic products for health-conscious consumers.",
     *         "user": {
     *             "id": 67,
     *             "name": "Maria Rodriguez",
     *             "email": "maria@greenearth.com"
     *         },
     *         "logo": "",
     *         "media": [],
     *         "products_count": 0,
     *         "created_at": "2025-01-16T14:35:00.000000Z",
     *         "updated_at": "2025-01-16T14:35:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The user id field is required.",
     *         "The name field is required.",
     *         "The description field is required.",
     *         "The user id must exist in users table.",
     *         "The description may not be greater than 1000 characters.",
     *         "The logo must be an image.",
     *         "The logo may not be greater than 2048 kilobytes."
     *     ]
     * }
     *
     * @response 422 scenario="User already has vendor" {
     *     "errors": [
     *         "The selected user already has a vendor profile associated with their account."
     *     ]
     * }
     *
     * @response 422 scenario="Invalid user assignment" {
     *     "errors": [
     *         "The selected user id is invalid."
     *     ]
     * }
     *
     * @response 413 scenario="Logo file too large" {
     *     "message": "File too large. Maximum size is 2.0 MB."
     * }
     *
     * @response 422 scenario="Invalid file type" {
     *     "message": "File type .gif not allowed. Allowed types: jpg, jpeg, png"
     * }
     */
    public function store(StoreVendorRequest $request)
    {
        try {
            return $this->vendor->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific vendor's detailed information
     *
     * Get comprehensive information about a specific vendor including business details, associated user
     * account, branding assets, product statistics, and marketplace performance data. This endpoint
     * provides administrators with complete visibility into vendor profiles for partnership management,
     * performance monitoring, and customer service purposes.
     *
     * @group Vendor Management
     * @authenticated
     *
     * @urlParam vendor integer required The ID of the vendor to retrieve. Example: 12
     *
     * @response 200 scenario="Vendor details retrieved successfully" {
     *     "message": "Vendor details retrieved.",
     *     "data": {
     *         "id": 12,
     *         "name": "TechCraft Solutions",
     *         "description": "Innovative technology solutions for modern businesses. We specialize in custom software development, cloud services, and digital transformation consulting with over 10 years of industry experience. Our team of certified professionals delivers cutting-edge solutions that drive business growth and operational efficiency.",
     *         "user": {
     *             "id": 45,
     *             "name": "Sarah Mitchell",
     *             "email": "sarah@techcraft.com",
     *             "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *             "stripe_customer_id": "cus_techcraft123456",
     *             "last_login_at": "2025-01-16T09:15:00.000000Z",
     *             "created_at": "2024-12-15T10:30:00.000000Z",
     *             "updated_at": "2025-01-16T09:15:00.000000Z",
     *             "user_address": {
     *                 "id": 67,
     *                 "address_line1": "123 Innovation Drive",
     *                 "address_line2": "Tech Hub Building, Floor 5",
     *                 "city": "London",
     *                 "state": "England",
     *                 "country": "United Kingdom",
     *                 "postal_code": "EC2A 4DP"
     *             }
     *         },
     *         "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo-secure.jpg",
     *         "media": [
     *             {
     *                 "id": 78,
     *                 "url": "https://yourapi.com/storage/vendor-logos/techcraft-logo-secure.jpg",
     *                 "name": "TechCraft Solutions Logo",
     *                 "file_name": "techcraft-logo-secure.jpg",
     *                 "mime_type": "image/jpeg",
     *                 "size": 156789
     *             }
     *         ],
     *         "products_count": 23,
     *         "created_at": "2025-01-05T14:20:00.000000Z",
     *         "updated_at": "2025-01-15T16:45:00.000000Z",
     *         "deleted_at": null
     *     }
     * }
     *
     * @response 200 scenario="New vendor without logo or products" {
     *     "message": "Vendor details retrieved.",
     *     "data": {
     *         "id": 15,
     *         "name": "Startup Innovations",
     *         "description": "New vendor focused on innovative product development and emerging technologies.",
     *         "user": {
     *             "id": 89,
     *             "name": "Alex Johnson",
     *             "email": "alex@startup-innovations.com",
     *             "email_verified_at": "2025-01-14T10:00:00.000000Z",
     *             "created_at": "2025-01-14T09:30:00.000000Z"
     *         },
     *         "logo": "",
     *         "media": [],
     *         "products_count": 0,
     *         "created_at": "2025-01-14T09:30:00.000000Z",
     *         "updated_at": "2025-01-14T09:30:00.000000Z",
     *         "deleted_at": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *     "message": "No query results for model [App\\Models\\Vendor] 999"
     * }
     */
    public function show(Request $request, DB $vendor)
    {
        try {
            return $this->vendor->find($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing vendor's information
     *
     * Modify an existing vendor's profile information including business details, user associations,
     * descriptions, and branding assets. This endpoint supports partial updates - only provided fields
     * will be updated, others remain unchanged. Administrators can update vendor information for
     * partnership management, profile corrections, and branding updates. Includes secure logo
     * replacement with comprehensive validation.
     *
     * @group Vendor Management
     * @authenticated
     *
     * @urlParam vendor integer required The ID of the vendor to update. Example: 12
     *
     * @bodyParam user_id integer optional Updated user ID for vendor ownership. Only admins can change this. Must be a valid, existing user. Example: 67
     * @bodyParam name string optional Updated vendor business name. Will be used for public display and branding. Example: TechCraft Solutions International Ltd
     * @bodyParam description string optional Updated business description. Maximum 1000 characters. Example: Global leader in innovative technology solutions. We specialize in enterprise software development, cloud migration services, AI integration, and digital transformation consulting with over 15 years of proven industry experience.
     * @bodyParam logo file optional New logo file to replace existing one. Must be an image (JPG, JPEG, PNG) with maximum size of 2MB. Will completely replace the current logo.
     *
     * @response 200 scenario="Vendor updated successfully with new logo" {
     *     "message": "Vendor updated successfully.",
     *     "data": {
     *         "id": 12,
     *         "name": "TechCraft Solutions International Ltd",
     *         "description": "Global leader in innovative technology solutions. We specialize in enterprise software development, cloud migration services, AI integration, and digital transformation consulting with over 15 years of proven industry experience.",
     *         "user": {
     *             "id": 45,
     *             "name": "Sarah Mitchell",
     *             "email": "sarah@techcraft.com",
     *             "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *         },
     *         "logo": "https://yourapi.com/storage/vendor-logos/new-techcraft-logo-hash456.jpg",
     *         "media": [
     *             {
     *                 "id": 95,
     *                 "url": "https://yourapi.com/storage/vendor-logos/new-techcraft-logo-hash456.jpg",
     *                 "name": "Updated TechCraft Logo",
     *                 "file_name": "new-techcraft-logo-hash456.jpg",
     *                 "mime_type": "image/jpeg",
     *                 "size": 203567
     *             }
     *         ],
     *         "products_count": 23,
     *         "created_at": "2025-01-05T14:20:00.000000Z",
     *         "updated_at": "2025-01-16T11:30:00.000000Z",
     *         "deleted_at": null
     *     }
     * }
     *
     * @response 200 scenario="Partial update (description only)" {
     *     "message": "Vendor updated successfully.",
     *     "data": {
     *         "id": 12,
     *         "name": "TechCraft Solutions",
     *         "description": "Updated business description with new focus areas and expanded service offerings for international markets.",
     *         "user": {
     *             "id": 45,
     *             "name": "Sarah Mitchell",
     *             "email": "sarah@techcraft.com"
     *         },
     *         "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo-secure.jpg",
     *         "products_count": 23,
     *         "updated_at": "2025-01-16T11:45:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="User ownership transfer" {
     *     "message": "Vendor updated successfully.",
     *     "data": {
     *         "id": 12,
     *         "name": "TechCraft Solutions",
     *         "description": "Innovative technology solutions for modern businesses.",
     *         "user": {
     *             "id": 67,
     *             "name": "Maria Rodriguez",
     *             "email": "maria@techcraft.com",
     *             "email_verified_at": "2025-01-12T09:30:00.000000Z"
     *         },
     *         "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo-secure.jpg",
     *         "products_count": 23,
     *         "updated_at": "2025-01-16T12:00:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *     "message": "No query results for model [App\\Models\\Vendor] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The user id must exist in users table.",
     *         "The description may not be greater than 1000 characters.",
     *         "The logo must be an image.",
     *         "The logo may not be greater than 2048 kilobytes.",
     *         "The logo must be a file of type: jpg, jpeg, png."
     *     ]
     * }
     *
     * @response 422 scenario="User already has vendor" {
     *     "errors": [
     *         "The selected user already has a vendor profile associated with their account."
     *     ]
     * }
     *
     * @response 413 scenario="Logo file too large" {
     *     "message": "File too large. Maximum size is 2.0 MB."
     * }
     *
     * @response 422 scenario="Invalid file type" {
     *     "message": "File type .gif not allowed. Allowed types: jpg, jpeg, png"
     * }
     *
     * @response 500 scenario="Logo upload failed" {
     *     "message": "Failed to process logo file: Unable to write file to disk."
     * }
     */
    public function update(UpdateVendorRequest $request, DB $vendor)
    {
        try {
            return $this->vendor->update($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete a vendor profile
     *
     * Permanently remove a vendor profile from the marketplace along with their associated media assets.
     * This action is irreversible and will completely remove the vendor's business profile from the
     * system. Exercise extreme caution when deleting vendors as this may affect product listings,
     * order history, customer relationships, and marketplace analytics. Consider deactivating vendor
     * accounts instead of deletion for business continuity and data integrity.
     *
     * **Warning**: This permanently deletes the vendor profile and all associated media files.
     * Product listings, order history, and customer relationships may be affected. Ensure this
     * action is intentional and properly authorized by business stakeholders.
     *
     * @group Vendor Management
     * @authenticated
     *
     * @urlParam vendor integer required The ID of the vendor to permanently delete. Example: 12
     *
     * @response 200 scenario="Vendor deleted successfully" {
     *     "message": "Vendor deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *     "message": "No query results for model [App\\Models\\Vendor] 999"
     * }
     *
     * @response 409 scenario="Vendor has active products" {
     *     "message": "Cannot delete vendor with active product listings. Please remove or transfer all products first."
     * }
     *
     * @response 409 scenario="Vendor has pending orders" {
     *     "message": "Cannot delete vendor with pending or recent orders. Please resolve all order-related activities first."
     * }
     *
     * @response 422 scenario="Vendor has dependencies" {
     *     "message": "This vendor has active marketplace dependencies and cannot be deleted. Please contact system administrator."
     * }
     *
     * @response 422 scenario="Protected vendor account" {
     *     "message": "Cannot delete featured or premium vendor accounts without special authorization."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *     "message": "An error occurred while deleting the vendor profile and associated media files."
     * }
     */
    public function destroy(Request $request, DB $vendor)
    {
        try {
            return $this->vendor->delete($request, $vendor);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
