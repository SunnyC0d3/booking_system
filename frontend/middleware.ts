import { NextRequest, NextResponse } from 'next/server';
import { securityMiddleware, RATE_LIMIT_CONFIGS, validateOrigin, detectSuspiciousActivity } from '@/middleware/security';

// Route configuration
const routeConfig = {
    // Protected routes requiring authentication
    protectedRoutes: [
        '/dashboard',
        '/profile',
        '/account',
        '/orders',
        '/settings',
        '/admin',
        '/digital-library',
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
        '/download', // Digital download routes
    ],

    // API routes with different rate limits
    apiRoutes: {
        auth: ['/api/auth', '/api/login', '/api/register'],
        public: ['/api/products', '/api/categories', '/api/search'],
        protected: ['/api/user', '/api/orders', '/api/downloads'],
        admin: ['/api/admin'],
    },
};

// Main middleware function
export async function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;
    const startTime = Date.now();

    // Skip middleware for static files and internal Next.js routes
    if (shouldSkipMiddleware(pathname)) {
        return NextResponse.next();
    }

    try {
        // 1. Basic security checks
        if (!validateOrigin(request)) {
            return new NextResponse('Invalid Origin', {
                status: 403,
                headers: { 'Content-Type': 'text/plain' }
            });
        }

        // 2. Detect suspicious activity
        if (detectSuspiciousActivity(request)) {
            console.warn(`Suspicious activity detected: ${request.method} ${pathname} from ${getClientIP(request)}`);
            return new NextResponse('Access Denied', {
                status: 403,
                headers: { 'Content-Type': 'text/plain' }
            });
        }

        // 3. Apply security middleware with route-specific configurations
        const securityConfig = getSecurityConfigForRoute(pathname);
        const securityResponse = await securityMiddleware(request, securityConfig);

        // If security middleware blocked the request, return early
        if (securityResponse.status !== 200) {
            return securityResponse;
        }

        // 4. Authentication and authorization
        const authResponse = await handleAuthentication(request);
        if (authResponse) {
            return authResponse;
        }

        // 5. Route-specific handling
        const routeResponse = await handleRouteSpecific(request);
        if (routeResponse) {
            return routeResponse;
        }

        // 6. Add performance and monitoring headers
        const response = NextResponse.next();
        addPerformanceHeaders(response, startTime);

        return response;

    } catch (error) {
        console.error('Middleware error:', error);

        // Fail securely - allow the request but log the error
        const response = NextResponse.next();
        addBasicHeaders(response);
        return response;
    }
}

function shouldSkipMiddleware(pathname: string): boolean {
    const skipPatterns = [
        '/_next/',
        '/api/_next/',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/manifest.json',
    ];

    return skipPatterns.some(pattern => pathname.startsWith(pattern)) ||
        pathname.includes('.') && !pathname.startsWith('/download/'); // Skip files with extensions except download routes
}

function getClientIP(request: NextRequest): string {
    const forwarded = request.headers.get('x-forwarded-for');
    const realIP = request.headers.get('x-real-ip');
    const cfConnectingIP = request.headers.get('cf-connecting-ip');

    if (cfConnectingIP) return cfConnectingIP;
    if (realIP) return realIP;
    if (forwarded) {
        const firstIP = forwarded.split(',')[0];
        if (firstIP) return firstIP.trim();
    }

    return '127.0.0.1';
}

function getSecurityConfigForRoute(pathname: string) {
    // Configure security based on route type
    if (pathname.startsWith('/api/auth')) {
        return {
            rateLimit: RATE_LIMIT_CONFIGS.auth,
            csp: "default-src 'self'; script-src 'none';",
            hsts: 'max-age=31536000; includeSubDomains; preload',
        };
    }

    if (pathname.startsWith('/api/admin')) {
        return {
            rateLimit: RATE_LIMIT_CONFIGS.admin,
            csp: "default-src 'self'; script-src 'self' 'unsafe-inline';",
            hsts: 'max-age=31536000; includeSubDomains; preload',
        };
    }

    if (pathname.startsWith('/api/')) {
        return {
            rateLimit: RATE_LIMIT_CONFIGS.api,
            csp: "default-src 'self';",
        };
    }

    if (pathname.startsWith('/admin')) {
        return {
            rateLimit: RATE_LIMIT_CONFIGS.admin,
            csp: "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';",
        };
    }

    // Default configuration for public routes
    return {
        rateLimit: RATE_LIMIT_CONFIGS.public,
    };
}

// Authentication and authorization handler
async function handleAuthentication(request: NextRequest): Promise<NextResponse | null> {
    const { pathname } = request.nextUrl;

    // Check if route requires authentication
    const requiresAuth = routeConfig.protectedRoutes.some(route =>
        pathname.startsWith(route)
    );

    const requiresAdmin = routeConfig.adminRoutes.some(route =>
        pathname.startsWith(route)
    );

    if (!requiresAuth && !requiresAdmin) {
        return null; // Public route, no auth required
    }

    // Get auth token from various sources
    const authToken = getAuthToken(request);

    if (!authToken) {
        // Redirect to login for protected routes
        const loginUrl = new URL('/login', request.url);
        loginUrl.searchParams.set('redirect', pathname);
        return NextResponse.redirect(loginUrl);
    }

    // Validate token (this is a simplified version - implement proper JWT validation)
    const isValidToken = await validateAuthToken(authToken);

    if (!isValidToken) {
        // Clear invalid token and redirect to login
        const response = NextResponse.redirect(new URL('/login', request.url));
        response.cookies.delete('auth-token');
        return response;
    }

    // Check admin permissions
    if (requiresAdmin) {
        const hasAdminRole = await checkAdminRole(authToken);
        if (!hasAdminRole) {
            return new NextResponse('Forbidden', {
                status: 403,
                headers: { 'Content-Type': 'text/plain' }
            });
        }
    }

    return null; // Authentication successful
}

