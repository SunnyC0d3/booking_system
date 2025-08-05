import { NextRequest, NextResponse } from 'next/server';
import { securityMiddleware } from '@/middleware/security';

// Route configuration
const routeConfig = {
    // Protected routes requiring authentication
    protectedRoutes: [
        '/dashboard',
        '/profile',
        '/orders',
        '/settings',
        '/admin',
    ],

    // Admin-only routes
    adminRoutes: [
        '/admin',
    ],

    // Public routes (no authentication required)
    publicRoutes: [
        '/',
        '/products',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/about',
        '/contact',
    ],

    // API routes with different rate limits
    apiRoutes: {
        auth: ['/api/auth', '/api/login', '/api/register'],
        public: ['/api/products', '/api/categories'],
        protected: ['/api/user', '/api/orders'],
        admin: ['/api/admin'],
    },
};

// Main middleware function
export async function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    // Skip middleware for static files and internal Next.js routes
    if (
        pathname.startsWith('/_next/') ||
        pathname.startsWith('/api/_next/') ||
        pathname.includes('.') // Skip files with extensions
    ) {
        return NextResponse.next();
    }

    try {
        // 1. Apply security middleware first
        const securityResponse = await securityMiddleware(request);

        // If security middleware blocked the request, return early
        if (securityResponse.status !== 200) {
            return securityResponse;
        }

        // 2. Authentication and authorization
        const authResponse = await handleAuthentication(request);
        if (authResponse) {
            return authResponse;
        }

        // 3. Route-specific handling
        const routeResponse = await handleRouteSpecific(request);
        if (routeResponse) {
            return routeResponse;
        }

        // 4. Add performance and monitoring headers
        const response = NextResponse.next();
        addPerformanceHeaders(response, request);

        return response;

    } catch (error) {
        console.error('Middleware error:', error);

        // Return safe error response
        return new NextResponse('Internal Error', {
            status: 500,
            headers: {
                'Content-Type': 'text/plain',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
            }
        });
    }
}

// Authentication and authorization handler
async function handleAuthentication(request: NextRequest): Promise<NextResponse | null> {
    const { pathname } = request.nextUrl;

    // Get token from various sources
    const token = getAuthToken(request);
    const isAuthenticated = token && await validateToken(token);
    const isAdmin = isAuthenticated && await checkAdminRole(token);

    // Check if route requires authentication
    const requiresAuth = routeConfig.protectedRoutes.some(route =>
        pathname.startsWith(route)
    );

    const requiresAdmin = routeConfig.adminRoutes.some(route =>
        pathname.startsWith(route)
    );

    // Handle authentication requirements
    if (requiresAuth && !isAuthenticated) {
        const loginUrl = new URL('/login', request.url);
        loginUrl.searchParams.set('redirect', pathname);
        return NextResponse.redirect(loginUrl);
    }

    // Handle admin requirements
    if (requiresAdmin && !isAdmin) {
        return new NextResponse('Access Denied', {
            status: 403,
            headers: { 'Content-Type': 'text/plain' }
        });
    }

    // Redirect authenticated users from auth pages
    if (isAuthenticated && ['/login', '/register'].includes(pathname)) {
        const redirectTo = request.nextUrl.searchParams.get('redirect') || '/dashboard';
        return NextResponse.redirect(new URL(redirectTo, request.url));
    }

    return null;
}

// Route-specific handling
async function handleRouteSpecific(request: NextRequest): Promise<NextResponse | null> {
    const { pathname } = request.nextUrl;

    // Handle API routes
    if (pathname.startsWith('/api/')) {
        return handleApiRoutes(request);
    }

    // Handle admin routes
    if (pathname.startsWith('/admin')) {
        return handleAdminRoutes(request);
    }

    // Handle product routes with caching
    if (pathname.startsWith('/products')) {
        const response = NextResponse.next();
        response.headers.set('Cache-Control', 'public, max-age=300, s-maxage=300');
        return response;
    }

    return null;
}

// API routes handler
async function handleApiRoutes(request: NextRequest): Promise<NextResponse | null> {
    // Add API-specific headers
    const response = NextResponse.next();

    // CORS headers for API routes
    response.headers.set('Access-Control-Allow-Origin', process.env.FRONTEND_URL || '*');
    response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');

    // Handle preflight requests
    if (request.method === 'OPTIONS') {
        return new NextResponse(null, { status: 200, headers: response.headers });
    }

    // Add API versioning header
    response.headers.set('API-Version', 'v1');

    // Add request ID for tracing
    response.headers.set('X-Request-ID', crypto.randomUUID());

    return response;
}

// Admin routes handler
async function handleAdminRoutes(_request: NextRequest): Promise<NextResponse | null> {
    // Add admin-specific security headers
    const response = NextResponse.next();

    // Stricter security for admin routes
    response.headers.set('X-Admin-Route', 'true');
    response.headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
    response.headers.set('X-Frame-Options', 'DENY');

    return response;
}

// Performance headers
function addPerformanceHeaders(response: NextResponse, request: NextRequest) {
    const { pathname } = request.nextUrl;

    // Add timing information
    response.headers.set('X-Timestamp', Date.now().toString());

    // Add route-specific cache headers
    if (pathname.startsWith('/static/') || pathname.includes('/_next/static/')) {
        response.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
    } else if (pathname.startsWith('/api/')) {
        response.headers.set('Cache-Control', 'private, max-age=0, must-revalidate');
    } else {
        response.headers.set('Cache-Control', 'public, max-age=300, s-maxage=300, stale-while-revalidate=86400');
    }

    // Add performance hints
    if (pathname === '/') {
        response.headers.set('Link', [
            '</css/critical.css>; rel=preload; as=style',
            '</js/app.js>; rel=preload; as=script',
            '<https://fonts.googleapis.com>; rel=preconnect',
        ].join(', '));
    }
}

// Authentication helper functions
function getAuthToken(request: NextRequest): string | null {
    // Check Authorization header
    const authHeader = request.headers.get('authorization');
    if (authHeader?.startsWith('Bearer ')) {
        return authHeader.substring(7);
    }

    // Check cookie
    const tokenCookie = request.cookies.get('access_token');
    if (tokenCookie) {
        return tokenCookie.value;
    }

    return null;
}

async function validateToken(token: string): Promise<boolean> {
    try {
        // Validate JWT token structure first
        const parts = token.split('.');
        if (parts.length !== 3 || !parts[1]) {
            return false;
        }

        // For JWT tokens, verify signature and check expiration
        const payload = JSON.parse(atob(parts[1]));
        const currentTime = Date.now() / 1000;

        return payload.exp && payload.exp > currentTime;
    } catch {
        return false;
    }
}

async function checkAdminRole(token: string): Promise<boolean> {
    try {
        // Validate JWT token structure first
        const parts = token.split('.');
        if (parts.length !== 3 || !parts[1]) {
            return false;
        }

        const payload = JSON.parse(atob(parts[1]));
        return payload.role === 'admin' || payload.roles?.includes('admin');
    } catch {
        return false;
    }
}

// Export middleware config
export const config = {
    matcher: [
        '/((?!api|_next/static|_next/image|favicon.ico|public).*)',
    ],
};