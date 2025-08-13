import { NextRequest, NextResponse } from 'next/server';
import { securityMiddleware, RATE_LIMIT_CONFIGS, validateOrigin, detectSuspiciousActivity } from '@/middleware/security';
import { MiddlewareAuthUtils } from '@/middleware/auth-utils';

const routeConfig = {
    protectedRoutes: [
        '/dashboard',
        '/profile',
        '/account',
        '/orders',
        '/settings',
        '/admin',
        '/digital-library',
    ],
    adminRoutes: ['/admin'],
    publicRoutes: [
        '/',
        '/products',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/about',
        '/contact',
        '/download',
    ],
    apiRoutes: {
        auth: ['/api/auth', '/api/login', '/api/register'],
        public: ['/api/products', '/api/categories', '/api/search'],
        protected: ['/api/user', '/api/orders', '/api/downloads'],
        admin: ['/api/admin'],
    },
} as const;

interface MiddlewareConfig {
    rateLimit?: any;
    csp?: string;
    hsts?: string;
    cache?: string;
}

export async function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;
    const startTime = Date.now();

    if (shouldSkipMiddleware(pathname)) {
        return NextResponse.next();
    }

    try {
        const securityResult = await handleSecurity(request);
        if (securityResult) return securityResult;

        const authResult = await handleAuthentication(request);
        if (authResult) return authResult;

        const routeResult = await handleRouteSpecific(request);
        if (routeResult) return routeResult;

        const response = NextResponse.next();
        addPerformanceHeaders(response, startTime);
        addSecurityHeaders(response, pathname);

        return response;

    } catch (error) {
        console.error('Middleware error:', error);
        const response = NextResponse.next();
        addBasicSecurityHeaders(response);
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
        '/__nextjs_original-stack-frame',
    ] as const;

    return skipPatterns.some(pattern => pathname.startsWith(pattern)) ||
        (pathname.includes('.') && !pathname.startsWith('/download/'));
}

async function handleSecurity(request: NextRequest): Promise<NextResponse | null> {
    if (!validateOrigin(request)) {
        return new NextResponse('Invalid Origin', {
            status: 403,
            headers: { 'Content-Type': 'text/plain' }
        });
    }

    if (detectSuspiciousActivity(request)) {
        console.warn(`Suspicious activity: ${request.method} ${request.nextUrl.pathname} from ${getClientIP(request)}`);
        return new NextResponse('Access Denied', {
            status: 403,
            headers: { 'Content-Type': 'text/plain' }
        });
    }

    const securityConfig = getSecurityConfigForRoute(request.nextUrl.pathname);
    const securityResponse = await securityMiddleware(request, securityConfig);

    return securityResponse.status !== 200 ? securityResponse : null;
}

async function handleAuthentication(request: NextRequest): Promise<NextResponse | null> {
    const { pathname } = request.nextUrl;

    const requiresAuth = routeConfig.protectedRoutes.some(route => pathname.startsWith(route));
    const requiresAdmin = routeConfig.adminRoutes.some(route => pathname.startsWith(route));

    if (!requiresAuth && !requiresAdmin) {
        return null;
    }

    const authToken = getAuthToken(request);

    if (!authToken) {
        return createAuthRedirect(request, pathname);
    }

    if (isTokenExpired(authToken)) {
        return createTokenExpiredResponse(request, pathname);
    }

    const isValid = await validateAuthToken(authToken);
    if (!isValid) {
        return createInvalidTokenResponse(request, pathname);
    }

    if (requiresAdmin) {
        const hasAdminRole = await checkAdminRole(authToken);
        if (!hasAdminRole) {
            return new NextResponse('Forbidden - Admin access required', {
                status: 403,
                headers: { 'Content-Type': 'text/plain' }
            });
        }
    }

    return null;
}

function getAuthToken(request: NextRequest): string | null {
    const tokenInfo = MiddlewareAuthUtils.extractTokenFromRequest(request);
    return tokenInfo?.token || null;
}

async function validateAuthToken(token: string): Promise<boolean> {
    const result = await MiddlewareAuthUtils.validateToken(token);
    return result.isValid;
}

async function checkAdminRole(token: string): Promise<boolean> {
    return await MiddlewareAuthUtils.checkUserRole(token, 'admin');
}

function createAuthRedirect(request: NextRequest, pathname: string): NextResponse {
    if (pathname.startsWith('/api/')) {
        return new NextResponse('Unauthorized', {
            status: 401,
            headers: {
                'Content-Type': 'text/plain',
                'WWW-Authenticate': 'Bearer realm="API"'
            }
        });
    }

    const loginUrl = new URL('/login', request.url);
    loginUrl.searchParams.set('redirect', pathname);
    return NextResponse.redirect(loginUrl);
}

