import { NextRequest, NextResponse } from 'next/server';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

interface ClientCredentialsRequest {
    grant_type: 'client_credentials';
    client_id: string;
    client_secret: string;
    scope: string;
}

interface TokenCacheEntry {
    token: string;
    expiresAt: number;
    issuedAt: number;
    scope: string;
}

class TokenCache {
    private static instance: TokenCache;
    private cache: Map<string, TokenCacheEntry> = new Map();
    private pendingRequests: Map<string, Promise<TokenCacheEntry>> = new Map();
    private readonly REFRESH_BUFFER_MS = 300000;

    static getInstance(): TokenCache {
        if (!TokenCache.instance) {
            TokenCache.instance = new TokenCache();
        }
        return TokenCache.instance;
    }

    private getCacheKey(clientId: string, scope: string): string {
        return `${clientId}:${scope}`;
    }

    isTokenValid(entry: TokenCacheEntry): boolean {
        const now = Date.now();
        return now < (entry.expiresAt - this.REFRESH_BUFFER_MS);
    }

    getToken(clientId: string, scope: string = ''): TokenCacheEntry | null {
        const key = this.getCacheKey(clientId, scope);
        const entry = this.cache.get(key);

        if (!entry || !this.isTokenValid(entry)) {
            return null;
        }

        return entry;
    }

    setToken(clientId: string, tokenData: ClientCredentialsResponse): TokenCacheEntry {
        const now = Date.now();
        const entry: TokenCacheEntry = {
            token: tokenData.access_token,
            expiresAt: now + (tokenData.expires_in * 1000),
            issuedAt: now,
            scope: tokenData.scope || ''
        };

        const key = this.getCacheKey(clientId, tokenData.scope || '');
        this.cache.set(key, entry);

        return entry;
    }

    async getOrFetchToken(
        clientId: string,
        clientSecret: string,
        scope: string = '',
        fetcher: () => Promise<ClientCredentialsResponse>
    ): Promise<TokenCacheEntry> {
        const cachedToken = this.getToken(clientId, scope);
        if (cachedToken) {
            return cachedToken;
        }

        const key = this.getCacheKey(clientId, scope);

        const pendingRequest = this.pendingRequests.get(key);
        if (pendingRequest) {
            return pendingRequest;
        }

        const tokenPromise = this.fetchAndCacheToken(clientId, scope, fetcher);
        this.pendingRequests.set(key, tokenPromise);

        try {
            const result = await tokenPromise;
            return result;
        } finally {
            this.pendingRequests.delete(key);
        }
    }

    private async fetchAndCacheToken(
        clientId: string,
        scope: string,
        fetcher: () => Promise<ClientCredentialsResponse>
    ): Promise<TokenCacheEntry> {
        try {
            const tokenData = await fetcher();
            return this.setToken(clientId, tokenData);
        } catch (error) {
            throw error;
        }
    }

    cleanupExpiredTokens(): void {
        const now = Date.now();
        for (const [key, entry] of this.cache.entries()) {
            if (now >= entry.expiresAt) {
                this.cache.delete(key);
            }
        }
    }
}

const tokenCache = TokenCache.getInstance();

if (typeof globalThis !== 'undefined' && !globalThis.__tokenCleanupInterval) {
    globalThis.__tokenCleanupInterval = setInterval(() => {
        tokenCache.cleanupExpiredTokens();
    }, 600000);
}

export async function POST(request: NextRequest) {
    try {
        const clientId = process.env.API_CLIENT_ID;
        const clientSecret = process.env.API_SECRET_KEY;
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!clientId || !clientSecret) {
            return NextResponse.json(
                {
                    error: 'Client credentials not configured',
                    code: 'MISSING_CREDENTIALS'
                },
                { status: 500 }
            );
        }

        const fetchToken = async (): Promise<ClientCredentialsResponse> => {
            const credentials = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');

            const requestBody: ClientCredentialsRequest = {
                grant_type: 'client_credentials',
                client_id: clientId,
                client_secret: clientSecret,
                scope: '',
            };

            const response = await fetch(`${baseUrl}/oauth/token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Basic ${credentials}`,
                },
                body: JSON.stringify(requestBody),
                signal: AbortSignal.timeout(15000),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));

                const errorMessage = errorData.error_description ||
                    errorData.message ||
                    errorData.error ||
                    `HTTP ${response.status}: ${response.statusText}`;

                const error = new Error(errorMessage);
                (error as any).status = response.status;
                (error as any).code = errorData.error || 'CLIENT_CREDENTIALS_FAILED';
                throw error;
            }

            const tokenData: ClientCredentialsResponse = await response.json();

            if (!tokenData.access_token || !tokenData.expires_in) {
                const error = new Error('Invalid token response from OAuth server');
                (error as any).status = 502;
                (error as any).code = 'INVALID_TOKEN_RESPONSE';
                throw error;
            }

            return tokenData;
        };

        const tokenEntry = await tokenCache.getOrFetchToken(
            clientId,
            clientSecret,
            '',
            fetchToken
        );

        const remainingSeconds = Math.floor((tokenEntry.expiresAt - Date.now()) / 1000);

        return NextResponse.json({
            access_token: tokenEntry.token,
            expires_in: remainingSeconds,
            token_type: 'Bearer' as const,
            scope: tokenEntry.scope,
            issued_at: Math.floor(tokenEntry.issuedAt / 1000),
            from_cache: Date.now() > tokenEntry.issuedAt + 1000,
        });

    } catch (error: any) {
        if (error.name === 'TimeoutError' || error.name === 'AbortError') {
            return NextResponse.json(
                {
                    error: 'OAuth server timeout',
                    code: 'OAUTH_TIMEOUT'
                },
                { status: 408 }
            );
        }

        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            return NextResponse.json(
                {
                    error: 'Unable to connect to OAuth server',
                    code: 'OAUTH_CONNECTION_FAILED'
                },
                { status: 503 }
            );
        }

        if (error.status && error.code) {
            return NextResponse.json(
                {
                    error: error.message,
                    code: error.code
                },
                { status: error.status }
            );
        }

        console.error('OAuth token error:', error);
        return NextResponse.json(
            {
                error: error.message || 'Authentication service unavailable',
                code: 'INTERNAL_ERROR'
            },
            { status: 500 }
        );
    }
}