import { NextRequest, NextResponse } from 'next/server';

interface SecurityConfig {
    csp?: string;
    hsts?: string;
    rateLimit?: {
        windowMs: number;
        maxRequests: number;
    };
}

interface CSRFValidationResult {
    valid: boolean;
    token?: string;
}

const DEFAULT_CONFIG: SecurityConfig = {
    csp: "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https:;",
    hsts: 'max-age=31536000; includeSubDomains; preload',
    rateLimit: {
        windowMs: 15 * 60 * 1000, // 15 minutes
        maxRequests: 100, // limit each IP to 100 requests per windowMs
    },
};

// In-memory rate limiting store (in production, use Redis)
const rateLimitStore = new Map<string, { count: number; resetTime: number }>();

export async function securityMiddleware(
    request: NextRequest,
    config: SecurityConfig = DEFAULT_CONFIG
): Promise<NextResponse> {
    const response = NextResponse.next();
    const clientIP = getClientIP(request);
    const pathname = request.nextUrl.pathname;

    try {
        // 1. Rate Limiting
        if (config.rateLimit && shouldApplyRateLimit(pathname)) {
            const rateLimitResult = checkRateLimit(clientIP, config.rateLimit);
            if (!rateLimitResult.allowed) {
                return new NextResponse('Too Many Requests', {
                    status: 429,
                    headers: {
                        'Content-Type': 'text/plain',
                        'Retry-After': Math.ceil((rateLimitResult.resetTime - Date.now()) / 1000).toString(),
                        'X-RateLimit-Limit': config.rateLimit.maxRequests.toString(),
                        'X-RateLimit-Remaining': '0',
                        'X-RateLimit-Reset': new Date(rateLimitResult.resetTime).toISOString(),
                    },
                });
            }

            // Add rate limit headers
            response.headers.set('X-RateLimit-Limit', config.rateLimit.maxRequests.toString());
            response.headers.set('X-RateLimit-Remaining', (config.rateLimit.maxRequests - rateLimitResult.count).toString());
            response.headers.set('X-RateLimit-Reset', new Date(rateLimitResult.resetTime).toISOString());
        }

        // 2. Security Headers
        addSecurityHeaders(response, config);

        // 3. CSRF Protection for state-changing operations
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(request.method)) {
            const csrfResult = validateCSRFToken(request);
            if (!csrfResult.valid && shouldEnforceCSRF(pathname)) {
                return new NextResponse('CSRF Token Invalid', {
                    status: 403,
                    headers: { 'Content-Type': 'text/plain' },
                });
            }
        }

        // 4. Content Security Policy Violations Logging
        if (pathname === '/api/csp-report') {
            logCSPViolation(request);
            return new NextResponse('OK', { status: 200 });
        }

        // 5. Security Monitoring
        logSecurityEvent(request, clientIP);

        return response;

    } catch (error) {
        console.error('Security middleware error:', error);

        // Fail securely - allow request but log the error
        addBasicSecurityHeaders(response);
        return response;
    }
}

function getClientIP(request: NextRequest): string {
    // Check various headers for real IP
    const forwarded = request.headers.get('x-forwarded-for');
    const realIP = request.headers.get('x-real-ip');
    const cfConnectingIP = request.headers.get('cf-connecting-ip');

    if (cfConnectingIP) return cfConnectingIP;
    if (realIP) return realIP;

    // Fixed: Added null check before calling split
    if (forwarded) {
        const firstIP = forwarded.split(',')[0];
        if (firstIP) {
            return firstIP.trim();
        }
    }

    // Fallback to a default IP for development
    return '127.0.0.1';
}

function shouldApplyRateLimit(pathname: string): boolean {
    // Apply rate limiting to API routes and sensitive pages
    const rateLimitedPaths = [
        '/api/',
        '/login',
        '/register',
        '/forgot-password',
        '/contact',
    ];

    return rateLimitedPaths.some(path => pathname.startsWith(path));
}

function checkRateLimit(
    clientIP: string,
    config: { windowMs: number; maxRequests: number }
): { allowed: boolean; count: number; resetTime: number } {
    const now = Date.now();
    // Removed unused windowStart variable
    const key = `rate_limit:${clientIP}`;

    let record = rateLimitStore.get(key);

    // Clean up old records or create new one
    if (!record || record.resetTime <= now) {
        record = {
            count: 0,
            resetTime: now + config.windowMs,
        };
    }

    record.count++;
    rateLimitStore.set(key, record);

    // Clean up old entries periodically
    if (Math.random() < 0.01) { // 1% chance
        cleanupRateLimitStore();
    }

    return {
        allowed: record.count <= config.maxRequests,
        count: record.count,
        resetTime: record.resetTime,
    };
}

function cleanupRateLimitStore(): void {
    const now = Date.now();
    const keysToDelete: string[] = [];

    rateLimitStore.forEach((record, key) => {
        if (record.resetTime <= now) {
            keysToDelete.push(key);
        }
    });

    keysToDelete.forEach(key => rateLimitStore.delete(key));
}

function addSecurityHeaders(response: NextResponse, config: SecurityConfig): void {
    // Content Security Policy
    if (config.csp) {
        response.headers.set('Content-Security-Policy', config.csp);
    }

    // HTTP Strict Transport Security
    if (config.hsts && process.env.NODE_ENV === 'production') {
        response.headers.set('Strict-Transport-Security', config.hsts);
    }

    // Basic security headers
    addBasicSecurityHeaders(response);
}