function createTokenExpiredResponse(request: NextRequest, pathname: string): NextResponse {
    if (pathname.startsWith('/api/')) {
        return new NextResponse('Token Expired', {
            status: 401,
            headers: {
                'Content-Type': 'text/plain',
                'WWW-Authenticate': 'Bearer error="invalid_token", error_description="Token expired"'
            }
        });
    }

    const response = NextResponse.redirect(new URL('/login', request.url));
    response.cookies.delete('auth-token');
    return response;
}

function createInvalidTokenResponse(request: NextRequest, pathname: string): NextResponse {
    if (pathname.startsWith('/api/')) {
        return new NextResponse('Invalid Token', {
            status: 401,
            headers: {
                'Content-Type': 'text/plain',
                'WWW-Authenticate': 'Bearer error="invalid_token"'
            }
        });
    }

    const response = NextResponse.redirect(new URL('/login', request.url));
    response.cookies.delete('auth-token');
    return response;
}

function getSecurityConfigForRoute(pathname: string): MiddlewareConfig {
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
            csp: "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
        };
    }

    return {
        rateLimit: RATE_LIMIT_CONFIGS.public,
    };
}

async function handleRouteSpecific(request: NextRequest): Promise<NextResponse | null> {
    const { pathname } = request.nextUrl;

    if (pathname.startsWith('/api/')) {
        return handleApiRoutes(request);
    }

    if (pathname.startsWith('/admin')) {
        return handleAdminRoutes();
    }

    if (pathname.startsWith('/download/')) {
        return handleDownloadRoutes(request);
    }

    if (pathname.startsWith('/products')) {
        const response = NextResponse.next();
        response.headers.set('Cache-Control', 'public, max-age=300, s-maxage=600, stale-while-revalidate=86400');
        return response;
    }

    if (pathname.startsWith('/images/') || pathname.startsWith('/assets/')) {
        const response = NextResponse.next();
        response.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
        return response;
    }

    return null;
}

async function handleApiRoutes(request: NextRequest): Promise<NextResponse | null> {
    const response = NextResponse.next();

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

    response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token, X-Request-ID');
    response.headers.set('Access-Control-Allow-Credentials', 'true');
    response.headers.set('Access-Control-Max-Age', '86400');

    if (request.method === 'OPTIONS') {
        return new NextResponse(null, { status: 204, headers: response.headers });
    }

    response.headers.set('API-Version', 'v1');
    response.headers.set('X-Request-ID', generateRequestId());

    return response;
}

async function handleAdminRoutes(): Promise<NextResponse | null> {
    const response = NextResponse.next();

    response.headers.set(
        'Content-Security-Policy',
        "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https:;"
    );

    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
    response.headers.set('Pragma', 'no-cache');
    response.headers.set('Expires', '0');
    response.headers.set('Surrogate-Control', 'no-store');

    return response;
}

async function handleDownloadRoutes(request: NextRequest): Promise<NextResponse | null> {
    const response = NextResponse.next();

    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Download-Options', 'noopen');
    response.headers.set('X-Frame-Options', 'DENY');
    response.headers.set('Referrer-Policy', 'no-referrer');

    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate');
    response.headers.set('Pragma', 'no-cache');

    const token = request.nextUrl.pathname.split('/').pop();
    console.log(`Download attempt: token=${token} from IP=${getClientIP(request)} UA=${request.headers.get('user-agent')?.substring(0, 100)}`);

    return response;
}

function addPerformanceHeaders(response: NextResponse, startTime: number): void {
    const processingTime = Date.now() - startTime;
    response.headers.set('X-Response-Time', `${processingTime}ms`);
    response.headers.set('X-Timestamp', new Date().toISOString());
    response.headers.set('X-Middleware-Version', '2.0');
}

function addSecurityHeaders(response: NextResponse, pathname: string): void {
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Frame-Options', 'DENY');
    response.headers.set('X-XSS-Protection', '1; mode=block');
    response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin');

    if (!pathname.startsWith('/admin')) {
        response.headers.set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}

function addBasicSecurityHeaders(response: NextResponse): void {
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Frame-Options', 'DENY');
    response.headers.set('X-XSS-Protection', '1; mode=block');
    response.headers.set('X-Middleware-Error', 'true');
}

function getClientIP(request: NextRequest): string {
    return MiddlewareAuthUtils.getClientIP(request);
}

function generateRequestId(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).substring(2)}`;
}

function parseJwtExpiry(token: string): number | null {
    return MiddlewareAuthUtils.parseJwtExpiry(token);
}

function isTokenExpired(token: string): boolean {
    const tokenInfo = { token, expiresAt: MiddlewareAuthUtils.parseJwtExpiry(token) };
    return MiddlewareAuthUtils.isTokenExpired(tokenInfo);
}

export const config = {
    matcher: [
        '/((?!_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|manifest.json).*)',
    ],
};

export default middleware;