import { NextRequest, NextResponse } from 'next/server';
import { rateLimit } from '@/lib/rate-limit';
import { validateCSRF } from '@/lib/csrf';
import { sanitizeInput } from '@/lib/sanitization';

// Security configuration
const SECURITY_CONFIG = {
    rateLimiting: {
        windowMs: 15 * 60 * 1000, // 15 minutes
        maxRequests: 100,
        skipSuccessfulRequests: true,
    },
    csp: {
        'default-src': ["'self'"],
        'script-src': ["'self'", "'unsafe-eval'", "'unsafe-inline'", 'https://vercel.live'],
        'style-src': ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
        'font-src': ["'self'", 'https://fonts.gstatic.com'],
        'img-src': ["'self'", 'data:', 'https:', 'blob:'],
        'media-src': ["'self'", 'https:'],
        'connect-src': ["'self'", 'https:', 'wss:'],
        'worker-src': ["'self'", 'blob:'],
        'child-src': ["'self'"],
        'object-src': ["'none'"],
        'base-uri': ["'self'"],
        'form-action': ["'self'"],
        'frame-ancestors': ["'none'"],
    },
    sensitiveRoutes: ['/admin', '/api/admin', '/dashboard'],
    publicRoutes: ['/', '/products', '/login', '/register'],
};

// IP whitelist for admin routes (configure as needed)
const ADMIN_IP_WHITELIST = process.env.ADMIN_IPS?.split(',') || [];

// Security headers
function getSecurityHeaders(request: NextRequest): Record<string, string> {
    const nonce = generateNonce();
    const cspString = generateCSP(SECURITY_CONFIG.csp, nonce);

    return {
        // Content Security Policy
        'Content-Security-Policy': cspString,

        // HSTS
        'Strict-Transport-Security': 'max-age=63072000; includeSubDomains; preload',

        // XSS Protection
        'X-XSS-Protection': '1; mode=block',

        // Frame Options
        'X-Frame-Options': 'DENY',

        // Content Type Options
        'X-Content-Type-Options': 'nosniff',

        // Referrer Policy
        'Referrer-Policy': 'strict-origin-when-cross-origin',

        // Permissions Policy
        'Permissions-Policy': [
            'camera=()',
            'microphone=()',
            'geolocation=(self)',
            'gyroscope=()',
            'magnetometer=()',
            'payment=(self)',
            'usb=()',
        ].join(', '),

        // Cross-Origin Policies
        'Cross-Origin-Embedder-Policy': 'unsafe-none',
        'Cross-Origin-Opener-Policy': 'same-origin',
        'Cross-Origin-Resource-Policy': 'same-origin',

        // Security Headers
        'X-Permitted-Cross-Domain-Policies': 'none',
        'X-Download-Options': 'noopen',
        'X-DNS-Prefetch-Control': 'off',

        // Custom Security Headers
        'X-Security-Hash': generateSecurityHash(request),
        'X-Request-ID': crypto.randomUUID(),
    };
}

// Generate CSP string
function generateCSP(csp: Record<string, string[]>, nonce: string): string {
    const directives = Object.entries(csp).map(([key, values]) => {
        if (key === 'script-src') {
            values = [...values, `'nonce-${nonce}'`];
        }
        return `${key} ${values.join(' ')}`;
    });

    return directives.join('; ');
}

// Generate nonce for inline scripts
function generateNonce(): string {
    return Buffer.from(crypto.randomUUID()).toString('base64').slice(0, 16);
}

// Generate security hash
function generateSecurityHash(request: NextRequest): string {
    const data = `${request.method}:${request.url}:${Date.now()}`;
    return Buffer.from(data).toString('base64').slice(0, 20);
}

// Rate limiting implementation
class RateLimiter {
    private static cache = new Map<string, { count: number; resetTime: number }>();

    static async checkLimit(
        identifier: string,
        limit: number = 100,
        windowMs: number = 15 * 60 * 1000
    ): Promise<{ allowed: boolean; remaining: number; resetTime: number }> {
        const now = Date.now();
        const key = `rate_limit:${identifier}`;
        const current = this.cache.get(key);

        if (!current || now > current.resetTime) {
            // Reset or initialize
            const resetTime = now + windowMs;
            this.cache.set(key, { count: 1, resetTime });
            return { allowed: true, remaining: limit - 1, resetTime };
        }

        if (current.count >= limit) {
            return { allowed: false, remaining: 0, resetTime: current.resetTime };
        }

        // Increment count
        current.count++;
        this.cache.set(key, current);

        return {
            allowed: true,
            remaining: limit - current.count,
            resetTime: current.resetTime
        };
    }

