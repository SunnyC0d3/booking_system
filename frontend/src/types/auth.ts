// User Types
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    avatar?: string | null;
    phone?: string | null;
    date_of_birth?: string | null;
    gender?: 'male' | 'female' | 'other' | null;
    role: UserRole;
    preferences?: UserPreferences;
    security_score?: SecurityScore;
    created_at: string;
    updated_at: string;
}

export interface UserRole {
    id: number;
    name: string;
    permissions: Permission[];
}

export interface Permission {
    id: number;
    name: string;
    description?: string;
}

export interface UserPreferences {
    theme: 'light' | 'dark' | 'system';
    notifications: {
        email: boolean;
        push: boolean;
        sms: boolean;
    };
    language: string;
    timezone: string;
}

export interface SecurityScore {
    score: number;
    strength: 'weak' | 'medium' | 'strong' | 'very_strong';
    feedback: string[];
}

// Authentication Request Types
export interface LoginRequest {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterRequest {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms_accepted: boolean;
    marketing_consent?: boolean;
}

export interface ForgotPasswordRequest {
    email: string;
}

export interface ResetPasswordRequest {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface ChangePasswordRequest {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export interface VerifyEmailRequest {
    id: string;
    hash: string;
    expires: string;
    signature: string;
}

// Authentication Response Types
export interface AuthResponse {
    access_token: string;
    refresh_token?: string;
    token_type: 'Bearer';
    expires_in: number;
    user: User;
}

export interface RefreshTokenResponse {
    access_token: string;
    refresh_token?: string;
    token_type: 'Bearer';
    expires_in: number;
}

export interface PasswordResetResponse {
    message: string;
    access_token?: string;
    user?: User;
}

// Authentication State Types
export interface AuthState {
    user: User | null;
    accessToken: string | null;
    refreshToken: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    isInitialized: boolean;
    error: string | null;
    lastActivity: number | null;
}

export interface AuthActions {
    // Authentication
    login: (credentials: LoginRequest) => Promise<void>;
    register: (data: RegisterRequest) => Promise<void>;
    logout: () => Promise<void>;
    refreshToken: () => Promise<void>;

    // Password Management
    forgotPassword: (data: ForgotPasswordRequest) => Promise<void>;
    resetPassword: (data: ResetPasswordRequest) => Promise<void>;
    changePassword: (data: ChangePasswordRequest) => Promise<void>;

    // Email Verification
    verifyEmail: (data: VerifyEmailRequest) => Promise<void>;
    resendVerification: () => Promise<void>;

    // User Management
    updateUser: (user: Partial<User>) => void;
    fetchUserProfile: () => Promise<void>;
    updateUserPreferences: (preferences: Partial<UserPreferences>) => Promise<void>;

    // Session Management
    checkAuth: () => Promise<void>;
    initialize: () => Promise<void>;
    updateLastActivity: () => void;

    // Token Management
    setTokens: (accessToken: string, refreshToken?: string) => void;
    clearTokens: () => void;
    getStoredTokens: () => { accessToken: string | null; refreshToken: string | null };

    // Utility
    clearError: () => void;
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
}

// Form Validation Types
export interface LoginFormData {
    email: string;
    password: string;
    remember: boolean;
}

export interface RegisterFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms_accepted: boolean;
    marketing_consent: boolean;
}

export interface ForgotPasswordFormData {
    email: string;
}

export interface ResetPasswordFormData {
    password: string;
    password_confirmation: string;
}

export interface ChangePasswordFormData {
    current_password: string;
    password: string;
    password_confirmation: string;
}

// Authentication Error Types
export interface AuthError {
    message: string;
    code?: string;
    field?: string;
    errors?: Record<string, string[]>;
}

// Route Protection Types
export interface RouteGuardProps {
    children: React.ReactNode;
    requireAuth?: boolean;
    requireGuest?: boolean;
    requiredRoles?: string[];
    requiredPermissions?: string[];
    fallback?: React.ReactNode;
    redirectTo?: string;
}

// Session Types
export interface SessionInfo {
    ip_address: string;
    user_agent: string;
    last_activity: string;
    location?: string;
    is_current: boolean;
}

// Security Types
export interface SecurityEvent {
    type: 'login' | 'logout' | 'password_change' | 'failed_login' | 'token_refresh';
    timestamp: string;
    ip_address: string;
    user_agent: string;
    success: boolean;
    details?: Record<string, any>;
}

export interface TwoFactorAuth {
    enabled: boolean;
    backup_codes?: string[];
    recovery_codes?: string[];
}

// Authentication Hook Types
export interface UseAuthReturn extends AuthState, AuthActions {
    // Computed properties
    hasRole: (role: string) => boolean;
    hasPermission: (permission: string) => boolean;
    hasAnyRole: (roles: string[]) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
    isEmailVerified: boolean;
    needsEmailVerification: boolean;
    sessionTimeRemaining: number | null;
}

// API Response Types
export interface ApiResponse<T = any> {
    data: T;
    message?: string;
    errors?: Record<string, string[]>;
    meta?: {
        pagination?: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status?: number;
    code?: string;
}