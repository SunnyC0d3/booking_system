<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\User as DB;
use App\Services\V1\Users\User;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\UpdateUserRequest;
use Illuminate\Http\Request;
use \Exception;

class UserController extends Controller
{
    use ApiResponses;

    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Retrieve user profile information
     *
     * Get detailed profile information for a specific user. Users can view their own complete profile
     * including personal details, address information, security settings, and account status.
     * Other authenticated users can only view basic public profile information for privacy protection.
     * This endpoint is commonly used for profile pages and account management interfaces.
     *
     * @group User Profile
     * @authenticated
     *
     * @urlParam user integer required The ID of the user whose profile to retrieve. Example: 123
     *
     * @response 200 scenario="Own profile retrieved successfully" {
     *   "message": "User details retrieved.",
     *   "data": {
     *     "id": 123,
     *     "name": "Sarah Johnson",
     *     "email": "sarah.johnson@example.com",
     *     "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *     "stripe_customer_id": "cus_1234567890abcdef",
     *     "password_changed_at": "2025-01-01T12:00:00.000000Z",
     *     "last_login_at": "2025-01-16T09:15:00.000000Z",
     *     "last_login_ip": "192.168.1.100",
     *     "created_at": "2024-12-15T10:30:00.000000Z",
     *     "updated_at": "2025-01-16T09:15:00.000000Z",
     *     "deleted_at": null,
     *     "user_address": {
     *       "id": 45,
     *       "address_line1": "123 Oak Street",
     *       "address_line2": "Apartment 4B",
     *       "city": "London",
     *       "state": "England",
     *       "country": "United Kingdom",
     *       "postal_code": "SW1A 1AA"
     *     },
     *     "role": {
     *       "id": 7,
     *       "name": "User"
     *     },
     *     "vendors": [
     *       {
     *         "id": 12,
     *         "name": "Sarah's Boutique",
     *         "description": "Handcrafted jewelry and accessories",
     *         "logo": "https://yourapi.com/storage/vendor-logos/sarahs-boutique.jpg",
     *         "created_at": "2025-01-05T14:20:00.000000Z"
     *       }
     *     ],
     *     "security_info": {
     *       "requires_password_change": false,
     *       "days_until_password_expiry": 45,
     *       "security_score": {
     *         "score": 85,
     *         "level": "good",
     *         "issues": [
     *           "Consider enabling two-factor authentication"
     *         ]
     *       },
     *       "is_account_locked": false
     *     }
     *   }
     * }
     *
     * @response 200 scenario="Other user's public profile" {
     *   "message": "User details retrieved.",
     *   "data": {
     *     "id": 456,
     *     "name": "John Smith",
     *     "email": "j***@example.com",
     *     "email_verified_at": "2025-01-08T12:00:00.000000Z",
     *     "created_at": "2024-11-20T16:45:00.000000Z",
     *     "updated_at": "2025-01-15T11:30:00.000000Z",
     *     "role": {
     *       "id": 7,
     *       "name": "User"
     *     },
     *     "vendors": []
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
     * @response 404 scenario="User not found" {
     *   "message": "No query results for model [App\\Models\\User] 999"
     * }
     */
    public function show(Request $request, DB $user)
    {
        try {
            return $this->user->find($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update user profile information
     *
     * Update the authenticated user's profile information including personal details and address.
     * Users can only update their own profiles for security reasons. This endpoint supports
     * partial updates - only provided fields will be updated, others remain unchanged.
     * Password updates require the current password for security verification.
     *
     * @group User Profile
     * @authenticated
     *
     * @urlParam user integer required The ID of the user to update. Must match the authenticated user's ID. Example: 123
     *
     * @bodyParam name string optional The user's full name. Will be automatically formatted with proper capitalization. Example: Sarah Jane Johnson
     * @bodyParam email string optional The user's email address. Must be unique across all users. Example: sarah.johnson.new@example.com
     * @bodyParam password string optional New password. Must be at least 8 characters. Requires current password verification via separate endpoint. Example: MyNewSecurePassword123!
     * @bodyParam role_id integer optional The user's role ID. Only admins can change user roles. Example: 7
     * @bodyParam address object optional Complete address information for shipping and billing.
     * @bodyParam address.address_line1 string required Primary address line. Example: 456 Maple Avenue
     * @bodyParam address.address_line2 string optional Secondary address line (apartment, suite, etc.). Example: Suite 201
     * @bodyParam address.city string required City name. Example: Manchester
     * @bodyParam address.state string optional State or region. Example: Greater Manchester
     * @bodyParam address.country string required Country name. Example: United Kingdom
     * @bodyParam address.postal_code string required Postal or ZIP code. Example: M1 1AA
     *
     * @response 200 scenario="Profile updated successfully" {
     *   "message": "User updated successfully.",
     *   "data": {
     *     "id": 123,
     *     "name": "Sarah Jane Johnson",
     *     "email": "sarah.johnson.new@example.com",
     *     "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *     "stripe_customer_id": "cus_1234567890abcdef",
     *     "password_changed_at": "2025-01-01T12:00:00.000000Z",
     *     "last_login_at": "2025-01-16T09:15:00.000000Z",
     *     "last_login_ip": "192.168.1.100",
     *     "created_at": "2024-12-15T10:30:00.000000Z",
     *     "updated_at": "2025-01-16T15:22:00.000000Z",
     *     "deleted_at": null,
     *     "user_address": {
     *       "id": 45,
     *       "address_line1": "456 Maple Avenue",
     *       "address_line2": "Suite 201",
     *       "city": "Manchester",
     *       "state": "Greater Manchester",
     *       "country": "United Kingdom",
     *       "postal_code": "M1 1AA"
     *     },
     *     "role": {
     *       "id": 7,
     *       "name": "User"
     *     },
     *     "vendors": [
     *       {
     *         "id": 12,
     *         "name": "Sarah's Boutique",
     *         "description": "Handcrafted jewelry and accessories",
     *         "logo": "https://yourapi.com/storage/vendor-logos/sarahs-boutique.jpg"
     *       }
     *     ],
     *     "security_info": {
     *       "requires_password_change": false,
     *       "days_until_password_expiry": 45,
     *       "security_score": {
     *         "score": 85,
     *         "level": "good",
     *         "issues": [
     *           "Consider enabling two-factor authentication"
     *         ]
     *       },
     *       "is_account_locked": false
     *     }
     *   }
     * }
     *
     * @response 200 scenario="Address created for first time" {
     *   "message": "User updated successfully.",
     *   "data": {
     *     "id": 123,
     *     "name": "Sarah Johnson",
     *     "email": "sarah.johnson@example.com",
     *     "user_address": {
     *       "id": 67,
     *       "address_line1": "789 Pine Street",
     *       "address_line2": null,
     *       "city": "Edinburgh",
     *       "state": "Scotland",
     *       "country": "United Kingdom",
     *       "postal_code": "EH1 1YZ"
     *     }
     *   }
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Cannot update other user's profile" {
     *   "message": "You can only update your own profile."
     * }
     *
     * @response 403 scenario="Insufficient permissions for role change" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The email has already been taken.",
     *     "The password must be at least 8 characters.",
     *     "The address.address_line1 field is required when address is present.",
     *     "The address.city field is required when address is present.",
     *     "The address.country field is required when address is present.",
     *     "The address.postal_code field is required when address is present."
     *   ]
     * }
     *
     * @response 404 scenario="User not found" {
     *   "message": "No query results for model [App\\Models\\User] 999"
     * }
     *
     * @response 400 scenario="Email verification required" {
     *   "message": "Email verification required after email change. Please check your new email for verification link."
     * }
     */
    public function update(UpdateUserRequest $request, DB $user)
    {
        try {
            return $this->user->update($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
