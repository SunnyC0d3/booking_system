class ClientTokenManager {
    private static instance: ClientTokenManager;
    private token: string | null = null;
    private expiresAt: number | null = null;
    private isRefreshing = false;
    private refreshPromise: Promise<string> | null = null;
    private readonly EXPIRY_BUFFER = 5 * 60 * 1000;
    private readonly MAX_RETRIES = 3;

    private constructor() {}

    static getInstance(): ClientTokenManager {
        if (!ClientTokenManager.instance) {
            ClientTokenManager.instance = new ClientTokenManager();
        }
        return ClientTokenManager.instance;
    }

    async getValidToken(): Promise<string> {
        if (this.isRefreshing && this.refreshPromise) {
            return this.refreshPromise;
        }

        if (this.token && this.expiresAt && Date.now() < this.expiresAt) {
            return this.token;
        }

        return this.refreshToken();
    }

    private async refreshToken(retryCount = 0): Promise<string> {
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

    private async performTokenRequest(retryCount: number): Promise<string> {
        try {
            const response = await fetch('/api/auth/client-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                cache: 'no-cache'
            });

            if (!response.ok) {
                throw new Error(`Client token request failed: ${response.status}`);
            }

            const data = await response.json();

            if (!data.access_token || !data.expires_in) {
                throw new Error('Invalid client token response format');
            }

            this.token = data.access_token;
            this.expiresAt = Date.now() + (data.expires_in * 1000) - this.EXPIRY_BUFFER;

            return this.token;

        } catch (error: any) {
            if (retryCount < this.MAX_RETRIES) {
                const delay = Math.min(1000 * Math.pow(2, retryCount), 5000);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.performTokenRequest(retryCount + 1);
            }

            throw error;
        }
    }
}

export const clientTokenManager = ClientTokenManager.getInstance();