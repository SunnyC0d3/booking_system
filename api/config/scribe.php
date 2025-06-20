<?php

use Knuckles\Scribe\Extracting\Strategies;

return [
    'title' => 'E-Commerce API Documentation',
    'description' => 'Comprehensive API for managing products, orders, payments, users, vendors, and more in an e-commerce platform.',
    'base_url' => env('APP_URL'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
                'versions' => ['v1'],
            ],
            'include' => [],
            'exclude' => [
                'api/v1/payments/stripe/webhook',
            ],
        ],
    ],

    'type' => 'static',
    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => []
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => env('APP_URL'),
        'use_csrf' => false,
        'csrf_url' => null,
    ],

    'auth' => [
        'enabled' => true,
        'default' => true,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_AUTH_TOKEN}',
        'extra_info' => 'You can obtain your authentication token by logging in via the `/login` endpoint. The token should be included in the Authorization header as `Bearer {token}`.',
    ],

    'intro_text' => <<<INTRO
# Welcome to the E-Commerce API

This comprehensive API allows you to build powerful e-commerce applications with features including:

## 🔐 **Authentication & Security**
- User registration and login with Laravel Passport
- Account lockout protection after failed attempts
- Password validation and expiry policies
- Email verification and password reset

## 🛍️ **Product Management**
- Product catalog with categories, tags, and variants
- Media management for product images
- Inventory tracking and status management
- Vendor-specific product management

## 📦 **Order Processing**
- Complete order lifecycle management
- Order item tracking and management
- Order status updates and notifications

## 💳 **Payment Integration**
- Stripe payment processing
- Payment status tracking and webhooks
- Secure payment verification

## 🔄 **Returns & Refunds**
- Customer return request system
- Admin return review and approval
- Automated refund processing via payment gateways
- Comprehensive refund tracking

## 👥 **User & Vendor Management**
- Role-based access control
- User profile and address management
- Vendor account management

## 📊 **Security & Monitoring**
- Comprehensive security logging
- Account lock monitoring
- Failed attempt tracking
- Security score calculation

All endpoints follow RESTful conventions and return JSON responses. Most endpoints require authentication using Bearer tokens.
INTRO,

    'example_languages' => [
        'bash',
        'javascript',
        'php',
        'python'
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
        ],
    ],

    'openapi' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
            'info.contact' => [
                'name' => 'API Support',
                'email' => 'support@example.com'
            ],
        ],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Authentication',
            'Email Verification',
            'Password Management',
            'User Management',
            'Vendor Management',
            'Product Management',
            'Product Categories',
            'Product Attributes',
            'Product Tags',
            'Order Management',
            'Payment Processing',
            'Returns Management',
            'Refund Management',
            'Admin - Users',
            'Admin - Products',
            'Admin - Orders',
            'Admin - Payments',
            'Admin - Returns & Refunds',
            'Admin - System Management',
        ],
    ],

    'logo' => false,
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromDocBlocks::class,
            Strategies\Metadata\GetFromMetadataAttributes::class,
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromUrlParamAttribute::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamAttribute::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromHeaderAttribute::class,
            Strategies\Headers\GetFromHeaderTag::class,
            [
                'override',
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]
        ],
        'bodyParameters' => [
            Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamAttribute::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            Strategies\Responses\UseResponseAttributes::class,
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
            [
                Strategies\Responses\ResponseCalls::class,
                ['only' => ['GET *']]
            ]
        ],
        'responseFields' => [
            Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],

    'routeMatcher' => \Knuckles\Scribe\Matching\RouteMatcher::class,
];
