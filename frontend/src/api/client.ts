import axios, {
    AxiosInstance,
    AxiosRequestConfig,
    AxiosResponse,
    InternalAxiosRequestConfig
} from 'axios';
import { toast } from 'sonner';
import { ApiResponse, ApiError } from '@/types/auth';

// API Configuration
const API_CONFIG = {
    baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
    timeout: 30000,
    withCredentials: true,
} as const;

// Create axios instance
const apiClient: AxiosInstance = axios.create(API_CONFIG);

// Token management
class TokenManager {
    private static readonly ACCESS_TOKEN_KEY = 'access_token';
    private static readonly REFRESH_TOKEN_KEY = 'refresh_token';

    static getAccessToken(): string | null {
        if (typeof window === 'undefined') return null;
        return localStorage.getItem(this.ACCESS_TOKEN_KEY);
    }

    static getRefreshToken(): string | null {
        if (typeof window === 'undefined') return null;
        return localStorage.getItem(this.REFRESH_TOKEN_KEY);
    }

    static setTokens(accessToken: string, refreshToken?: string): void {
        if (typeof window === 'undefined') return;

        localStorage.setItem(this.ACCESS_TOKEN_KEY, accessToken);
        if (refreshToken) {
            localStorage.setItem(this.REFRESH_TOKEN_KEY, refreshToken);
        }
    }

    static clearTokens(): void {
        if (typeof window === 'undefined') return;

        localStorage.removeItem(this.ACCESS_TOKEN_KEY);
        localStorage.removeItem(this.REFRESH_TOKEN_KEY);
    }

    static hasValidToken(): boolean {
        const token = this.getAccessToken();
        if (!token) return false;

        try {
            // Check if token is expired (basic JWT parsing)
            const parts = token.split('.');
            if (parts.length !== 3 || !parts[1]) return false;

            const payload = JSON.parse(atob(parts[1]));
            const currentTime = Date.now() / 1000;
            return payload.exp > currentTime;
        } catch {
            return false;
        }
    }
}

// Request interceptor
apiClient.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        // Add auth token
        const token = TokenManager.getAccessToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Add CSRF token if available
        if (typeof document !== 'undefined') {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                config.headers['X-CSRF-TOKEN'] = csrfToken;
            }
        }

        // Add request timestamp
        config.metadata = { startTime: new Date() };

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor
apiClient.interceptors.response.use(
    (response: AxiosResponse) => {
        // Log response time in development
        if (process.env.NODE_ENV === 'development' && response.config.metadata) {
            const endTime = new Date();
            const duration = endTime.getTime() - response.config.metadata.startTime.getTime();
            console.log(`API ${response.config.method?.toUpperCase()} ${response.config.url}: ${duration}ms`);
        }

        return response;
    },
    async (error) => {
        const originalRequest = error.config;

        // Handle 401 Unauthorized
        if (error.response?.status === 401 && !originalRequest._retry) {
            originalRequest._retry = true;

            try {
                const refreshToken = TokenManager.getRefreshToken();
                if (refreshToken) {
                    const response = await axios.post(
                        `${API_CONFIG.baseURL}/auth/refresh`,
                        { refresh_token: refreshToken }
                    );

                    const { access_token, refresh_token: newRefreshToken } = response.data;
                    TokenManager.setTokens(access_token, newRefreshToken);

                    // Retry original request
                    originalRequest.headers.Authorization = `Bearer ${access_token}`;
                    return apiClient(originalRequest);
                }
            } catch (refreshError) {
                // Refresh failed, redirect to login
                TokenManager.clearTokens();
                if (typeof window !== 'undefined') {
                    window.location.href = '/login';
                }
                return Promise.reject(refreshError);
            }
        }

        // Handle network errors
        if (!error.response) {
            toast.error('Network error. Please check your connection.');
            return Promise.reject(new Error('Network error'));
        }

        // Handle API errors
        const apiError: ApiError = {
            message: error.response?.data?.message || 'An unexpected error occurred',
            errors: error.response?.data?.errors,
            status: error.response?.status,
            code: error.response?.data?.code,
        };

        // Show error toast for non-validation errors
        if (error.response?.status !== 422) {
            toast.error(apiError.message);
        }

        return Promise.reject(apiError);
    }
);

// API wrapper class
export class ApiClient {
    private client: AxiosInstance;

    constructor() {
        this.client = apiClient;
    }

    // Generic request method
    private async request<T = any>(
        config: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        try {
            const response = await this.client.request<ApiResponse<T>>(config);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    // HTTP methods
    async get<T = any>(
        url: string,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'GET', url });
    }

    async post<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'POST', url, data });
    }

    async put<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'PUT', url, data });
    }

    async patch<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'PATCH', url, data });
    }

    async delete<T = any>(
        url: string,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'DELETE', url });
    }

    // File upload
    async upload<T = any>(
        url: string,
        file: File,
        onProgress?: (progress: number) => void,
        config?: AxiosRequestConfig
    ): Promise<ApiResponse<T>> {
        const formData = new FormData();
        formData.append('file', file);

        return this.request<T>({
            ...config,
            method: 'POST',
            url,
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
                ...config?.headers,
            },
            onUploadProgress: (progressEvent) => {
                if (onProgress && progressEvent.total) {
                    const progress = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total
                    );
                    onProgress(progress);
                }
            },
        });
    }

    // Error handler
    private handleError(error: any): ApiError {
        if (error.response) {
            return {
                message: error.response.data?.message || 'Server error',
                errors: error.response.data?.errors,
                status: error.response.status,
                code: error.response.data?.code,
            };
        }

        if (error.request) {
            return {
                message: 'Network error. Please check your connection.',
                status: 0,
            };
        }

        return {
            message: error.message || 'An unexpected error occurred',
        };
    }

    // Token methods
    setTokens(accessToken: string, refreshToken?: string): void {
        TokenManager.setTokens(accessToken, refreshToken);
    }

    clearTokens(): void {
        TokenManager.clearTokens();
    }

    hasValidToken(): boolean {
        return TokenManager.hasValidToken();
    }

    getAccessToken(): string | null {
        return TokenManager.getAccessToken();
    }
}

// Export singleton instance
export const api = new ApiClient();

// Export token manager for direct access
export { TokenManager };

// Export axios instance for custom requests
export { apiClient };

// Declare module for axios metadata
declare module 'axios' {
    interface InternalAxiosRequestConfig {
        metadata?: {
            startTime: Date;
        };
        _retry?: boolean;
    }
}