function getAuthToken(request: NextRequest): string | null {
    // Check Authorization header first
    const authHeader = request.headers.get('authorization');
    if (authHeader && authHeader.startsWith('Bearer ')) {
        return authHeader.substring(7);
    }

    // Check cookie
    const tokenCookie = request.cookies.get('auth-token');
    if (tokenCookie) {
        return tokenCookie.value;
    }

    return null;
}

async function validateAuthToken(token: string): Promise<boolean> {
    // Implement proper JWT validation here
    // This is a simplified version for demonstration
    try {
        // In a real implementation, verify JWT signature and expiration
        // const payload = jwt.verify(token, process.env.JWT_SECRET);
        // return payload && !isTokenExpired(payload);

        // Placeholder validation
        return token.length > 10; // Very basic check
    } catch {
        return false;
    }
}

async function checkAdminRole(token: string): Promise<boolean> {
    // Implement proper role checking here
    // This is a simplified version for demonstration
    try {
        // In a real implementation, decode token and check roles
        // const payload = jwt.decode(token);
        // return payload.roles.includes('admin');

        // Placeholder check
        return token.includes('admin'); // Very basic check
    } catch {
        return false;
    }
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
        return handleAdminRoutes();
    }

    // Handle download routes with special security
    if (pathname.startsWith('/download/')) {
        return handleDownloadRoutes(request);
    }

    // Handle product routes with caching
    if (pathname.startsWith('/products')) {
        const response = NextResponse.next();
        response.headers.set('Cache-Control', 'public, max-age=300, s-maxage=300');
        return response;
    }

    // Handle static content with long caching
    if (pathname.startsWith('/images/') || pathname.startsWith('/assets/')) {
        const response = NextResponse.next();
        response.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
        return response;
    }

    return null;
}

// API routes handler
async function handleApiRoutes(request: NextRequest): Promise<NextResponse | null> {
    const response = NextResponse.next();

    // CORS headers for API routes
    const allowedOrigins = [
        process.env.NEXT_PUBLIC_APP_URL,
        process.env.FRONTEND_URL,
        'http://localhost:3000',
        'https://localhost:3000',
    ].filter(Boolean);

    const origin = request.headers.get('origin');
    if (origin && allowedOrigins.includes(origin)) {
        response.headers.set('Access-Control-Allow-Origin', origin);
    }

    response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token');
    response.headers.set('Access-Control-Allow-Credentials', 'true');

    // Handle preflight requests
    if (request.method === 'OPTIONS') {
        return new NextResponse(null, { status: 200, headers: response.headers });
    }

    // Add API versioning header
    response.headers.set('API-Version', 'v1');

    // Add request ID for tracing
    response.headers.set('X-Request-ID', generateRequestId());

    // Add API rate limit headers are already added by security middleware

    return response;
}

// Admin routes handler
async function handleAdminRoutes(): Promise<NextResponse | null> {
    const response = NextResponse.next();

    // Stricter CSP for admin routes
    response.headers.set(
        'Content-Security-Policy',
        "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;"
    );

    // Disable caching for admin routes
    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate');
    response.headers.set('Pragma', 'no-cache');
    response.headers.set('Expires', '0');

    return response;
}

// Download routes handler with enhanced security
async function handleDownloadRoutes(request: NextRequest): Promise<NextResponse | null> {
    const response = NextResponse.next();

    // Special headers for download routes
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Download-Options', 'noopen');
    response.headers.set('X-Frame-Options', 'DENY');

    // Disable caching for download tokens
    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate');
    response.headers.set('Pragma', 'no-cache');

    // Log download attempts for security monitoring
    const token = request.nextUrl.pathname.split('/').pop();
    console.log(`Download attempt: token=${token} from IP=${getClientIP(request)}`);

    return response;
}

function addPerformanceHeaders(response: NextResponse, startTime: number): void {
    const processingTime = Date.now() - startTime;
    response.headers.set('X-Response-Time', `${processingTime}ms`);
    response.headers.set('X-Timestamp', new Date().toISOString());
}

function addBasicHeaders(response: NextResponse): void {
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Frame-Options', 'DENY');
    response.headers.set('X-XSS-Protection', '1; mode=block');
}

function generateRequestId(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    // Fallback for environments without crypto.randomUUID
    return Date.now().toString(36) + Math.random().toString(36).substring(2);
}

// Export configuration for other parts of the app
export const config = {
    matcher: [
        /*
         * Match all request paths except for the ones starting with:
         * - api (API routes - handled separately)
         * - _next/static (static files)
         * - _next/image (image optimization files)
         * - favicon.ico (favicon file)
         * - robots.txt
         * - sitemap.xml
         * - manifest.json
         */
        '/((?!api|_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|manifest.json).*)',
    ],
};

export default middleware;