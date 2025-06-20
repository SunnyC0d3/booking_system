<?php

namespace App\Http\Controllers\V1\Public;

use App\Services\V1\Users\Vendor;
use App\Models\Vendor as DB;
use Illuminate\Http\Request;
use App\Requests\V1\UpdateVendorRequest;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
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
     * Retrieve a specific vendor profile
     *
     * Get detailed information about a specific vendor including their profile details, logo, and media.
     * This endpoint is typically used for vendor profile pages and allows authenticated users to view
     * vendor information. Vendors can view their own complete profile, while other users may see
     * limited public information depending on privacy settings.
     *
     * @group Vendor Profile
     * @authenticated
     *
     * @urlParam vendor integer required The ID of the vendor to retrieve. Example: 12
     *
     * @response 200 scenario="Vendor profile retrieved successfully" {
     *   "message": "Vendor details retrieved.",
     *   "data": {
     *     "id": 12,
     *     "name": "TechCraft Solutions",
     *     "description": "Innovative technology solutions for modern businesses. We specialize in custom software development, cloud services, and digital transformation consulting.",
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Mitchell",
     *       "email": "sarah@techcraft.com",
     *       "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *       "created_at": "2024-12-15T10:30:00.000000Z",
     *       "updated_at": "2025-01-16T09:15:00.000000Z"
     *     },
     *     "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *     "media": [
     *       {
     *         "id": 78,
     *         "url": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *         "name": "TechCraft Logo",
     *         "file_name": "techcraft-logo.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 156789
     *       }
     *     ],
     *     "products_count": 23,
     *     "created_at": "2025-01-05T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T16:45:00.000000Z",
     *     "deleted_at": null
     *   }
     * }
     *
     * @response 200 scenario="Vendor with no products" {
     *   "message": "Vendor details retrieved.",
     *   "data": {
     *     "id": 8,
     *     "name": "Startup Innovations",
     *     "description": "New vendor focused on innovative product development",
     *     "user": {
     *       "id": 23,
     *       "name": "Alex Johnson",
     *       "email": "alex@startup-innovations.com",
     *       "email_verified_at": "2025-01-14T10:00:00.000000Z"
     *     },
     *     "logo": "",
     *     "media": [],
     *     "products_count": 0,
     *     "created_at": "2025-01-14T09:30:00.000000Z",
     *     "updated_at": "2025-01-14T09:30:00.000000Z",
     *     "deleted_at": null
     *   }
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *   "message": "No query results for model [App\\Models\\Vendor] 999"
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
     * Update vendor profile information
     *
     * Update the authenticated vendor's profile information including business details, description, and logo.
     * Vendors can only update their own profiles. This endpoint supports partial updates - only provided
     * fields will be updated, others remain unchanged. Logo uploads are processed securely with validation.
     *
     * @group Vendor Profile
     * @authenticated
     *
     * @urlParam vendor integer required The ID of the vendor to update. Must belong to the authenticated user. Example: 12
     *
     * @bodyParam user_id integer optional The user ID associated with this vendor. Only admins can change this. Example: 45
     * @bodyParam name string optional The vendor's business name. Will be used for public display. Example: TechCraft Solutions Ltd
     * @bodyParam description string optional Detailed description of the vendor's business, services, or products. Maximum 1000 characters. Example: Leading provider of innovative technology solutions for modern businesses. We specialize in custom software development, cloud migration services, and digital transformation consulting with over 10 years of industry experience.
     * @bodyParam logo file optional New logo file for the vendor. Must be an image (JPG, JPEG, PNG) with maximum size of 2MB. Will replace existing logo if provided.
     *
     * @response 200 scenario="Vendor profile updated successfully" {
     *   "message": "Vendor updated successfully.",
     *   "data": {
     *     "id": 12,
     *     "name": "TechCraft Solutions Ltd",
     *     "description": "Leading provider of innovative technology solutions for modern businesses. We specialize in custom software development, cloud migration services, and digital transformation consulting with over 10 years of industry experience.",
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Mitchell",
     *       "email": "sarah@techcraft.com",
     *       "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *     },
     *     "logo": "https://yourapi.com/storage/vendor-logos/new-techcraft-logo.jpg",
     *     "media": [
     *       {
     *         "id": 82,
     *         "url": "https://yourapi.com/storage/vendor-logos/new-techcraft-logo.jpg",
     *         "name": "Updated TechCraft Logo",
     *         "file_name": "new-techcraft-logo.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 189456
     *       }
     *     ],
     *     "products_count": 23,
     *     "created_at": "2025-01-05T14:20:00.000000Z",
     *     "updated_at": "2025-01-16T11:30:00.000000Z",
     *     "deleted_at": null
     *   }
     * }
     *
     * @response 200 scenario="Partial update without logo" {
     *   "message": "Vendor updated successfully.",
     *   "data": {
     *     "id": 12,
     *     "name": "TechCraft Solutions Ltd",
     *     "description": "Updated business description with new focus areas and expanded service offerings.",
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Mitchell",
     *       "email": "sarah@techcraft.com"
     *     },
     *     "logo": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *     "media": [
     *       {
     *         "id": 78,
     *         "url": "https://yourapi.com/storage/vendor-logos/techcraft-logo.jpg",
     *         "name": "TechCraft Logo",
     *         "file_name": "techcraft-logo.jpg",
     *         "mime_type": "image/jpeg",
     *         "size": 156789
     *       }
     *     ],
     *     "products_count": 23,
     *     "created_at": "2025-01-05T14:20:00.000000Z",
     *     "updated_at": "2025-01-16T11:45:00.000000Z",
     *     "deleted_at": null
     *   }
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Cannot update other vendor's profile" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 403 scenario="Insufficient permissions for user_id change" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The description may not be greater than 1000 characters.",
     *     "The logo must be an image.",
     *     "The logo may not be greater than 2048 kilobytes.",
     *     "The logo must be a file of type: jpg, jpeg, png."
     *   ]
     * }
     *
     * @response 404 scenario="Vendor not found" {
     *   "message": "No query results for model [App\\Models\\Vendor] 999"
     * }
     *
     * @response 413 scenario="Logo file too large" {
     *   "message": "File too large. Maximum size is 2.0 MB."
     * }
     *
     * @response 422 scenario="Invalid file type" {
     *   "message": "File type .gif not allowed. Allowed types: jpg, jpeg, png"
     * }
     *
     * @response 500 scenario="Logo upload failed" {
     *   "message": "Failed to process logo file: Unable to write file to disk."
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
}