function addBasicSecurityHeaders(response: NextResponse): void {
    response.headers.set('X-Content-Type-Options', 'nosniff');
    response.headers.set('X-Frame-Options', 'DENY');
    response.headers.set('X-XSS-Protection', '1; mode=block');
    response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin');
    response.headers.set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    // Remove server information
    response.headers.delete('Server');
    response.headers.delete('X-Powered-By');
}

function validateCSRFToken(request: NextRequest): CSRFValidationResult {
    // Get CSRF token from header or body
    const headerToken = request.headers.get('X-CSRF-Token');
    const cookieToken = request.cookies.get('csrf-token')?.value;

    // For API routes, we might use a different validation approach
    if (request.nextUrl.pathname.startsWith('/api/')) {
        // For API routes, validate the token from headers
        const isValid = Boolean(headerToken && cookieToken && headerToken === cookieToken);

        // Fixed: Properly handle optional property with exact types
        const result: CSRFValidationResult = {
            valid: isValid,
        };

        if (headerToken) {
            result.token = headerToken;
        }

        return result;
    }

    // For form submissions, we might check hidden form fields
    // This is a simplified implementation
    return { valid: true }; // Allow for now, implement based on your auth strategy
}

function shouldEnforceCSRF(pathname: string): boolean {
    // Enforce CSRF for sensitive operations
    const csrfProtectedPaths = [
        '/api/auth/',
        '/api/admin/',
        '/api/user/',
        '/api/orders/',
        '/api/payments/',
    ];

    return csrfProtectedPaths.some(path => pathname.startsWith(path));
}

function logCSPViolation(request: NextRequest): void {
    // Log CSP violations for monitoring
    request.json().then(violation => {
        console.warn('CSP Violation:', violation);
        // In production, send this to your monitoring service
    }).catch(error => {
        console.error('Failed to parse CSP violation report:', error);
    });
}

function logSecurityEvent(request: NextRequest, clientIP: string): void {
    // Log security-relevant events
    const pathname = request.nextUrl.pathname;
    // Fixed: method is on request, not request.nextUrl
    const method = request.method;
    const userAgent = request.headers.get('user-agent') || 'unknown';

    // Only log interesting events to avoid noise
    const shouldLog = method !== 'GET' ||
        pathname.includes('admin') ||
        pathname.includes('api');

    if (shouldLog) {
        console.log(`Security Event: ${method} ${pathname} from ${clientIP} (${userAgent})`);
    }
}

// Enhanced CSRF token generation with proper crypto handling
export function generateCSRFToken(): string {
    // Use crypto.randomUUID() if available, otherwise fallback
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    // Fallback for environments without crypto.randomUUID
    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        const array = new Uint8Array(16);
        crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    // Final fallback using Math.random (less secure, use only in development)
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
}

export function setCSRFToken(response: NextResponse): string {
    const token = generateCSRFToken();

    response.cookies.set('csrf-token', token, {
        httpOnly: true,
        secure: process.env.NODE_ENV === 'production',
        sameSite: 'strict',
        maxAge: 60 * 60 * 24, // 24 hours
        path: '/', // Add explicit path for better browser compatibility
    });

    return token;
}

// Additional utility functions for enhanced security
export function validateOrigin(request: NextRequest): boolean {
    const origin = request.headers.get('origin');
    const allowedOrigins = [
        process.env.NEXT_PUBLIC_APP_URL,
        process.env.FRONTEND_URL,
        'http://localhost:3000', // Development
        'https://localhost:3000', // HTTPS development
    ].filter(Boolean);

    if (!origin) {
        // Allow requests without origin (e.g., direct navigation)
        return true;
    }

    return allowedOrigins.some(allowed => origin === allowed);
}

export function detectSuspiciousActivity(request: NextRequest): boolean {
    const userAgent = request.headers.get('user-agent') || '';
    const pathname = request.nextUrl.pathname;

    // Check for common bot patterns
    const suspiciousBots = [
        'bot', 'crawler', 'spider', 'scraper',
        'wget', 'curl', 'python-requests'
    ];

    if (suspiciousBots.some(bot => userAgent.toLowerCase().includes(bot))) {
        // Allow legitimate bots for SEO
        const legitimateBots = [
            'googlebot', 'bingbot', 'slurp', 'facebookexternalhit'
        ];

        if (!legitimateBots.some(bot => userAgent.toLowerCase().includes(bot))) {
            return true;
        }
    }

    // Check for suspicious path patterns
    const suspiciousPaths = [
        '.env', '.git', 'wp-admin', 'admin.php',
        'phpmyadmin', '.htaccess', 'config.php'
    ];

    return suspiciousPaths.some(path => pathname.includes(path));
}

// Rate limit configurations for different route types
export const RATE_LIMIT_CONFIGS = {
    api: {
        windowMs: 15 * 60 * 1000, // 15 minutes
        maxRequests: 100,
    },
    auth: {
        windowMs: 15 * 60 * 1000, // 15 minutes
        maxRequests: 5, // Stricter for auth endpoints
    },
    public: {
        windowMs: 60 * 1000, // 1 minute
        maxRequests: 30,
    },
    admin: {
        windowMs: 15 * 60 * 1000, // 15 minutes
        maxRequests: 200, // Higher for admin users
    },
} as const;

// Enhanced IP validation
export function isValidIP(ip: string): boolean {
    // IPv4 regex
    const ipv4Regex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

    // IPv6 regex (simplified)
    const ipv6Regex = /^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/;

    return ipv4Regex.test(ip) || ipv6Regex.test(ip);
}