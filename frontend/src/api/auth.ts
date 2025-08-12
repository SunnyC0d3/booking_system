import { api } from './client';
import type {
    LoginRequest,
    RegisterRequest,
    AuthResponse,
    RefreshTokenResponse,
    ForgotPasswordRequest,
    ResetPasswordRequest,
    PasswordResetResponse,
    ChangePasswordRequest,
    VerifyEmailRequest,
    User,
    UserPreferences,
    AuthApiError,
    SecurityInfoResponse
} from '@/types/auth';

const API_ENDPOINTS = {
    LOGIN: '/api/auth/login',
    REGISTER: '/api/auth/register',
    LOGOUT: '/api/auth/logout',
    REFRESH: '/api/auth/refresh',
    FORGOT_PASSWORD: '/api/auth/forgot-password',
    RESET_PASSWORD: '/api/auth/reset-password',
    CHANGE_PASSWORD: '/api/auth/change-password',
    VERIFY_EMAIL: '/api/auth/verify-email',
    RESEND_VERIFICATION: '/api/auth/resend-verification',
    USER_PROFILE: '/api/user',
    USER_PREFERENCES: '/api/user/preferences',
    SECURITY_INFO: '/api/security-info',
    VALIDATE_SESSION: '/api/auth/validate-session',
    HEALTH_CHECK: '/api/health',
} as const;

export class AuthApi {
    async login(credentials: LoginRequest): Promise<AuthResponse> {
        try {
            const response = await api.post<AuthResponse>(API_ENDPOINTS.LOGIN, credentials);

            if (!response.data?.access_token || !response.data?.user) {
                throw new Error('Invalid login response format');
            }

            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Login failed');
        }
    }

    async register(data: RegisterRequest): Promise<AuthResponse> {
        try {
            const response = await api.post<AuthResponse>(API_ENDPOINTS.REGISTER, data);

            if (!response.data?.access_token || !response.data?.user) {
                throw new Error('Invalid registration response format');
            }

            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Registration failed');
        }
    }

    async logout(): Promise<void> {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);

            await api.post(API_ENDPOINTS.LOGOUT, {}, {
                signal: controller.signal,
            });

