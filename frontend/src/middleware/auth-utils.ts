import { NextRequest } from 'next/server';

export interface AuthValidationResult {
    isValid: boolean;
    user?: any;
    error?: string;
    shouldRefresh?: boolean;
}

export interface TokenInfo {
    token: string;
    expiresAt?: number;
    issuedAt?: number;
    type: 'user' | 'client';
}

export class MiddlewareAuthUtils {
    private static readonly API_TIMEOUT = 5000;
    private static readonly TOKEN_BUFFER_TIME = 30000;

    static async validateToken(token: string): Promise<AuthValidationResult> {
        try {
            const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.API_TIMEOUT);

            const response = await fetch(`${baseUrl}/api/user`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'User-Agent': 'Next.js-Middleware/2.0',
                    'X-Middleware-Request': 'true',
                },
                signal: controller.signal,
                cache: 'no-store',
            });

            clearTimeout(timeoutId);

            if (response.ok) {
                const userData = await response.json();
                return {
                    isValid: true,
                    user: userData.user || userData,
                };
            }

            if (response.status === 401) {
                return {
                    isValid: false,
                    error: 'Token expired or invalid',
                    shouldRefresh: true,
                };
            }

            return {
                isValid: false,
                error: `Validation failed with status ${response.status}`,
            };

        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                return {
                    isValid: false,
                    error: 'Token validation timeout',
                };
            }

            console.warn('Token validation error:', error);
            return {
                isValid: false,
                error: 'Network error during validation',
            };
        }
    }

    static async checkUserRole(token: string, requiredRole: string): Promise<boolean> {
        const result = await this.validateToken(token);

        if (!result.isValid || !result.user) {
            return false;
        }

        const user = result.user;

        return user?.role?.name === requiredRole ||
            user?.roles?.some((role: any) => role.name === requiredRole) ||
            false;
    }

    static async checkUserPermission(token: string, requiredPermission: string): Promise<boolean> {
        const result = await this.validateToken(token);

        if (!result.isValid || !result.user) {
            return false;
        }

        const user = result.user;

        return user?.role?.permissions?.some((perm: any) => perm.name === requiredPermission) ||
            user?.permissions?.some((perm: any) => perm.name === requiredPermission) ||
            false;
    }

    static extractTokenFromRequest(request: NextRequest): TokenInfo | null {
        const authHeader = request.headers.get('authorization');
        if (authHeader?.startsWith('Bearer ')) {
            const token = authHeader.substring(7);
            return {
                token,
                type: 'user',
                expiresAt: this.parseJwtExpiry(token),
                issuedAt: this.parseJwtIssuedAt(token),
            };
        }

        const customAuthHeader = request.headers.get('x-auth-token');
        if (customAuthHeader) {
            return {
                token: customAuthHeader,
                type: 'user',
                expiresAt: this.parseJwtExpiry(customAuthHeader),
                issuedAt: this.parseJwtIssuedAt(customAuthHeader),
            };
        }

        const tokenCookie = request.cookies.get('auth-token');
        if (tokenCookie?.value) {
            return {
                token: tokenCookie.value,
                type: 'user',
                expiresAt: this.parseJwtExpiry(tokenCookie.value),
                issuedAt: this.parseJwtIssuedAt(tokenCookie.value),
            };
        }

        return null;
    }

    static isTokenExpired(tokenInfo: TokenInfo): boolean {
        if (!tokenInfo.expiresAt) {
            return false;
        }

        const now = Date.now();
        return now >= (tokenInfo.expiresAt - this.TOKEN_BUFFER_TIME);
    }

    static isTokenExpiringSoon(tokenInfo: TokenInfo, thresholdMs: number = 300000): boolean {
        if (!tokenInfo.expiresAt) {
            return false;
        }

        const now = Date.now();
        return (tokenInfo.expiresAt - now) < thresholdMs;
    }

    static parseJwtExpiry(token: string): number | null {
        try {
            const parts = token.split('.');
            if (parts.length !== 3) return null;

            const payload = JSON.parse(atob(parts[1]));
            return payload.exp ? payload.exp * 1000 : null;
        } catch {
            return null;
        }
    }

    static parseJwtIssuedAt(token: string): number | null {
        try {
            const parts = token.split('.');
            if (parts.length !== 3) return null;

            const payload = JSON.parse(atob(parts[1]));
            return payload.iat ? payload.iat * 1000 : null;
        } catch {
            return null;
        }
    }

    static parseJwtPayload(token: string): any | null {
        try {
            const parts = token.split('.');
            if (parts.length !== 3) return null;

            return JSON.parse(atob(parts[1]));
        } catch {
            return null;
        }
    }

    static getRequestMetadata(request: NextRequest) {
        return {
            ip: this.getClientIP(request),
            userAgent: request.headers.get('user-agent')?.substring(0, 200),
            referer: request.headers.get('referer'),
            origin: request.headers.get('origin'),
            method: request.method,
            pathname: request.nextUrl.pathname,
            timestamp: new Date().toISOString(),
        };
    }

    static getClientIP(request: NextRequest): string {
        const cfConnectingIP = request.headers.get('cf-connecting-ip');
        const xForwardedFor = request.headers.get('x-forwarded-for');
        const xRealIP = request.headers.get('x-real-ip');

        if (cfConnectingIP) return cfConnectingIP;
        if (xRealIP) return xRealIP;
        if (xForwardedFor) {
            const firstIP = xForwardedFor.split(',')[0]?.trim();
            if (firstIP) return firstIP;
        }

        return request.ip || '127.0.0.1';
    }

    static logSecurityEvent(
        type: 'auth_success' | 'auth_failure' | 'token_expired' | 'invalid_token' | 'admin_access' | 'suspicious_activity',
        request: NextRequest,
        details?: Record<string, any>
    ) {
        const metadata = this.getRequestMetadata(request);
        const logData = {
            type,
            ...metadata,
            ...details,
        };

        if (process.env.NODE_ENV === 'development') {
            console.log(`[Security Event] ${type}:`, logData);
        } else {
            console.log(JSON.stringify({ event: 'security', ...logData }));
        }
    }

    static createAuthHeaders(includeWWWAuthenticate: boolean = false): Record<string, string> {
        const headers: Record<string, string> = {
            'Content-Type': 'text/plain',
            'Cache-Control': 'no-store',
        };

        if (includeWWWAuthenticate) {
            headers['WWW-Authenticate'] = 'Bearer realm="API", charset="UTF-8"';
        }

        return headers;
    }

    static isProtectedAPIRoute(pathname: string): boolean {
        const protectedPatterns = [
            '/api/user',
            '/api/auth/logout',
            '/api/auth/refresh',
            '/api/auth/profile',
            '/api/orders',
            '/api/downloads',
            '/api/admin',
        ];

        return protectedPatterns.some(pattern => pathname.startsWith(pattern));
    }

    static isPublicAPIRoute(pathname: string): boolean {
        const publicPatterns = [
            '/api/auth/login',
            '/api/auth/register',
            '/api/auth/forgot-password',
            '/api/auth/reset-password',
            '/api/products',
            '/api/categories',
            '/api/search',
            '/api/health',
        ];

        return publicPatterns.some(pattern => pathname.startsWith(pattern));
    }

    static shouldBypassAuth(pathname: string): boolean {
        return this.isPublicAPIRoute(pathname) ||
            pathname.startsWith('/api/auth/') ||
            pathname === '/api/health' ||
            pathname === '/api/status';
    }
}

export default MiddlewareAuthUtils;