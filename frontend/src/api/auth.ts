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
    SecurityEvent,
    SessionInfo,
    ApiResponse
} from '@/types/auth';

/**
 * Authentication API functions
 */
export class AuthApi {
    // Authentication endpoints
    async login(credentials: LoginRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/auth/login', credentials);
        return response.data;
    }

    async register(data: RegisterRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/auth/register', data);
        return response.data;
    }

    async logout(): Promise<void> {
        await api.post('/auth/logout');
    }

    async refreshToken(refreshToken: string): Promise<RefreshTokenResponse> {
        const response = await api.post<RefreshTokenResponse>('/auth/refresh', {
            refresh_token: refreshToken,
        });
        return response.data;
    }

    // Password management - FIXED: Match Laravel API field names
    async forgotPassword(data: ForgotPasswordRequest): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/auth/forgot-password', data);
        return response.data;
    }

    async resetPassword(data: ResetPasswordRequest): Promise<PasswordResetResponse> {
        const response = await api.post<PasswordResetResponse>('/auth/reset-password', data);
        return response.data;
    }

    // FIXED: Changed to match Laravel API field names (current_password, new_password, new_password_confirmation)
    async changePassword(data: ChangePasswordRequest): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/auth/change-password', {
            current_password: data.current_password,
            new_password: data.password,
            new_password_confirmation: data.password_confirmation,
        });
        return response.data;
    }

    // Email verification
    async verifyEmail(data: VerifyEmailRequest): Promise<{ message: string }> {
        const response = await api.get<{ message: string }>(
            `/email/verify/${data.id}/${data.hash}?expires=${data.expires}&signature=${data.signature}`
        );
        return response.data;
    }

    async resendVerification(): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/email/verification-notification');
        return response.data;
    }

    // User profile
    async getProfile(): Promise<User> {
        const response = await api.get<User>('/auth/user');
        return response.data;
    }

    async updateProfile(data: Partial<User>): Promise<User> {
        const response = await api.patch<User>('/auth/user', data);
        return response.data;
    }

    async updatePreferences(preferences: Partial<UserPreferences>): Promise<UserPreferences> {
        const response = await api.patch<UserPreferences>('/auth/user/preferences', preferences);
        return response.data;
    }

    async uploadAvatar(file: File, onProgress?: (progress: number) => void): Promise<User> {
        const response = await api.upload<User>('/auth/user/avatar', file, onProgress);
        return response.data;
    }

    async deleteAvatar(): Promise<User> {
        const response = await api.delete<User>('/auth/user/avatar');
        return response.data;
    }

    // ADDED: Security info endpoint to match Laravel API
    async getSecurityInfo(): Promise<{
        requires_password_change: boolean;
        days_until_password_expiry: number;
        security_score: number;
        is_account_locked: boolean;
        last_login_at?: string;
        last_login_ip?: string;
        failed_login_attempts: number;
        active_sessions: number;
        two_factor_enabled: boolean;
        password_changed_at?: string;
        recent_activity?: SecurityEvent[];
    }> {
        const response = await api.get('/auth/security-info');
        return response.data;
    }

    async getActiveSessions(): Promise<SessionInfo[]> {
        const response = await api.get<SessionInfo[]>('/auth/sessions');
        return response.data;
    }

    async revokeSession(sessionId: string): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/auth/sessions/${sessionId}`);
        return response.data;
    }

    async revokeAllSessions(): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>('/auth/sessions');
        return response.data;
    }

    // Two-factor authentication
    async enableTwoFactor(): Promise<{
        qr_code: string;
        secret: string;
        backup_codes: string[];
    }> {
        const response = await api.post('/auth/two-factor/enable');
        return response.data;
    }

    async confirmTwoFactor(code: string): Promise<{
        backup_codes: string[];
        message: string;
    }> {
        const response = await api.post('/auth/two-factor/confirm', { code });
        return response.data;
    }

    async disableTwoFactor(password: string): Promise<{ message: string }> {
        const response = await api.delete('/auth/two-factor/disable', {
            data: { password }
        });
        return response.data;
    }

    async generateBackupCodes(): Promise<{ backup_codes: string[] }> {
        const response = await api.post('/auth/two-factor/backup-codes');
        return response.data;
    }

    // Account management
    async deleteAccount(password: string, reason?: string): Promise<{ message: string }> {
        const response = await api.delete('/auth/user', {
            data: { password, reason }
        });
        return response.data;
    }

    async exportData(): Promise<Blob> {
        const response = await api.get('/auth/user/export', {
            responseType: 'blob'
        });
        return response.data;
    }

    // Admin functions (if user has admin role)
    async getUsers(params?: {
        page?: number;
        per_page?: number;
        search?: string;
        role?: string;
        status?: string;
    }): Promise<ApiResponse<User[]>> {
        const response = await api.get('/admin/users', { params });
        return response;
    }

    async getUserById(id: number): Promise<User> {
        const response = await api.get<User>(`/admin/users/${id}`);
        return response.data;
    }

    async updateUser(id: number, data: Partial<User>): Promise<User> {
        const response = await api.patch<User>(`/admin/users/${id}`, data);
        return response.data;
    }

    async deleteUser(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/admin/users/${id}`);
        return response.data;
    }

    async impersonateUser(id: number): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>(`/admin/users/${id}/impersonate`);
        return response.data;
    }

    // Utility functions
    async checkEmailAvailability(email: string): Promise<{ available: boolean }> {
        const response = await api.post<{ available: boolean }>('/auth/check-email', { email });
        return response.data;
    }

    async validatePasswordStrength(password: string): Promise<{
        score: number;
        strength: string;
        feedback: string[];
    }> {
        const response = await api.post('/auth/validate-password', { password });
        return response.data;
    }

    // Health check
    async healthCheck(): Promise<{
        status: 'ok' | 'error';
        timestamp: string;
        version?: string;
    }> {
        const response = await api.get('/health');
        return response.data;
    }
}

// Export singleton instance
export const authApi = new AuthApi();