            clearTimeout(timeoutId);
        } catch (error: any) {
            console.warn('Logout request failed:', error);

            if (error.name !== 'AbortError') {
                throw this.handleAuthError(error, 'Logout failed');
            }
        }
    }

    async refreshToken(refreshToken: string, retryCount = 0): Promise<RefreshTokenResponse> {
        const MAX_RETRIES = 2;

        try {
            // Use our server route for token refresh
            const response = await api.post<RefreshTokenResponse>(API_ENDPOINTS.REFRESH, {
                refresh_token: refreshToken,
            });

            if (!response.data?.access_token) {
                throw new Error('Invalid refresh token response');
            }

            return response.data;
        } catch (error: any) {
            if (retryCount < MAX_RETRIES && this.isRetryableError(error)) {
                await this.delay(1000 * (retryCount + 1));
                return this.refreshToken(refreshToken, retryCount + 1);
            }

            throw this.handleAuthError(error, 'Token refresh failed');
        }
    }

    async forgotPassword(data: ForgotPasswordRequest): Promise<{ message: string }> {
        try {
            const response = await api.post<{ message: string }>(API_ENDPOINTS.FORGOT_PASSWORD, data);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to send reset email');
        }
    }

    async resetPassword(data: ResetPasswordRequest): Promise<PasswordResetResponse> {
        try {
            const response = await api.post<PasswordResetResponse>(API_ENDPOINTS.RESET_PASSWORD, data);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Password reset failed');
        }
    }

    async changePassword(data: ChangePasswordRequest): Promise<{ message: string }> {
        try {
            const payload = {
                current_password: data.current_password,
                new_password: data.password,
                new_password_confirmation: data.password_confirmation,
            };

            const response = await api.post<{ message: string }>(API_ENDPOINTS.CHANGE_PASSWORD, payload);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to change password');
        }
    }

    async verifyEmail(data: VerifyEmailRequest): Promise<{ message: string }> {
        try {
            const params = new URLSearchParams({
                id: data.id,
                hash: data.hash,
                expires: data.expires.toString(),
                signature: data.signature,
            });

            const url = `${API_ENDPOINTS.VERIFY_EMAIL}?${params.toString()}`;

            const response = await api.get<{ message: string }>(url);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Email verification failed');
        }
    }

    async resendVerification(): Promise<{ message: string }> {
        try {
            const response = await api.post<{ message: string }>(API_ENDPOINTS.RESEND_VERIFICATION);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to resend verification email');
        }
    }

    async getProfile(bustCache = false): Promise<User> {
        try {
            const url = bustCache
                ? `${API_ENDPOINTS.USER_PROFILE}?cache_bust=${Date.now()}`
                : API_ENDPOINTS.USER_PROFILE;

            const response = await api.get<User>(url);

            if (!response.data?.id || !response.data?.email) {
                throw new Error('Invalid user profile data');
            }

            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to fetch user profile');
        }
    }

    async updatePreferences(preferences: Partial<UserPreferences>): Promise<UserPreferences> {
        try {
            if (!preferences || Object.keys(preferences).length === 0) {
                throw new Error('No preferences provided for update');
            }

            const response = await api.patch<UserPreferences>(API_ENDPOINTS.USER_PREFERENCES, preferences);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to update preferences');
        }
    }

    async getSecurityInfo(bustCache = false): Promise<SecurityInfoResponse> {
        try {
            const url = bustCache
                ? `${API_ENDPOINTS.SECURITY_INFO}?cache_bust=${Date.now()}`
                : API_ENDPOINTS.SECURITY_INFO;

            const response = await api.get<SecurityInfoResponse>(url);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to fetch security information');
        }
    }

    async validateSession(): Promise<boolean> {
        try {
            // Use our server route for session validation
            const response = await api.get(API_ENDPOINTS.VALIDATE_SESSION);
            return response.data?.valid === true;
        } catch {
            return false;
        }
    }

    async updateProfile(profileData: Partial<User>): Promise<User> {
        try {
            const response = await api.patch<User>(API_ENDPOINTS.USER_PROFILE, profileData);

            if (!response.data?.id) {
                throw new Error('Invalid profile update response');
            }

            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to update profile');
        }
    }

    async updateTwoFactorAuth(enabled: boolean): Promise<{ message: string; backup_codes?: string[] }> {
        try {
            const response = await api.post('/api/user/two-factor', { enabled });
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to update two-factor authentication');
        }
    }

    async getUserSessions(): Promise<{
        current_session: string;
        active_sessions: Array<{
            id: string;
            device: string;
            location: string;
            last_activity: string;
            is_current: boolean;
        }>;
    }> {
        try {
            const response = await api.get('/api/user/sessions');
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to fetch user sessions');
        }
    }

    async revokeSession(sessionId: string): Promise<{ message: string }> {
        try {
            const response = await api.delete(`/api/user/sessions/${sessionId}`);
            return response.data;
        } catch (error: any) {
            throw this.handleAuthError(error, 'Failed to revoke session');
        }
    }

    private handleAuthError(error: any, fallbackMessage: string): AuthApiError {
        const authError: AuthApiError = new Error(
            error?.message ||
            error?.response?.data?.message ||
            fallbackMessage
        );

        authError.code = error?.code || error?.response?.data?.code;
        authError.status = error?.status || error?.response?.status;
        authError.details = error?.errors || error?.response?.data?.errors;

        return authError;
    }

    private isRetryableError(error: any): boolean {
        return (
            !error.response ||
            error.code === 'NETWORK_ERROR' ||
            (error.status >= 500 && error.status < 600)
        );
    }

    private delay(ms: number): Promise<void> {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async healthCheck(): Promise<{ status: 'ok' | 'error'; timestamp: number }> {
        try {
            // Use our server route for health check
            const response = await api.get(API_ENDPOINTS.HEALTH_CHECK);
            return {
                status: 'ok',
                timestamp: Date.now(),
                ...response.data,
            };
        } catch (error) {
            return {
                status: 'error',
                timestamp: Date.now(),
            };
        }
    }
}

export const authApi = new AuthApi();

export const authApiMethods = {
    login: (credentials: LoginRequest) => authApi.login(credentials),
    register: (data: RegisterRequest) => authApi.register(data),
    logout: () => authApi.logout(),
    refreshToken: (token: string) => authApi.refreshToken(token),
    forgotPassword: (data: ForgotPasswordRequest) => authApi.forgotPassword(data),
    resetPassword: (data: ResetPasswordRequest) => authApi.resetPassword(data),
    changePassword: (data: ChangePasswordRequest) => authApi.changePassword(data),
    verifyEmail: (data: VerifyEmailRequest) => authApi.verifyEmail(data),
    resendVerification: () => authApi.resendVerification(),
    getProfile: (bustCache?: boolean) => authApi.getProfile(bustCache),
    updateProfile: (data: Partial<User>) => authApi.updateProfile(data),
    updatePreferences: (preferences: Partial<UserPreferences>) => authApi.updatePreferences(preferences),
    getSecurityInfo: (bustCache?: boolean) => authApi.getSecurityInfo(bustCache),
    validateSession: () => authApi.validateSession(),
    updateTwoFactorAuth: (enabled: boolean) => authApi.updateTwoFactorAuth(enabled),
    getUserSessions: () => authApi.getUserSessions(),
    revokeSession: (sessionId: string) => authApi.revokeSession(sessionId),
    healthCheck: () => authApi.healthCheck(),
} as const;