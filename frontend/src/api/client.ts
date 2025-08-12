import axios, {
    AxiosInstance,
    AxiosRequestConfig,
    AxiosResponse,
    InternalAxiosRequestConfig,
    CancelTokenSource,
} from 'axios';
import { toast } from 'sonner';
import { ApiResponse, ApiError } from '@/types/auth';

const API_CONFIG = {
    baseURL: '/',
    timeout: process.env.NODE_ENV === 'production' ? 15000 : 30000,
    withCredentials: true,
    maxRedirects: 3,
    validateStatus: (status: number) => status < 500,
} as const;

interface QueuedRequest {
    config: InternalAxiosRequestConfig;
    resolve: (value: AxiosResponse) => void;
    reject: (error: any) => void;
}

interface RequestMetadata {
    startTime: Date;
    requestId: string;
    retryCount: number;
}

// Token refresh state
const refreshState = {
    isRefreshing: false,
    failedQueue: [] as QueuedRequest[],
};

const generateRequestId = (): string => {
    return Date.now().toString(36) + Math.random().toString(36).substring(2);
};

const processQueue = (error: any = null, token: string | null = null) => {
    refreshState.failedQueue.forEach(({ config, resolve, reject }) => {
        if (error) {
            reject(error);
        } else if (token) {
            config.headers.Authorization = `Bearer ${token}`;
            resolve(apiClient(config));
        } else {
            reject(new Error('No token available'));
        }
    });

    refreshState.failedQueue = [];
};

// Get auth store function with error handling
const getAuthStore = () => {
    try {
        // Use dynamic import to avoid circular dependency
        const authStoreModule = require('@/stores/authStore');
        return authStoreModule.useAuthStore.getState();
    } catch (error) {
        console.warn('Auth store not available:', error);
        return null;
    }
};

const apiClient: AxiosInstance = axios.create(API_CONFIG);

// Request interceptor
apiClient.interceptors.request.use(
    async (config: InternalAxiosRequestConfig) => {
        try {
            const requestId = generateRequestId();
            config.metadata = {
                startTime: new Date(),
                requestId,
                retryCount: config.metadata?.retryCount || 0,
            };

            // Skip auth for auth endpoints to avoid circular dependency
            const isAuthEndpoint = config.url?.includes('/api/auth/') || false;

            if (!isAuthEndpoint) {
                const authStore = getAuthStore();

                if (authStore) {
                    let token: string | null = null;

                    // Try to get user token first if authenticated
                    if (authStore.isAuthenticated && authStore.isUserTokenValid()) {
                        token = authStore.getUserToken();
                    }
                    // If user token is expiring and we have a refresh token, try to refresh
                    else if (authStore.isAuthenticated && authStore.isUserTokenExpiring() && authStore.userToken?.refreshToken) {
                        try {
                            await authStore.refreshAuthToken();
                            token = authStore.getUserToken();
                        } catch (error) {
                            console.warn('Token refresh failed in interceptor:', error);
                        }
                    }
                    // For public endpoints, try client token
                    else if (!authStore.isAuthenticated) {
                        try {
                            token = await authStore.ensureValidClientToken();
                        } catch (error) {
                            console.warn('Client token unavailable:', error);
                        }
                    }

                    if (token) {
                        config.headers.Authorization = `Bearer ${token}`;
                    }
                }
            }

            // Add CSRF token if available
            if (typeof document !== 'undefined') {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    config.headers['X-CSRF-TOKEN'] = csrfToken;
                }
            }

            config.headers['X-Request-ID'] = requestId;
            config.headers['Content-Type'] = config.headers['Content-Type'] || 'application/json';
            config.headers['Accept'] = config.headers['Accept'] || 'application/json';

            return config;
        } catch (error) {
            console.error('Request interceptor error:', error);
            return config;
        }
    },
    (error) => Promise.reject(error)
);

