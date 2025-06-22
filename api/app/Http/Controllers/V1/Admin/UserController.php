<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User as DB;
use App\Services\V1\Users\User;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreUserRequest;
use App\Requests\V1\UpdateUserRequest;
use App\Requests\V1\FilterUserRequest;
use Illuminate\Http\Request;
use App\Filters\V1\UserFilter;
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
     * Retrieve a paginated list of users with advanced filtering
     *
     * Get a comprehensive paginated list of all users in the system with advanced filtering capabilities.
     * This endpoint provides administrators with complete user information including personal details,
     * addresses, role assignments, vendor associations, and account status. Essential for user management,
     * administrative oversight, customer service, and system analytics. Supports powerful filtering and
     * search functionality to help administrators quickly find specific users or user groups.
     *
     * @group User Management
     * @authenticated
     *
     * @queryParam filter array optional Array of filter parameters to narrow down results.
     * @queryParam filter[name] string optional Filter users by name (partial match supported). Example: john
     * @queryParam filter[email] string optional Filter users by email address (partial match supported). Example: @gmail.com
     * @queryParam filter[search] string optional Search across user names and email addresses. Example: sarah johnson
     * @queryParam filter[role] string optional Filter by role ID(s). Single ID or comma-separated multiple IDs. Example: 3,5,7
     * @queryParam filter[created_at] string optional Filter by registration date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2025-01-01,2025-01-31
     * @queryParam filter[updated_at] string optional Filter by last update date. Single date or comma-separated date range (YYYY-MM-DD). Example: 2024-12-01,2025-01-31
     * @queryParam filter[include] string optional Include related data. Options: role,vendors,userAddress. Example: role,vendors,userAddress
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of users per page (max 100). Default: 15. Example: 25
     * @queryParam sort string optional Sort users. Prefix with '-' for descending. Available: name, email, created_at, updated_at. Example: -created_at,name
     *
     * @response 200 scenario="Users retrieved successfully" {
     *     "message": "Users retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 123,
     *                 "name": "Sarah Johnson",
     *                 "email": "sarah.johnson@example.com",
     *                 "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *                 "stripe_customer_id": "cus_1234567890abcdef",
     *                 "password_changed_at": "2025-01-01T12:00:00.000000Z",
     *                 "last_login_at": "2025-01-16T09:15:00.000000Z",
     *                 "last_login_ip": "192.168.1.100",
     *                 "created_at": "2024-12-15T10:30:00.000000Z",
     *                 "updated_at": "2025-01-16T09:15:00.000000Z",
     *                 "deleted_at": null,
     *                 "user_address": {
     *                     "id": 45,
     *                     "address_line1": "123 Oak Street",
     *                     "address_line2": "Apartment 4B",
     *                     "city": "London",
     *                     "state": "England",
     *                     "country": "United Kingdom",
     *                     "postal_code": "SW1A 1AA"
     *                 },
     *                 "role": {
     *                     "id": 6,
     *                     "name": "user"
     *                 },
     *                 "vendors": [
     *                     {
     *                         "id": 12,
     *                         "name": "Sarah's Boutique",
     *                         "description": "Handcrafted jewelry and accessories",
     *                         "logo": "https://yourapi.com/storage/vendor-logos/sarahs-boutique.jpg",
     *                         "created_at": "2025-01-05T14:20:00.000000Z"
     *                     }
     *                 ]
     *             },
     *             {
     *                 "id": 89,
     *                 "name": "Michael Chen",
     *                 "email": "michael.chen@example.com",
     *                 "email_verified_at": "2025-01-08T12:00:00.000000Z",
     *                 "stripe_customer_id": "cus_9876543210fedcba",
     *                 "password_changed_at": "2024-12-20T15:30:00.000000Z",
     *                 "last_login_at": "2025-01-15T14:45:00.000000Z",
     *                 "last_login_ip": "203.0.113.42",
     *                 "created_at": "2024-11-20T16:45:00.000000Z",
     *                 "updated_at": "2025-01-15T14:45:00.000000Z",
     *                 "deleted_at": null,
     *                 "user_address": {
     *                     "id": 67,
     *                     "address_line1": "456 Pine Avenue",
     *                     "address_line2": null,
     *                     "city": "Manchester",
     *                     "state": "Greater Manchester",
     *                     "country": "United Kingdom",
     *                     "postal_code": "M1 1AA"
     *                 },
     *                 "role": {
     *                     "id": 3,
     *                     "name": "manager"
     *                 },
     *                 "vendors": []
     *             },
     *             {
     *                 "id": 45,
     *                 "name": "Emma Wilson",
     *                 "email": "emma.wilson@example.com",
     *                 "email_verified_at": "2025-01-05T14:00:00.000000Z",
     *                 "stripe_customer_id": null,
     *                 "password_changed_at": "2025-01-05T14:15:00.000000Z",
     *                 "last_login_at": "2025-01-14T11:20:00.000000Z",
     *                 "last_login_ip": "198.51.100.23",
     *                 "created_at": "2025-01-05T14:00:00.000000Z",
     *                 "updated_at": "2025-01-14T11:20:00.000000Z",
     *                 "deleted_at": null,
     *                 "user_address": null,
     *                 "role": {
     *                     "id": 4,
     *                     "name": "customer_service"
     *                 },
     *                 "vendors": []
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 1247,
     *         "last_page": 84,
     *         "from": 1,
     *         "to": 15,
     *         "path": "https://yourapi.com/api/v1/admin/users",
     *         "first_page_url": "https://yourapi.com/api/v1/admin/users?page=1",
     *         "last_page_url": "https://yourapi.com/api/v1/admin/users?page=84",
     *         "next_page_url": "https://yourapi.com/api/v1/admin/users?page=2",
     *         "prev_page_url": null
     *     }
     * }
     *
     * @response 200 scenario="Filtered users by role" {
     *     "message": "Users retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 67,
     *                 "name": "David Rodriguez",
     *                 "email": "david@techcorp.com",
     *                 "role": {
     *                     "id": 2,
     *                     "name": "admin"
     *                 },
     *                 "vendors": [],
     *                 "last_login_at": "2025-01-16T08:30:00.000000Z"
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 15,
     *         "total": 8,
     *         "last_page": 1
     *     }
     * }
     *
     * @response 200 scenario="No users found" {
     *     "message": "Users retrieved successfully.",
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
     *         "The filter.email must be a valid email format.",
     *         "The filter.role field must contain only comma-separated numbers.",
     *         "The filter.created_at field must be a valid date or date range in YYYY-MM-DD format."
     *     ]
     * }
     */
    public function index(FilterUserRequest $request, UserFilter $filter)
    {
        try {
            return $this->user->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new user account
     *
     * Create a new user account with complete profile information including personal details, address,
     * and role assignment. This endpoint allows administrators to onboard new users directly into the
     * system with predefined access levels. The created user will receive appropriate notifications
     * and can immediately begin using the system with their assigned role permissions.
     *
     * @group User Management
     * @authenticated
     *
     * @bodyParam name string required The user's full name. Will be automatically formatted with proper capitalization. Example: Sarah Jane Johnson
     * @bodyParam email string required The user's email address. Must be unique across all users. Example: sarah.johnson@example.com
     * @bodyParam password string required The user's initial password. Must meet security requirements (minimum 8 characters). Example: SecurePassword123!
     * @bodyParam role_id integer required The role ID to assign to the user. Must be a valid role that exists in the system. Example: 6
     * @bodyParam address object required Complete address information for the user.
     * @bodyParam address.address_line1 string required Primary address line. Example: 123 Oak Street
     * @bodyParam address.address_line2 string optional Secondary address line (apartment, suite, etc.). Example: Apartment 4B
     * @bodyParam address.city string required City name. Example: London
     * @bodyParam address.state string optional State, region, or county. Example: England
     * @bodyParam address.country string required Country name. Example: United Kingdom
     * @bodyParam address.postal_code string required Postal or ZIP code. Example: SW1A 1AA
     *
     * @response 200 scenario="User created successfully" {
     *     "message": "User created successfully!",
     *     "data": {
     *         "id": 156,
     *         "name": "Sarah Jane Johnson",
     *         "email": "sarah.johnson@example.com",
     *         "email_verified_at": null,
     *         "stripe_customer_id": null,
     *         "password_changed_at": null,
     *         "last_login_at": null,
     *         "last_login_ip": null,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z",
     *         "deleted_at": null,
     *         "user_address": {
     *             "id": 89,
     *             "address_line1": "123 Oak Street",
     *             "address_line2": "Apartment 4B",
     *             "city": "London",
     *             "state": "England",
     *             "country": "United Kingdom",
     *             "postal_code": "SW1A 1AA"
     *         },
     *         "role": {
     *             "id": 6,
     *             "name": "user"
     *         },
     *         "vendors": []
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The name field is required.",
     *         "The email has already been taken.",
     *         "The password must be at least 8 characters.",
     *         "The role id must exist in roles table.",
     *         "The address.address_line1 field is required.",
     *         "The address.city field is required.",
     *         "The address.country field is required.",
     *         "The address.postal_code field is required."
     *     ]
     * }
     *
     * @response 422 scenario="Duplicate email address" {
     *     "errors": [
     *         "The email has already been taken."
     *     ]
     * }
     *
     * @response 422 scenario="Invalid role assignment" {
     *     "errors": [
     *         "The selected role id is invalid."
     *     ]
     * }
     */
    public function store(StoreUserRequest $request)
    {
        try {
            return $this->user->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific user's detailed information
     *
     * Get comprehensive information about a specific user including personal details, address,
     * role assignments, vendor associations, and account status. This endpoint provides administrators
     * with complete visibility into user accounts for customer service, account management, and
     * administrative oversight purposes.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam user integer required The ID of the user to retrieve. Example: 123
     *
     * @response 200 scenario="User details retrieved successfully" {
     *     "message": "User details retrieved.",
     *     "data": {
     *         "id": 123,
     *         "name": "Sarah Johnson",
     *         "email": "sarah.johnson@example.com",
     *         "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *         "stripe_customer_id": "cus_1234567890abcdef",
     *         "password_changed_at": "2025-01-01T12:00:00.000000Z",
     *         "last_login_at": "2025-01-16T09:15:00.000000Z",
     *         "last_login_ip": "192.168.1.100",
     *         "created_at": "2024-12-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-16T09:15:00.000000Z",
     *         "deleted_at": null,
     *         "user_address": {
     *             "id": 45,
     *             "address_line1": "123 Oak Street",
     *             "address_line2": "Apartment 4B",
     *             "city": "London",
     *             "state": "England",
     *             "country": "United Kingdom",
     *             "postal_code": "SW1A 1AA"
     *         },
     *         "role": {
     *             "id": 6,
     *             "name": "user"
     *         },
     *         "vendors": [
     *             {
     *                 "id": 12,
     *                 "name": "Sarah's Boutique",
     *                 "description": "Handcrafted jewelry and accessories",
     *                 "logo": "https://yourapi.com/storage/vendor-logos/sarahs-boutique.jpg",
     *                 "products_count": 23,
     *                 "created_at": "2025-01-05T14:20:00.000000Z",
     *                 "updated_at": "2025-01-15T16:45:00.000000Z"
     *             }
     *         ]
     *     }
     * }
     *
     * @response 200 scenario="User without address or vendors" {
     *     "message": "User details retrieved.",
     *     "data": {
     *         "id": 89,
     *         "name": "John Doe",
     *         "email": "john.doe@example.com",
     *         "email_verified_at": "2025-01-12T10:00:00.000000Z",
     *         "stripe_customer_id": null,
     *         "last_login_at": "2025-01-15T16:30:00.000000Z",
     *         "user_address": null,
     *         "role": {
     *             "id": 6,
     *             "name": "user"
     *         },
     *         "vendors": []
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="User not found" {
     *     "message": "No query results for model [App\\Models\\User] 999"
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
     * Update an existing user's information
     *
     * Modify an existing user's profile information including personal details, address, role assignment,
     * and password. This endpoint supports partial updates - only provided fields will be updated, others
     * remain unchanged. Administrators can update any user's information including role changes and
     * password resets for customer service and account management purposes.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam user integer required The ID of the user to update. Example: 123
     *
     * @bodyParam name string optional The user's updated full name. Will be automatically formatted with proper capitalization. Example: Sarah Jane Johnson-Smith
     * @bodyParam email string optional The user's updated email address. Must be unique across all users. Example: sarah.johnson.new@example.com
     * @bodyParam password string optional New password for the user. Must be at least 8 characters. Useful for admin password resets. Example: NewSecurePassword456!
     * @bodyParam role_id integer optional Updated role ID for the user. Must be a valid role that exists in the system. Example: 3
     * @bodyParam address object optional Updated address information. If provided, all address fields are required.
     * @bodyParam address.address_line1 string required Primary address line when address is provided. Example: 456 Maple Avenue
     * @bodyParam address.address_line2 string optional Secondary address line. Example: Suite 201
     * @bodyParam address.city string required City name when address is provided. Example: Manchester
     * @bodyParam address.state string optional State, region, or county. Example: Greater Manchester
     * @bodyParam address.country string required Country name when address is provided. Example: United Kingdom
     * @bodyParam address.postal_code string required Postal or ZIP code when address is provided. Example: M1 1AA
     *
     * @response 200 scenario="User updated successfully" {
     *     "message": "User updated successfully.",
     *     "data": {
     *         "id": 123,
     *         "name": "Sarah Jane Johnson-Smith",
     *         "email": "sarah.johnson.new@example.com",
     *         "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *         "stripe_customer_id": "cus_1234567890abcdef",
     *         "password_changed_at": "2025-01-16T15:30:00.000000Z",
     *         "last_login_at": "2025-01-16T09:15:00.000000Z",
     *         "last_login_ip": "192.168.1.100",
     *         "created_at": "2024-12-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-16T15:30:00.000000Z",
     *         "deleted_at": null,
     *         "user_address": {
     *             "id": 45,
     *             "address_line1": "456 Maple Avenue",
     *             "address_line2": "Suite 201",
     *             "city": "Manchester",
     *             "state": "Greater Manchester",
     *             "country": "United Kingdom",
     *             "postal_code": "M1 1AA"
     *         },
     *         "role": {
     *             "id": 3,
     *             "name": "manager"
     *         },
     *         "vendors": [
     *             {
     *                 "id": 12,
     *                 "name": "Sarah's Boutique",
     *                 "description": "Handcrafted jewelry and accessories"
     *             }
     *         ]
     *     }
     * }
     *
     * @response 200 scenario="Partial update (role change only)" {
     *     "message": "User updated successfully.",
     *     "data": {
     *         "id": 123,
     *         "name": "Sarah Johnson",
     *         "email": "sarah.johnson@example.com",
     *         "role": {
     *             "id": 4,
     *             "name": "customer_service"
     *         },
     *         "updated_at": "2025-01-16T15:45:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="User not found" {
     *     "message": "No query results for model [App\\Models\\User] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *     "errors": [
     *         "The email has already been taken.",
     *         "The password must be at least 8 characters.",
     *         "The role id must exist in roles table.",
     *         "The address.address_line1 field is required when address is present.",
     *         "The address.city field is required when address is present.",
     *         "The address.country field is required when address is present.",
     *         "The address.postal_code field is required when address is present."
     *     ]
     * }
     *
     * @response 422 scenario="Duplicate email address" {
     *     "errors": [
     *         "The email has already been taken."
     *     ]
     * }
     *
     * @response 422 scenario="Invalid role assignment" {
     *     "errors": [
     *         "The selected role id is invalid."
     *     ]
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

    /**
     * Permanently delete a user account
     *
     * Permanently remove a user account from the system along with their associated address information.
     * This action is irreversible and will completely remove the user's data from the database.
     * Exercise extreme caution when deleting users as this may affect order history, vendor relationships,
     * and system audit trails. Consider deactivating accounts instead of deletion for data integrity.
     *
     * **Warning**: This permanently deletes all user data including address information. Order history
     * and other related records may be affected. Ensure this action is intentional and authorized.
     *
     * @group User Management
     * @authenticated
     *
     * @urlParam user integer required The ID of the user to permanently delete. Example: 123
     *
     * @response 200 scenario="User deleted successfully" {
     *     "message": "User deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="User not found" {
     *     "message": "No query results for model [App\\Models\\User] 999"
     * }
     *
     * @response 409 scenario="User has dependencies" {
     *     "message": "Cannot delete user with active orders or vendor associations. Please transfer or resolve dependencies first."
     * }
     *
     * @response 422 scenario="Protected user account" {
     *     "message": "Cannot delete system administrator accounts or users with special privileges."
     * }
     *
     * @response 422 scenario="User has active sessions" {
     *     "message": "User has active sessions. Please revoke all tokens and sessions before deletion."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *     "message": "An error occurred while deleting the user account."
     * }
     */
    public function destroy(Request $request, DB $user)
    {
        try {
            return $this->user->delete($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
