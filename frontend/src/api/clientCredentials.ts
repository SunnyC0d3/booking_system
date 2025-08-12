import axios, { AxiosInstance } from 'axios';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

const clientCredentialsClient: AxiosInstance = axios.create({
    timeout: 15000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    maxRedirects: 0,
});

export class ClientCredentialsApi {
    private static readonly CLIENT_TOKEN_KEY = 'client_token';
    private static readonly CLIENT_TOKEN_EXPIRY_KEY = 'client_token_expiry';
    private static readonly EXPIRY_BUFFER = 300000; // 5 minutes buffer
    private static readonly MAX_RETRY_ATTEMPTS = 3;

    private static clientToken: string | null = null;
    private static clientTokenExpiry: number | null = null;
    private static isRefreshing = false;
    private static refreshPromise: Promise<string> | null = null;

    static async getClientToken(): Promise<string> {
        if (this.isRefreshing && this.refreshPromise) {
            return this.refreshPromise;
        }

        if (this.clientToken && this.clientTokenExpiry && Date.now() < this.clientTokenExpiry) {
            return this.clientToken;
        }

        const stored = this.getStoredToken();
        if (stored.token && stored.expiry && Date.now() < stored.expiry) {
            this.clientToken = stored.token;
            this.clientTokenExpiry = stored.expiry;
            return stored.token;
        }

        return this.requestClientCredentials();
    }

    private static getStoredToken(): { token: string | null; expiry: number | null } {
        if (typeof window === 'undefined') {
            return { token: null, expiry: null };
        }

        const token = localStorage.getItem(this.CLIENT_TOKEN_KEY);
        const expiryStr = localStorage.getItem(this.CLIENT_TOKEN_EXPIRY_KEY);
        const expiry = expiryStr ? parseInt(expiryStr, 10) : null;

        return { token, expiry };
    }

    private static setStoredToken(token: string, expiry: number): void {
        if (typeof window === 'undefined') return;

        try {
            localStorage.setItem(this.CLIENT_TOKEN_KEY, token);
            localStorage.setItem(this.CLIENT_TOKEN_EXPIRY_KEY, expiry.toString());
        } catch (error) {
            console.warn('Failed to store client token:', error);
        }
    }

    private static async requestClientCredentials(retryCount = 0): Promise<string> {
        if (this.isRefreshing && this.refreshPromise) {
            return this.refreshPromise;
        }

        this.isRefreshing = true;
        this.refreshPromise = this.performTokenRequest(retryCount);

        try {
            const token = await this.refreshPromise;
            return token;
        } finally {
            this.isRefreshing = false;
            this.refreshPromise = null;
        }
    }

    private static async performTokenRequest(retryCount: number): Promise<string> {
        try {
            // Call our server-side API route instead of directly calling the OAuth endpoint
            const response = await clientCredentialsClient.post<ClientCredentialsResponse>(
                '/api/auth/client-token',
                {},
                {
                    timeout: 10000,
                }
            );

            const data = response.data;

            if (!data.access_token || !data.expires_in) {
                throw new Error('Invalid token response format');
            }

            this.clientToken = data.access_token;
            this.clientTokenExpiry = Date.now() + (data.expires_in * 1000) - this.EXPIRY_BUFFER;

            this.setStoredToken(this.clientToken, this.clientTokenExpiry);

            return this.clientToken;

        } catch (error: any) {
            if (retryCount < this.MAX_RETRY_ATTEMPTS && this.isRetryableError(error)) {
                const delay = Math.min(1000 * Math.pow(2, retryCount), 5000);
                await this.delay(delay);
                return this.performTokenRequest(retryCount + 1);
            }

            const errorMessage = this.extractErrorMessage(error);
            const enhancedError = new Error(`Client credentials authentication failed: ${errorMessage}`);

            console.error('Client credentials error:', {
                message: errorMessage,
                status: error.response?.status,
                retryCount,
                timestamp: new Date().toISOString(),
            });

            throw enhancedError;
        }
    }

    private static extractErrorMessage(error: any): string {
        return (
            error.response?.data?.error ||
            error.response?.data?.message ||
            error.message ||
            'Unknown authentication error'
        );
    }

    private static isRetryableError(error: any): boolean {
        if (!error.response) return true;

        const status = error.response.status;
        return status >= 500 || status === 429 || status === 408;
    }

    private static delay(ms: number): Promise<void> {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    static clearClientCredentials(): void {
        this.clientToken = null;
        this.clientTokenExpiry = null;

        if (typeof window !== 'undefined') {
            try {
                localStorage.removeItem(this.CLIENT_TOKEN_KEY);
                localStorage.removeItem(this.CLIENT_TOKEN_EXPIRY_KEY);
            } catch (error) {
                console.warn('Failed to clear stored client credentials:', error);
            }
        }
    }

    static async refreshClientCredentials(): Promise<string> {
        this.clearClientCredentials();
        return this.requestClientCredentials();
    }

    static hasValidClientToken(): boolean {
        if (this.clientToken && this.clientTokenExpiry && Date.now() < this.clientTokenExpiry) {
            return true;
        }

        const stored = this.getStoredToken();
        if (stored.token && stored.expiry && Date.now() < stored.expiry) {
            this.clientToken = stored.token;
            this.clientTokenExpiry = stored.expiry;
            return true;
        }

        return false;
    }

    static validateConfiguration(): { valid: boolean; errors: string[] } {
        // Since we're using server route, we can't validate client credentials here
        // The validation happens on the server side
        return {
            valid: true,
            errors: [],
        };
    }

    static getTokenInfo(): {
        hasToken: boolean;
        expiresAt: Date | null;
        expiresIn: number | null;
        isExpiringSoon: boolean;
    } {
        const expiry = this.clientTokenExpiry || this.getStoredToken().expiry;

        return {
            hasToken: Boolean(this.clientToken || this.getStoredToken().token),
            expiresAt: expiry ? new Date(expiry) : null,
            expiresIn: expiry ? Math.max(0, expiry - Date.now()) : null,
            isExpiringSoon: expiry ? (expiry - Date.now()) < (10 * 60 * 1000) : false, // 10 minutes
        };
    }

    static async ensureValidToken(): Promise<string> {
        const tokenInfo = this.getTokenInfo();

        if (!tokenInfo.hasToken || tokenInfo.isExpiringSoon) {
            return this.refreshClientCredentials();
        }

        return this.getClientToken();
    }

    static async testConnection(): Promise<{ success: boolean; latency: number; error?: string }> {
        const startTime = Date.now();

        try {
            // Test our server route instead of direct OAuth endpoint
            await clientCredentialsClient.post('/api/auth/client-token', {}, {
                timeout: 5000,
            });

            return {
                success: true,
                latency: Date.now() - startTime,
            };
        } catch (error: any) {
            return {
                success: false,
                latency: Date.now() - startTime,
                error: this.extractErrorMessage(error),
            };
        }
    }
}

export const clientCredentials = {
    getToken: () => ClientCredentialsApi.getClientToken(),
    refreshToken: () => ClientCredentialsApi.refreshClientCredentials(),
    clearToken: () => ClientCredentialsApi.clearClientCredentials(),
    hasValidToken: () => ClientCredentialsApi.hasValidClientToken(),
    validateConfig: () => ClientCredentialsApi.validateConfiguration(),
    getTokenInfo: () => ClientCredentialsApi.getTokenInfo(),
    ensureValidToken: () => ClientCredentialsApi.ensureValidToken(),
    testConnection: () => ClientCredentialsApi.testConnection(),
} as const;

export type { ClientCredentialsResponse };