// Response interceptor
apiClient.interceptors.response.use(
    (response: AxiosResponse) => {
        if (process.env.NODE_ENV === 'development' && response.config.metadata) {
            const { startTime, requestId } = response.config.metadata;
            const duration = new Date().getTime() - startTime.getTime();
            console.log(
                `[${requestId}] ${response.config.method?.toUpperCase()} ${response.config.url}: ${duration}ms (${response.status})`
            );
        }

        return response;
    },
    async (error) => {
        const originalRequest = error.config;
        const maxRetries = 3;

        if (!originalRequest || originalRequest._retry) {
            return Promise.reject(error);
        }

        if (error.response?.status === 401) {
            originalRequest._retry = true;

            if (refreshState.isRefreshing) {
                return new Promise((resolve, reject) => {
                    refreshState.failedQueue.push({
                        config: originalRequest,
                        resolve,
                        reject,
                    });
                });
            }

            refreshState.isRefreshing = true;

            try {
                const authStore = getAuthStore();

                if (authStore) {
                    // Try to refresh user token first
                    if (authStore.isAuthenticated && authStore.userToken?.refreshToken) {
                        try {
                            const newToken = await authStore.refreshAuthToken();
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                            processQueue(null, newToken);
                            return apiClient(originalRequest);
                        } catch (refreshError) {
                            console.warn('User token refresh failed:', refreshError);
                            await authStore.logout(true);
                        }
                    }

                    // Fall back to client token for public routes
                    if (!isProtectedRoute(originalRequest.url)) {
                        try {
                            const clientToken = await authStore.ensureValidClientToken();
                            originalRequest.headers.Authorization = `Bearer ${clientToken}`;
                            processQueue(null, clientToken);
                            return apiClient(originalRequest);
                        } catch (clientError) {
                            processQueue(clientError, null);
                        }
                    } else {
                        processQueue(new Error('Authentication required'), null);
                        redirectToLogin();
                    }
                }
            } finally {
                refreshState.isRefreshing = false;
            }
        }

        // Retry logic for network errors
        if (isRetryableError(error) &&
            originalRequest.metadata?.retryCount < maxRetries) {

            const retryCount = (originalRequest.metadata?.retryCount || 0) + 1;
            const delay = Math.min(1000 * Math.pow(2, retryCount - 1), 5000);

            await new Promise(resolve => setTimeout(resolve, delay));

            originalRequest.metadata = {
                ...originalRequest.metadata,
                retryCount,
            };

            return apiClient(originalRequest);
        }

        // Handle network errors
        if (!error.response) {
            const networkError = new Error('Network error');
            if (!hasShownNetworkError) {
                toast.error('Network error. Please check your connection.');
                hasShownNetworkError = true;
                setTimeout(() => {
                    hasShownNetworkError = false;
                }, 5000);
            }
            return Promise.reject(networkError);
        }

        const apiError: ApiError = {
            message: error.response?.data?.message || 'An unexpected error occurred',
            errors: error.response?.data?.errors,
            status: error.response?.status,
            code: error.response?.data?.code,
        };

        if (error.response?.status !== 422 && !isSilentRoute(originalRequest.url)) {
            toast.error(apiError.message);
        }

        return Promise.reject(apiError);
    }
);

// Utility functions
let hasShownNetworkError = false;

const isRetryableError = (error: any): boolean => {
    if (axios.isCancel(error)) return false;

    return (
        !error.response ||
        error.code === 'NETWORK_ERROR' ||
        error.code === 'ECONNABORTED' ||
        (error.response?.status >= 500 && error.response?.status < 600) ||
        error.response?.status === 429
    );
};

const isProtectedRoute = (url?: string): boolean => {
    if (!url) return false;

    const protectedPatterns = [
        '/auth/',
        '/user',
        '/my-',
        '/profile',
        '/dashboard',
        '/account',
    ];

    return protectedPatterns.some(pattern => url.includes(pattern));
};

const isSilentRoute = (url?: string): boolean => {
    if (!url) return false;

    const silentPatterns = [
        '/health',
        '/ping',
        '/validate-session',
    ];

    return silentPatterns.some(pattern => url.includes(pattern));
};

const redirectToLogin = (): void => {
    if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
        const currentPath = window.location.pathname + window.location.search;
        const loginUrl = `/login?redirect=${encodeURIComponent(currentPath)}`;
        window.location.href = loginUrl;
    }
};

export class ApiClient {
    private client: AxiosInstance;
    private cancelTokens = new Map<string, CancelTokenSource>();

    constructor() {
        this.client = apiClient;
    }

    private async request<T = any>(
        config: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        try {
            if (config.requestKey) {
                this.cancelPreviousRequest(config.requestKey);

                const cancelTokenSource = axios.CancelToken.source();
                config.cancelToken = cancelTokenSource.token;
                this.cancelTokens.set(config.requestKey, cancelTokenSource);
            }

            const response = await this.client.request<ApiResponse<T>>(config);

            if (config.requestKey) {
                this.cancelTokens.delete(config.requestKey);
            }

            return response.data;
        } catch (error) {
            if (config.requestKey) {
                this.cancelTokens.delete(config.requestKey);
            }

            if (axios.isCancel(error)) {
                throw new Error('Request cancelled');
            }

            throw this.handleError(error);
        }
    }

