import axios, { AxiosInstance } from 'axios';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

const clientCredentialsClient: AxiosInstance = axios.create({
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

export class ClientCredentialsApi {
    private static readonly CLIENT_TOKEN_KEY = 'client_token';
    private static readonly CLIENT_TOKEN_EXPIRY_KEY = 'client_token_expiry';
    private static clientToken: string | null = null;
    private static clientTokenExpiry: number | null = null;

    private static readonly CLIENT_ID = process.env.API_CLIENT_ID || '';
    private static readonly CLIENT_SECRET = process.env.API_SECRET_KEY || '';

    static async getClientToken(): Promise<string> {
        if (this.clientToken && this.clientTokenExpiry && Date.now() < this.clientTokenExpiry) {
            return this.clientToken;
        }

        if (typeof window !== 'undefined') {
            const storedToken = localStorage.getItem(this.CLIENT_TOKEN_KEY);
            const storedExpiry = localStorage.getItem(this.CLIENT_TOKEN_EXPIRY_KEY);

            if (storedToken && storedExpiry && Date.now() < parseInt(storedExpiry)) {
                this.clientToken = storedToken;
                this.clientTokenExpiry = parseInt(storedExpiry);
                return storedToken;
            }
        }

        return this.requestClientCredentials();
    }

    private static async requestClientCredentials(): Promise<string> {
        try {
            const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

            const response = await clientCredentialsClient.post<ClientCredentialsResponse>(
                `${baseUrl}/oauth/token`,
                {
                    grant_type: 'client_credentials',
                    client_id: this.CLIENT_ID,
                    client_secret: this.CLIENT_SECRET,
                    scope: '',
                }
            );

            const data = response.data;

            this.clientToken = data.access_token;
            this.clientTokenExpiry = Date.now() + (data.expires_in * 1000) - 300000;

            if (typeof window !== 'undefined') {
                localStorage.setItem(this.CLIENT_TOKEN_KEY, this.clientToken);
                localStorage.setItem(this.CLIENT_TOKEN_EXPIRY_KEY, this.clientTokenExpiry.toString());
            }

            return this.clientToken;
        } catch (error: any) {
            console.error('Failed to get client credentials token:', error);

            const errorMessage = error.response?.data?.error_description ||
                error.response?.data?.message ||
                error.message ||
                'Unknown error';

            throw new Error(`Unable to authenticate with API: ${errorMessage}`);
        }
    }

    static clearClientCredentials(): void {
        this.clientToken = null;
        this.clientTokenExpiry = null;

        if (typeof window !== 'undefined') {
            localStorage.removeItem(this.CLIENT_TOKEN_KEY);
            localStorage.removeItem(this.CLIENT_TOKEN_EXPIRY_KEY);
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

        if (typeof window !== 'undefined') {
            const storedToken = localStorage.getItem(this.CLIENT_TOKEN_KEY);
            const storedExpiry = localStorage.getItem(this.CLIENT_TOKEN_EXPIRY_KEY);

            if (storedToken && storedExpiry && Date.now() < parseInt(storedExpiry)) {
                this.clientToken = storedToken;
                this.clientTokenExpiry = parseInt(storedExpiry);
                return true;
            }
        }

        return false;
    }

    static getClientAuthHeader(): string {
        const credentials = btoa(`${this.CLIENT_ID}:${this.CLIENT_SECRET}`);
        return `Basic ${credentials}`;
    }

    static validateConfiguration(): boolean {
        if (!this.CLIENT_ID || !this.CLIENT_SECRET) {
            console.error('Client credentials not configured. Please set API_CLIENT_ID and API_SECRET_KEY in your environment variables.');
            return false;
        }
        return true;
    }
}

export const clientCredentials = {
    getToken: () => ClientCredentialsApi.getClientToken(),
    refreshToken: () => ClientCredentialsApi.refreshClientCredentials(),
    clearToken: () => ClientCredentialsApi.clearClientCredentials(),
    hasValidToken: () => ClientCredentialsApi.hasValidClientToken(),
    getAuthHeader: () => ClientCredentialsApi.getClientAuthHeader(),
    validateConfig: () => ClientCredentialsApi.validateConfiguration()
};