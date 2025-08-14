import { NextRequest, NextResponse } from 'next/server';
import { MiddlewareAuthUtils } from '@/middleware/auth-utils';

const AUTH_CONFIG = {
    protected: ['/dashboard', '/profile', '/account', '/orders', '/settings', '/digital-library'],
    admin: ['/admin'],
    public: ['/', '/products', '/login', '/register', '/forgot-password', '/reset-password', '/about', '/contact'],
    authApi: ['/api/auth/logout', '/api/auth/refresh', '/api/user', '/api/orders', '/api/downloads', '/api/admin'],
    publicApi: ['/api/auth/login', '/api/auth/register', '/api/auth/forgot-password', '/api/products', '/api/categories', '/api/health']
} as const;

export async function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    if (shouldSkip(pathname)) {
        return NextResponse.next();
    }

    try {
        if (pathname.startsWith('/api/')) {
            return handleApiAuth(request);
        }

        return handlePageAuth(request);

    } catch (error) {
        console.error('Middleware error:', error);
        return NextResponse.next();
    }
}

function shouldSkip(pathname: string): boolean {
    return [
            '/_next/', '/favicon.ico', '/robots.txt', '/sitemap.xml', '/manifest.json'
        ].some(pattern => pathname.startsWith(pattern)) ||
        (pathname.includes('.') && !pathname.startsWith('/download/'));
}

async function handleApiAuth(request: NextRequest): Promise<NextResponse> {
    const { pathname } = request.nextUrl;

    const requiresAuth = AUTH_CONFIG.authApi.some(route => pathname.startsWith(route));
    const isPublicApi = AUTH_CONFIG.publicApi.some(route => pathname.startsWith(route));

    if (isPublicApi || !requiresAuth) {
        return NextResponse.next();
    }

    const tokenInfo = MiddlewareAuthUtils.extractTokenFromRequest(request);

    if (!tokenInfo?.token) {
        return new NextResponse('Unauthorized', {
            status: 401,
            headers: MiddlewareAuthUtils.createAuthHeaders(true)
        });
    }

    if (MiddlewareAuthUtils.isTokenExpired(tokenInfo)) {
        return new NextResponse('Token Expired', {
            status: 401,
            headers: {
                ...MiddlewareAuthUtils.createAuthHeaders(true),
                'WWW-Authenticate': 'Bearer error="invalid_token", error_description="Token expired"'
            }
        });
    }

    const { isValid } = await MiddlewareAuthUtils.validateToken(tokenInfo.token);

    if (!isValid) {
        return new NextResponse('Invalid Token', {
            status: 401,
            headers: MiddlewareAuthUtils.createAuthHeaders(true)
        });
    }

    if (pathname.startsWith('/api/admin')) {
        const hasAdminRole = await MiddlewareAuthUtils.checkUserRole(tokenInfo.token, 'admin');
        if (!hasAdminRole) {
            return new NextResponse('Forbidden', { status: 403 });
        }
    }

    return NextResponse.next();
}

async function handlePageAuth(request: NextRequest): Promise<NextResponse> {
    const { pathname } = request.nextUrl;

    // Check if page requires authentication
    const requiresAuth = AUTH_CONFIG.protected.some(route => pathname.startsWith(route));
    const requiresAdmin = AUTH_CONFIG.admin.some(route => pathname.startsWith(route));
    const isPublicRoute = AUTH_CONFIG.public.some(route => pathname === route || pathname.startsWith(route));

    if (isPublicRoute || (!requiresAuth && !requiresAdmin)) {
        return NextResponse.next();
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/((?!_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml|manifest.json).*)']
};