    static cleanup(): void {
        const now = Date.now();
        for (const [key, value] of this.cache.entries()) {
            if (now > value.resetTime) {
                this.cache.delete(key);
            }
        }
    }
}

// Clean up rate limiter cache every 5 minutes
setInterval(() => RateLimiter.cleanup(), 5 * 60 * 1000);

// IP-based access control
function checkIPAccess(request: NextRequest, route: string): boolean {
    if (!SECURITY_CONFIG.sensitiveRoutes.some(r => route.startsWith(r))) {
        return true; // Allow access to non-sensitive routes
    }

    const forwarded = request.headers.get('x-forwarded-for');
    const realIP = request.headers.get('x-real-ip');
    const clientIP = forwarded?.split(',')[0] || realIP || 'unknown';

    // In development, allow localhost
    if (process.env.NODE_ENV === 'development') {
        if (clientIP === '127.0.0.1' || clientIP === '::1' || clientIP === 'unknown') {
            return true;
        }
    }

    // Check whitelist
    if (ADMIN_IP_WHITELIST.length > 0) {
        return ADMIN_IP_WHITELIST.includes(clientIP);
    }

    return true; // Allow if no whitelist configured
}

// Main security middleware
export async function securityMiddleware(request: NextRequest): Promise<NextResponse> {
    const { pathname } = request.nextUrl;
    const method = request.method;

    // Skip security checks for static assets
    if (pathname.startsWith('/_next/') || pathname.startsWith('/api/_next/')) {
        return NextResponse.next();
    }

    try {
        // 1. IP-based access control
        if (!checkIPAccess(request, pathname)) {
            return new NextResponse('Access Denied', {
                status: 403,
                headers: { 'Content-Type': 'text/plain' }
            });
        }

        // 2. Rate limiting
        const clientIP = request.headers.get('x-forwarded-for')?.split(',')[0] ||
            request.headers.get('x-real-ip') ||
            'unknown';

        const rateLimitResult = await RateLimiter.checkLimit(
            clientIP,
            SECURITY_CONFIG.rateLimiting.maxRequests,
            SECURITY_CONFIG.rateLimiting.windowMs
        );

        if (!rateLimitResult.allowed) {
            return new NextResponse('Rate Limit Exceeded', {
                status: 429,
                headers: {
                    'Retry-After': Math.ceil((rateLimitResult.resetTime - Date.now()) / 1000).toString(),
                    'X-RateLimit-Limit': SECURITY_CONFIG.rateLimiting.maxRequests.toString(),
                    'X-RateLimit-Remaining': '0',
                    'X-RateLimit-Reset': rateLimitResult.resetTime.toString(),
                },
            });
        }

        // 3. CSRF protection for state-changing requests
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            const csrfToken = request.headers.get('x-csrf-token') ||
                request.headers.get('csrf-token');

            if (pathname.startsWith('/api/') && !validateCSRF(csrfToken)) {
                return new NextResponse('CSRF Token Invalid', {
                    status: 403,
                    headers: { 'Content-Type': 'text/plain' }
                });
            }
        }

        // 4. Input size validation
        const contentLength = request.headers.get('content-length');
        if (contentLength && parseInt(contentLength) > 10 * 1024 * 1024) { // 10MB
            return new NextResponse('Payload Too Large', {
                status: 413,
                headers: { 'Content-Type': 'text/plain' }
            });
        }

        // 5. Create response with security headers
        const response = NextResponse.next();
        const securityHeaders = getSecurityHeaders(request);

        Object.entries(securityHeaders).forEach(([key, value]) => {
            response.headers.set(key, value);
        });

        // Add rate limit headers
        response.headers.set('X-RateLimit-Limit', SECURITY_CONFIG.rateLimiting.maxRequests.toString());
        response.headers.set('X-RateLimit-Remaining', rateLimitResult.remaining.toString());
        response.headers.set('X-RateLimit-Reset', rateLimitResult.resetTime.toString());

        return response;

    } catch (error) {
        console.error('Security middleware error:', error);

        // Fail securely
        return new NextResponse('Security Error', {
            status: 500,
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// Export configuration for use in other parts of the app
export { SECURITY_CONFIG, RateLimiter };