    async get<T = any>(
        url: string,
        config?: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'GET', url });
    }

    async post<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'POST', url, data });
    }

    async put<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'PUT', url, data });
    }

    async patch<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'PATCH', url, data });
    }

    async delete<T = any>(
        url: string,
        config?: AxiosRequestConfig & { requestKey?: string }
    ): Promise<ApiResponse<T>> {
        return this.request<T>({ ...config, method: 'DELETE', url });
    }

    async upload<T = any>(
        url: string,
        file: File | File[],
        onProgress?: (progress: number) => void,
        config?: AxiosRequestConfig & {
            requestKey?: string;
            fieldName?: string;
            additionalFields?: Record<string, any>;
        }
    ): Promise<ApiResponse<T>> {
        const formData = new FormData();

        if (Array.isArray(file)) {
            file.forEach((f, index) => {
                formData.append(`${config?.fieldName || 'files'}[${index}]`, f);
            });
        } else {
            formData.append(config?.fieldName || 'file', file);
        }

        if (config?.additionalFields) {
            Object.entries(config.additionalFields).forEach(([key, value]) => {
                formData.append(key, typeof value === 'string' ? value : JSON.stringify(value));
            });
        }

        return this.request<T>({
            ...config,
            method: 'POST',
            url,
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
                ...config?.headers,
            },
            timeout: 120000,
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

    async downloadFile(
        url: string,
        filename?: string,
        onProgress?: (progress: number) => void,
        config?: AxiosRequestConfig
    ): Promise<Blob> {
        try {
            const response = await this.client.request({
                ...config,
                method: 'GET',
                url,
                responseType: 'blob',
                timeout: 300000,
                onDownloadProgress: (progressEvent) => {
                    if (onProgress && progressEvent.total) {
                        const progress = Math.round(
                            (progressEvent.loaded * 100) / progressEvent.total
                        );
                        onProgress(progress);
                    }
                },
            });

            if (filename && typeof window !== 'undefined') {
                const blob = new Blob([response.data]);
                const downloadUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(downloadUrl);
            }

            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    cancelRequest(requestKey: string): void {
        this.cancelPreviousRequest(requestKey);
    }

    cancelAllRequests(): void {
        this.cancelTokens.forEach((source) => {
            source.cancel('Request cancelled by user');
        });
        this.cancelTokens.clear();
    }

    private cancelPreviousRequest(requestKey: string): void {
        const existingRequest = this.cancelTokens.get(requestKey);
        if (existingRequest) {
            existingRequest.cancel(`Request ${requestKey} superseded`);
            this.cancelTokens.delete(requestKey);
        }
    }

    private handleError(error: any): ApiError {
        if (axios.isCancel(error)) {
            return {
                message: 'Request was cancelled',
                code: 'CANCELLED',
            };
        }

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
                code: 'NETWORK_ERROR',
            };
        }

        return {
            message: error.message || 'An unexpected error occurred',
            code: 'UNKNOWN_ERROR',
        };
    }

    // Legacy token management methods (for backward compatibility)
    setTokens(accessToken: string, refreshToken?: string): void {
        console.warn('api.setTokens() is deprecated. Tokens are managed by auth store.');
    }

    clearTokens(): void {
        const authStore = getAuthStore();
        if (authStore) {
            authStore.clearAllTokens();
        }
        this.cancelAllRequests();
    }

    hasValidToken(): boolean {
        const authStore = getAuthStore();
        if (!authStore) return false;

        return authStore.isUserTokenValid() || authStore.isClientTokenValid();
    }

    getAccessToken(): string | null {
        const authStore = getAuthStore();
        if (!authStore) return null;

        return authStore.getUserToken() || authStore.getClientToken();
    }

    getRequestMetrics(): {
        activeRequests: number;
        queuedRequests: number;
        isRefreshing: boolean;
    } {
        return {
            activeRequests: this.cancelTokens.size,
            queuedRequests: refreshState.failedQueue.length,
            isRefreshing: refreshState.isRefreshing,
        };
    }

    async healthCheck(): Promise<{ status: 'ok' | 'error'; latency: number }> {
        const startTime = Date.now();

        try {
            await this.get('/api/health', { requestKey: 'health-check' });
            return {
                status: 'ok',
                latency: Date.now() - startTime,
            };
        } catch {
            return {
                status: 'error',
                latency: Date.now() - startTime,
            };
        }
    }
}

export const api = new ApiClient();
export { apiClient };

declare module 'axios' {
    interface InternalAxiosRequestConfig {
        metadata?: {
            startTime: Date;
            requestId: string;
            retryCount: number;
        };
        _retry?: boolean;
    }
}