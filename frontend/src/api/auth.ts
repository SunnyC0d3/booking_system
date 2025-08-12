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
} from '@/types/auth';

export class AuthApi {
    async login(credentials: LoginRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/login', credentials);
        return response.data;
    }

    async register(data: RegisterRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/register', data);
        return response.data;
    }

    async logout(): Promise<void> {
        await api.post('/logout');
    }

    async refreshToken(refreshToken: string): Promise<RefreshTokenResponse> {
        const response = await api.post<RefreshTokenResponse>('/refresh', {
            refresh_token: refreshToken,
        });
        return response.data;
    }

    async forgotPassword(data: ForgotPasswordRequest): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/forgot-password', data);
        return response.data;
    }

    async resetPassword(data: ResetPasswordRequest): Promise<PasswordResetResponse> {
        const response = await api.post<PasswordResetResponse>('/reset-password', data);
        return response.data;
    }

    async changePassword(data: ChangePasswordRequest): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/change-password', {
            current_password: data.current_password,
            new_password: data.password,
            new_password_confirmation: data.password_confirmation,
        });
        return response.data;
    }

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

    async getProfile(): Promise<User> {
        const response = await api.get<User>('/user');
        return response.data;
    }

    async updatePreferences(preferences: Partial<UserPreferences>): Promise<UserPreferences> {
        const response = await api.patch<UserPreferences>('/user/preferences', preferences);
        return response.data;
    }

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
        const response = await api.get('/security-info');
        return response.data;
    }
}

export const authApi = new AuthApi();