# E-Commerce API

A comprehensive Laravel-based e-commerce API with advanced security features, multi-vendor support, inventory management, and payment processing.

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Table of Contents

- [Features](#-features)
- [Architecture](#-architecture)
- [Quick Start](#-quick-start)
- [API Documentation](#-api-documentation)
- [Authentication](#-authentication)
- [User Roles](#-user-roles)
- [Workflows](#-typical-user-workflows)
- [Configuration](#-configuration)
- [Testing](#-testing)
- [Project Structure](#-project-structure)
- [Security](#-security-considerations)
- [Contributing](#-contributing)
- [Support](#-support)

## 🚀 Features

### 🔐 Authentication & Security
- **Laravel Passport** OAuth2 authentication
- **Account lockout protection** after failed login attempts
- **Password strength validation** with history tracking
- **Email verification** with secure links
- **Rate limiting** with dynamic thresholds
- **Security monitoring** and logging
- **CSRF protection** and security headers

### 🛍️ Product Management
- **Multi-vendor marketplace** support
- **Product variants** (size, color, material, etc.)
- **Category hierarchy** with unlimited nesting
- **Product tags** for enhanced searchability
- **Media management** with image optimization
- **Inventory tracking** with low stock alerts
- **Advanced search** with relevance scoring and faceted filtering

### 📦 Order Processing
- **Complete order lifecycle** management
- **Order status tracking** with automated updates
- **Order items** with variant support
- **Soft deletion** with restore capabilities

### 💳 Payment Integration
- **Stripe payment processing** with webhooks
- **Payment verification** and status tracking
- **Multiple payment methods** support
- **Secure payment intent handling**

### 🔄 Returns & Refunds
- **Customer return requests** with reason tracking
- **Admin review workflow** (approve/reject/review)
- **Automated refund processing** via payment gateways
- **Comprehensive audit trails**

### 🛒 Shopping Cart
- **Persistent shopping carts** for authenticated users
- **Price synchronization** when product prices change
- **Quantity management** with stock validation
- **Cart expiration** with cleanup

### 👥 User & Role Management
- **Role-based access control** (RBAC)
- **8 predefined roles** from Guest to Super Admin
- **Granular permissions** system
- **User profiles** with address management

### 📊 Inventory Management
- **Real-time stock tracking**
- **Low stock threshold alerts**
- **Bulk inventory updates**
- **Inventory overview dashboard**

### ⭐ Review & Rating System
- **Customer product reviews** with 1-5 star ratings
- **Verified purchase badges** for authentic reviews
- **Review helpfulness voting** system
- **Media attachments** (photos/videos) in reviews
- **Review moderation** and reporting system
- **Vendor response system** to customer reviews
- **Review analytics** and performance metrics
- **Bulk moderation tools** for administrators

### 🚚 Advanced Shipping System
- **Multi-zone shipping** with country/postcode targeting
- **Dynamic shipping rates** based on weight, value, and destination
- **Multiple shipping methods** per zone (Standard, Express, Overnight)
- **Real-time shipping calculations** with cart integration
- **Shipment tracking** with carrier integration
- **Shipping address management** with validation
- **Free shipping thresholds** and promotional rates
- **Bulk shipping operations** for administrators

### 🏢 Dropshipping & Supplier Management
- **Comprehensive supplier network** with API integrations
- **Product mapping** between suppliers and marketplace
- **Automated inventory synchronization** from suppliers
- **Dropship order fulfillment** with tracking
- **Supplier performance analytics** and metrics
- **Profit margin management** with dynamic pricing
- **Multi-supplier support** per product
- **Vendor dropshipping dashboard** with read-only access

## 🏗️ Architecture

### Tech Stack
- **Framework:** Laravel 11
- **Authentication:** Laravel Passport (OAuth2)
- **Database:** MySQL/PostgreSQL with full-text search
- **File Storage:** Local/S3 with Spatie Media Library
- **Search:** MySQL Full-Text with intelligent ranking
- **Queue:** Database/Redis for background processing
- **Cache:** Database/Redis for performance optimization

### Security Features
- **Secure file uploads** with MIME type validation
- **SQL injection protection** with Eloquent ORM
- **XSS protection** with input sanitization
- **CSRF protection** on all state-changing operations
- **Security headers** (HSTS, CSP, X-Frame-Options, etc.)
- **Account lockout** after multiple failed attempts
- **Password expiry** and history tracking

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+ or PostgreSQL 13+
- Node.js 18+ (for documentation generation)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd api
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure environment variables**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ecommerce_api
   DB_USERNAME=root
   DB_PASSWORD=

   # Laravel Passport (for auth)
   PASSPORT_PERSONAL_ACCESS_CLIENT_ID=
   PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=
   
   # Stripe (for payments)
   STRIPE_SECRET_KEY=
   STRIPE_PUBLIC_KEY=
   STRIPE_WEBHOOK_SECRET_KEY=
   
   # Shippo (for shipping integration)
   SHIPPO_API_KEY=
   SHIPPO_ENVIRONMENT=
   
   # Digital Downloads (NEW)
   DIGITAL_DOWNLOAD_MAX_FILE_SIZE=104857600  # 100MB in bytes
   DIGITAL_DOWNLOAD_ALLOWED_EXTENSIONS=zip,exe,dmg,pkg,pdf,epub,mp4,mov
   DIGITAL_DOWNLOAD_DEFAULT_EXPIRY_DAYS=365
   DIGITAL_DOWNLOAD_DEFAULT_LIMIT=3
   DIGITAL_DOWNLOAD_STORAGE_DISK=local  # or s3 for production

   # License Management (NEW)
   LICENSE_KEY_LENGTH=32
   LICENSE_KEY_FORMAT=XXXX-XXXX-XXXX-XXXX
   DEFAULT_LICENSE_ACTIVATIONS=1

   # Mail configuration
   MAIL_MAILER=smtp
   MAIL_HOST=mailhog
   MAIL_PORT=1025
   MAIL_USERNAME=null
   MAIL_PASSWORD=null
   ```

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Passport setup**
   ```bash
   php artisan passport:install
   ```

7. **Storage link**
   ```bash
   php artisan storage:link
   ```

8. **Start the server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

## 📖 API Documentation

### Generate Documentation
```bash
php artisan scribe:generate
```
📖 **Access documentation at:** `http://localhost:8000/docs`

### Key Endpoints

#### Authentication
- `POST /api/v1/register` - User registration
- `POST /api/v1/login` - User login
- `POST /api/v1/logout` - User logout
- `POST /api/v1/change-password` - Change password
- `POST /api/v1/forgot-password` - Request password reset
- `POST /api/v1/reset-password` - Reset password with token
- `GET /api/v1/security-info` - Get user security information

#### Email Verification
- `GET /api/v1/email/verify/{id}/{hash}` - Verify email address
- `GET /api/v1/email/resend` - Resend verification email

#### Products
- `GET /api/v1/products` - Browse products with advanced filtering
- `GET /api/v1/products/{id}` - Product details

#### Cart
- `GET /api/v1/cart` - View cart
- `POST /api/v1/cart/items` - Add to cart
- `POST /api/v1/cart/items/{id}` - Update cart item
- `DELETE /api/v1/cart/items/{id}` - Remove from cart
- `DELETE /api/v1/cart/clear` - Clear entire cart
- `POST /api/v1/cart/sync-prices` - Sync cart prices with current product prices

#### Orders
- `GET /api/v1/orders` - User's orders
- `GET /api/v1/orders/{id}` - Order details
- `POST /api/v1/orders/from-cart` - Create order from cart

#### Payments
- `POST /api/v1/payments/{gateway}/create` - Create payment intent
- `POST /api/v1/payments/{gateway}/verify` - Verify payment
- `POST /api/v1/payments/stripe/webhook` - Stripe webhook handler

#### Returns
- `POST /api/v1/returns` - Create return request

#### Reviews & Ratings
- `GET /api/v1/products/{product}/reviews` - Get product reviews
- `GET /api/v1/reviews/{review}` - Get specific review
- `POST /api/v1/reviews` - Create product review
- `POST /api/v1/reviews/{review}` - Update review
- `DELETE /api/v1/reviews/{review}` - Delete review
- `POST /api/v1/reviews/{review}/helpfulness` - Vote on review helpfulness
- `POST /api/v1/reviews/{review}/report` - Report inappropriate review

#### Review Responses (Vendor)
- `GET /api/v1/reviews/{review}/responses` - Get vendor responses to review
- `POST /api/v1/reviews/{review}/responses` - Create vendor response
- `GET /api/v1/vendor/responses` - Get vendor's responses dashboard
- `GET /api/v1/vendor/unanswered-reviews` - Get reviews needing vendor response

#### Shipping Addresses
- `GET /api/v1/shipping-addresses` - Get user's shipping addresses
- `POST /api/v1/shipping-addresses` - Add new shipping address
- `PUT /api/v1/shipping-addresses/{id}` - Update shipping address
- `DELETE /api/v1/shipping-addresses/{id}` - Delete shipping address
- `PATCH /api/v1/shipping-addresses/{id}/set-default` - Set as default address
- `POST /api/v1/shipping-addresses/{id}/validate` - Validate address

#### Shipping Calculations
- `POST /api/v1/shipping/cart/quote` - Get shipping quote for cart
- `POST /api/v1/shipping/products/quote` - Get shipping quote for products
- `POST /api/v1/shipping/estimate` - Get quick shipping estimate (public)
- `POST /api/v1/shipping/cheapest` - Get cheapest shipping option
- `POST /api/v1/shipping/fastest` - Get fastest shipping option
- `POST /api/v1/shipping/validate-method` - Validate shipping method

#### Shipment Tracking
- `GET /api/v1/tracking/{trackingNumber}` - Track shipment (public)
- `GET /api/v1/tracking/{trackingNumber}/status` - Get shipment status
- `GET /api/v1/my-shipments` - Get user's shipments
- `GET /api/v1/my-shipments/{id}` - Get specific shipment

#### Checkout
- `POST /api/v1/checkout/summary` - Get checkout summary
- `POST /api/v1/checkout/validate` - Validate checkout data

#### Vendor Dropshipping (Read-Only)
- `GET /api/v1/vendor/dropshipping/dashboard` - Get dropshipping dashboard
- `GET /api/v1/vendor/dropshipping/orders` - Get dropship orders
- `GET /api/v1/vendor/dropshipping/suppliers` - Get available suppliers
- `GET /api/v1/vendor/dropshipping/supplier-products` - Get supplier products
- `GET /api/v1/vendor/dropshipping/product-mappings` - Get product mappings
- `GET /api/v1/vendor/dropshipping/analytics` - Get dropshipping analytics
- `GET /api/v1/vendor/dropshipping/profit-margins` - Get profit margin analysis
- `GET /api/v1/vendor/dropshipping/supplier-performance` - Get supplier performance metrics

#### Digital Products & Downloads (NEW)

- `GET /api/v1/my-digital-products` - User's digital library
- `GET /api/v1/my-digital-products/downloads` - Download access management
- `GET /api/v1/my-digital-products/licenses` - License key management
- `GET /api/v1/my-digital-products/statistics` - User download statistics

#### Secure Digital Downloads (NEW)

- GET `/api/v1/digital/download/{token}` - Secure file download
- GET `/api/v1/digital/download/{token}/info` - Download access information
- POST `/api/v1/digital/download/{token}/progress/{attemptId}` - Update download progress

#### License Validation (NEW)

- POST `/api/v1/license/validate` - Validate license key
- POST `/api/v1/license/activate` - Activate license
- POST `/api/v1/license/deactivate` - Deactivate license

### Admin Endpoints
All admin endpoints require `super_admin` or `admin` role:

#### User & Role Management
- `GET /api/v1/admin/users` - Manage users
- `POST /api/v1/admin/users` - Create user
- `GET /api/v1/admin/roles` - Manage roles (super_admin only)
- `GET /api/v1/admin/permissions` - Manage permissions (super_admin only)
- `POST /api/v1/admin/roles/{role}/permissions` - Assign permissions to role

#### Vendor Management
- `GET /api/v1/admin/vendors` - Manage vendors
- `POST /api/v1/admin/vendors` - Create vendor
- `PUT /api/v1/admin/vendors/{id}` - Update vendor
- `DELETE /api/v1/admin/vendors/{id}` - Delete vendor

#### Product Management
- `GET /api/v1/admin/products` - Manage products
- `POST /api/v1/admin/products` - Create product
- `PUT /api/v1/admin/products/{id}` - Update product
- `DELETE /api/v1/admin/products/{id}` - Delete product
- `PATCH /api/v1/admin/products/{id}/restore` - Restore soft-deleted product

#### Product Categories & Attributes
- `GET /api/v1/admin/product-categories` - Manage categories
- `GET /api/v1/admin/product-attributes` - Manage attributes
- `GET /api/v1/admin/product-tags` - Manage tags

#### Order & Payment Management
- `GET /api/v1/admin/orders` - Manage orders
- `PUT /api/v1/admin/orders/{id}` - Update order
- `GET /api/v1/admin/payments` - View all payments
- `GET /api/v1/admin/payment-methods` - Manage payment methods

#### Returns & Refunds
- `GET /api/v1/admin/returns` - Manage return requests
- `POST /api/v1/admin/returns/{id}/{action}` - Review return (approve/reject)
- `GET /api/v1/admin/refunds` - View all refunds
- `POST /api/v1/admin/refunds/{gateway}/{id}` - Process refund

#### Inventory Management
- `GET /api/v1/admin/inventory/overview` - Inventory dashboard
- `POST /api/v1/admin/inventory/products/{id}/threshold` - Update stock threshold
- `POST /api/v1/admin/inventory/check` - Manual inventory check
- `POST /api/v1/admin/inventory/bulk-update-thresholds` - Bulk update thresholds

#### Review Management
- `GET /api/v1/admin/reviews` - Manage all reviews
- `GET /api/v1/admin/reviews/reports` - Get reported reviews
- `GET /api/v1/admin/reviews/analytics` - Review analytics
- `POST /api/v1/admin/reviews/{id}/moderate` - Moderate review
- `POST /api/v1/admin/reviews/bulk-moderate` - Bulk moderate reviews
- `GET /api/v1/admin/review-responses` - Manage vendor responses
- `POST /api/v1/admin/review-responses/{id}/approve` - Approve vendor response

#### Shipping Management
- `GET /api/v1/admin/shipping-methods` - Manage shipping methods
- `POST /api/v1/admin/shipping-methods` - Create shipping method
- `GET /api/v1/admin/shipping-zones` - Manage shipping zones
- `POST /api/v1/admin/shipping-zones` - Create shipping zone
- `GET /api/v1/admin/shipping-rates` - Manage shipping rates
- `POST /api/v1/admin/shipping-rates/bulk-create` - Bulk create rates
- `GET /api/v1/admin/shipments` - Manage shipments
- `POST /api/v1/admin/shipments/{id}/mark-shipped` - Mark shipment as shipped
- `GET /api/v1/admin/shipments/stats` - Get shipment statistics

#### Supplier & Dropshipping Management
- `GET /api/v1/admin/suppliers` - Manage suppliers
- `POST /api/v1/admin/suppliers` - Create supplier
- `POST /api/v1/admin/suppliers/{id}/test-connection` - Test supplier connection
- `GET /api/v1/admin/supplier-products` - Manage supplier products
- `POST /api/v1/admin/supplier-products/suppliers/{id}/sync` - Sync from supplier
- `GET /api/v1/admin/supplier-integrations` - Manage integrations
- `POST /api/v1/admin/supplier-integrations/{id}/sync` - Trigger sync
- `GET /api/v1/admin/product-supplier-mappings` - Manage product mappings
- `POST /api/v1/admin/product-supplier-mappings/bulk-sync-prices` - Bulk sync prices
- `GET /api/v1/admin/dropship-orders` - Manage dropship orders
- `POST /api/v1/admin/dropship-orders/{id}/send-to-supplier` - Send to supplier
- `GET /api/v1/admin/dropship-orders/stats` - Get dropship statistics

#### Digital Product Management (NEW)
- GET `/api/v1/admin/digital-products` - Manage digital products
- GET `/api/v1/admin/digital-products/{product}` - Digital product details
- GET `/api/v1/admin/digital-products/statistics` - Analytics dashboard
- POST `/api/v1/admin/digital-products/cleanup` - Cleanup expired access
- GET `/api/v1/admin/digital-library/users/{user}` - User's digital library (admin)

#### Product File Management (NEW)
- GET `/api/v1/admin/products/{product}/files` - Manage product files
- POST `/api/v1/admin/products/{product}/files` - Upload product files
- POST `/api/v1/admin/products/{product}/files/bulk` - Bulk file upload
- GET `/api/v1/admin/products/{product}/files/{file}/download` - Admin file download

## 🔐 Authentication

### Getting an Access Token
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password"
  }'
```

**Response:**
```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "expires_at": 1640995200,
    "user": {
      "id": 1,
      "email": "test@example.com",
      "role": "super_admin"
    }
  }
}
```

### Using the Token
```bash
curl -X GET http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## 👥 User Roles

| Role | Description | Key Permissions |
|------|-------------|----------------|
| **Super Admin** | Full system access | All permissions |
| **Admin** | Administrative access | User, vendor, product, order management |
| **Vendor Manager** | Multi-vendor oversight | Vendor management, order viewing |
| **Vendor** | Single vendor access | Own products and vendor profile |
| **Customer Service** | Support team | Customer data, refunds, returns |
| **Content Manager** | Content management | Product content, categories |
| **User** | Regular customer | Own profile, orders, cart |
| **Guest** | Browse-only access | View products and public content |

## 🛒 Typical User Workflows

### Customer Journey
1. **Browse** products (no auth required)
2. **Register/Login** to create account
3. **Add items** to cart
4. **Calculate shipping** and select method
5. **Add shipping address** and validate
6. **Checkout** and create order
7. **Make payment** via Stripe
8. **Track shipment** status
9. **Leave reviews** for purchased products
10. **Request returns** if needed

### Vendor Workflow
1. **Create vendor account** (admin approval may be required)
2. **Add products** with variants and media
3. **Manage inventory** and stock levels
4. **Set up dropshipping** with suppliers (optional)
5. **Process orders** for their products
6. **Respond to customer reviews**
7. **Handle returns** and customer service
8. **Monitor dropshipping analytics**

### Admin Workflow
1. **Monitor inventory** via dashboard
2. **Manage users** and role assignments
3. **Review returns** and process refunds
4. **Moderate reviews** and handle reports
5. **Configure shipping** zones and rates
6. **Manage suppliers** and integrations
7. **Oversee dropship orders** and fulfillment
8. **Analyze system** security and performance

### Dropshipping Workflow
1. **Configure suppliers** with API integrations
2. **Map supplier products** to marketplace
3. **Set profit margins** and pricing rules
4. **Sync inventory** automatically
5. **Process orders** through suppliers
6. **Track fulfillment** status
7. **Monitor supplier performance**
8. **Analyze profit margins**

## 🔧 Configuration

### Rate Limiting
Configure in `config/rate-limiting.php`:
```php
'auth' => [
    'login' => '5,1',           // 5 attempts per minute
    'register' => '3,1',        // 3 attempts per minute
],
'api' => [
    'general' => '60,1',        // 60 requests per minute
],
'reviews' => [
    'create' => '5,60',         // 5 reviews per hour
    'vote' => '10,5',           // 10 votes per 5 minutes
],
'shipping' => [
    'calculations' => '30,1',   // 30 calculations per minute
],
'dropshipping' => [
    'general' => '100,1',       // 100 requests per minute
],
```

### Search Configuration
Configure in `config/search.php`:
```php
'default_engine' => 'database',
'performance' => [
    'max_results_per_page' => 100,
    'enable_query_cache' => true,
],
```

### Security Settings
- Account lockout after 5 failed attempts
- Password expiry after 90 days
- Secure file upload validation
- Comprehensive security logging

### Review System Configuration
```php
'reviews' => [
    'max_media_files' => 5,
    'allowed_media_types' => ['image/jpeg', 'image/png', 'video/mp4'],
    'max_file_size' => 10485760, // 10MB
    'require_purchase' => true,
    'auto_approve' => true,
],
```

### Shipping Configuration
```php
'shipping' => [
    'default_handling_days' => 1,
    'max_package_weight' => 50, // kg
    'free_shipping_threshold' => 5000, // pennies (£50.00)
    'enable_address_validation' => true,
],
```

### Dropshipping Configuration
```php
'dropshipping' => [
    'auto_fulfill_enabled' => true,
    'default_markup_percentage' => 25.0,
    'min_profit_margin' => 10.0,
    'sync_frequency_minutes' => 60,
    'max_retry_attempts' => 3,
],
```

### Digital Product Configuration
```php
'cleanup' => [
    'expired_access_retention_days' => 30,
    'failed_attempt_retention_days' => 7,
],
'security' => [
    'enforce_ip_validation' => true,
    'max_concurrent_downloads' => 2,
    'download_timeout_minutes' => 60,
],
```

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run tests with coverage
php artisan test --coverage
```

### Sample Data
The seeder creates test data including:
- 1 Super Admin user (`test@example.com` / `password`)
- 20 regular users
- 10 vendors with products
- 100 orders with various statuses
- Product reviews and ratings
- Shipping zones and methods
- Supplier integrations
- Product categories and tags

### Test User Accounts
- **Super Admin:** `test@example.com` / `password`
- **Regular Users:** Random generated users
- **Vendors:** Associated with vendor accounts
- **Test Supplier:** API integration with test data

## 📁 Project Structure

```
api/
├── app/
│   ├── Console/Commands/        # Artisan commands
│   ├── Constants/               # Application constants
│   ├── Events/                  # Events
│   ├── Filters/V1/              # Query filters
│   ├── Http/
│   │   ├── Controllers/V1/      # API controllers
│   │   │   ├── Admin/           # Admin controllers
│   │   │   ├── Auth/            # Authentication controllers
│   │   │   └── Public/          # Public controllers
│   │   └── Middleware           # Custom middleware
│   ├── Jobs/                    # Jobs
│   ├── Listeners/               # Listeners
│   ├── Mail/                    # Mail
│   ├── Models/                  # Eloquent models
│   ├── Observers/               # Observers
│   ├── Providers/               # Providers
│   ├── Requests/V1/             # Requests
│   ├── Resources/V1/            # JSON Resources
│   ├── Services/V1/             # Business logic services
│   └── Traits/V1/               # Reusable traits
├── config/                      # Configuration files
├── database/
│   ├── factories/               # Model factories
│   ├── migrations/              # Database migrations
│   └── seeders/                 # Database seeders
└── routes/
    ├── api/v1/
    │   ├── admin/               # Admin routes
    │   └── public/              # Public routes
    └── console.php              # Scheduled commands
```

## 🚨 Security Considerations

### Production Deployment
1. **Environment Variables:** Never commit `.env` files
2. **HTTPS:** Always use SSL in production
3. **Database:** Use strong passwords and restricted access
4. **Stripe:** Use live keys only in production
5. **File Uploads:** Implement virus scanning
6. **Rate Limiting:** Adjust limits based on traffic
7. **Monitoring:** Set up error tracking and logging

### Security Features
- **Account lockout** prevents brute force attacks
- **Password policies** enforce strong passwords
- **File upload validation** prevents malicious files
- **SQL injection protection** via Eloquent ORM
- **XSS protection** through input sanitization
- **Smart throttling** for review and rating actions

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Write tests** for new functionality
4. **Commit** your changes (`git commit -m 'Add amazing feature'`)
5. **Push** to the branch (`git push origin feature/amazing-feature`)
6. **Open** a Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive tests
- Update documentation for new features
- Ensure all tests pass before submitting PR

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For issues and questions:

### Troubleshooting Steps
1. **Check the API documentation** at `/docs`
2. **Review error logs** in `storage/logs/`
3. **Check security logs** for authentication issues
4. **Verify environment configuration**
5. **Ensure database migrations** are up to date

### Getting Help
- 📖 **Documentation:** `http://localhost:8000/docs`
- 🐛 **Issues:** Create an issue on GitHub
- 💬 **Discussions:** Use GitHub Discussions for questions

### Common Issues

**Authentication Problems:**
```bash
# Clear authentication cache
php artisan auth:clear-resets
php artisan cache:clear
```

**Database Issues:**
```bash
# Reset and reseed database
php artisan migrate:fresh --seed
```

**Permission Errors:**
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
```

## 🔄 Maintenance

### Scheduled Tasks
The following commands should run automatically via Laravel's scheduler:

```bash
# Add to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Command Details
- **`passport:purge`** - Purges expired OAuth tokens (hourly)
- **`auth:clear-resets`** - Clears expired password reset tokens (every 15 minutes)
- **`auth:revoke-expired-tokens`** - Revokes expired authentication tokens (every 30 minutes)
- **`cleanup:expired-carts`** - Removes expired shopping carts (hourly)
- **`cleanup:empty-carts --days=7`** - Removes empty carts older than 7 days (daily at 2 AM)
- **`inventory:check`** - Checks inventory levels and sends low stock alerts (hourly)
- **`orders:process-overdue-shipments`** - Processes overdue shipments (hourly)

### Manual Maintenance Commands

**Authentication & Tokens:**
```bash
# Purge expired OAuth tokens
php artisan passport:purge

# Clear password reset tokens
php artisan auth:clear-resets

# Revoke expired tokens
php artisan auth:revoke-expired-tokens
```

**Cart Management:**
```bash
# Clean up expired carts
php artisan cleanup:expired-carts

# Clean up empty carts (older than 7 days)
php artisan cleanup:empty-carts --days=7
```

**Inventory:**
```bash
# Check inventory and send alerts
php artisan inventory:check
```

**Reviews & Ratings:**
```bash
# Recalculate product ratings
php artisan reviews:recalculate-ratings

# Clean up orphaned review media
php artisan reviews:cleanup-media
```

**Shipping & Orders:**
```bash
# Process overdue shipments
php artisan orders:process-overdue-shipments

# Update shipping rates from carriers
php artisan shipping:update-rates

# Sync tracking information
php artisan shipping:sync-tracking
```

**Dropshipping & Suppliers:**
```bash
# Sync all supplier products
php artisan suppliers:sync-all

# Check supplier API health
php artisan suppliers:health-check

# Process pending dropship orders
php artisan dropshipping:process-orders

# Update profit margins
php artisan dropshipping:update-margins
```

### Performance Optimization
```bash
# Optimize for production
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear all caches
php artisan optimize:clear
```

### Monitoring Checklist
- [ ] Track failed login attempts in security logs
- [ ] Monitor API response times and performance
- [ ] Check inventory alert notifications
- [ ] Review payment webhook failures
- [ ] Monitor queue job failures
- [ ] Check storage disk usage
- [ ] Verify backup integrity
- [ ] Monitor review moderation queue
- [ ] Check shipping rate accuracy
- [ ] Verify supplier API connections
- [ ] Monitor dropship order processing
- [ ] Check profit margin calculations
- [ ] Review cart abandonment rates
- [ ] Monitor shipment tracking updates

### Database Maintenance
```bash
# Optimize database tables
php artisan db:optimize

# Check for orphaned records
php artisan db:check-integrity

# Backup database
php artisan backup:run

# Restore from backup
php artisan backup:restore --backup=filename.sql
```

### File System Maintenance
```bash
# Clean up temporary files
php artisan files:cleanup-temp

# Optimize media files
php artisan media:optimize

# Generate missing thumbnails
php artisan media:regenerate

# Clean up orphaned media files
php artisan media:cleanup-orphaned
```

---

**Built with ❤️ using Laravel** | **Star ⭐ this repo if you find it helpful!**
