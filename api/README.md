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

   # Stripe (for payments)
   STRIPE_PUBLIC_KEY=pk_test_...
   STRIPE_SECRET_KEY=sk_test_...
   STRIPE_WEBHOOK_SECRET_KEY=whsec_...

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

#### Products
- `GET /api/v1/products` - Browse products with advanced filtering
- `GET /api/v1/products/{id}` - Product details

#### Cart
- `GET /api/v1/cart` - View cart
- `POST /api/v1/cart/items` - Add to cart
- `PUT /api/v1/cart/items/{id}` - Update cart item
- `DELETE /api/v1/cart/items/{id}` - Remove from cart

#### Orders
- `GET /api/v1/orders` - User's orders
- `GET /api/v1/orders/{id}` - Order details

#### Payments
- `POST /api/v1/payments/{gateway}/create` - Create payment intent
- `POST /api/v1/payments/{gateway}/verify` - Verify payment

#### Returns
- `POST /api/v1/returns` - Create return request

### Admin Endpoints
All admin endpoints require `super_admin` or `admin` role:

- `GET /api/v1/admin/users` - Manage users
- `GET /api/v1/admin/vendors` - Manage vendors
- `GET /api/v1/admin/products` - Manage products
- `GET /api/v1/admin/orders` - Manage orders
- `GET /api/v1/admin/returns` - Manage returns
- `GET /api/v1/admin/inventory/overview` - Inventory dashboard

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
4. **Checkout** and create order
5. **Make payment** via Stripe
6. **Track order** status
7. **Request returns** if needed

### Vendor Workflow
1. **Create vendor account** (admin approval may be required)
2. **Add products** with variants and media
3. **Manage inventory** and stock levels
4. **Process orders** for their products
5. **Handle returns** and customer service

### Admin Workflow
1. **Monitor inventory** via dashboard
2. **Manage users** and role assignments
3. **Review returns** and process refunds
4. **Oversee vendor** activities
5. **Analyze system** security and performance

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

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

### Sample Data
The seeder creates test data including:
- 1 Super Admin user (`test@example.com` / `password`)
- 20 regular users
- 10 vendors with products
- 100 orders with various statuses
- Product categories and tags

### Test User Accounts
- **Super Admin:** `test@example.com` / `password`
- **Regular Users:** Random generated users
- **Vendors:** Associated with vendor accounts

## 📁 Project Structure

```
api/
├── app/
│   ├── Console/Commands/         # Artisan commands
│   ├── Constants/               # Application constants
│   ├── Filters/V1/              # Query filters
│   ├── Http/
│   │   ├── Controllers/V1/      # API controllers
│   │   ├── Middleware/V1/       # Custom middleware
│   │   └── Requests/V1/         # Form requests
│   ├── Models/                  # Eloquent models
│   ├── Services/V1/             # Business logic services
│   └── Traits/V1/               # Reusable traits
├── config/                      # Configuration files
├── database/
│   ├── factories/               # Model factories
│   ├── migrations/              # Database migrations
│   └── seeders/                 # Database seeders
└── routes/api/v1/               # API routes
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

---

**Built with ❤️ using Laravel** | **Star ⭐ this repo if you find it helpful!**
