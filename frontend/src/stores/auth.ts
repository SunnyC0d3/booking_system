import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import Cookies from 'js-cookie';
import {
    User,
    LoginRequest,
    RegisterRequest,
    ChangePasswordRequest,
    ForgotPasswordRequest,
    ResetPasswordRequest,
} from '@/types/api';
import { authApi } from '@/api/auth';
import { toast } from 'sonner';

interface AuthState {
    user: User | null;
    accessToken: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
}

interface AuthActions {
    // Authentication actions
    login: (credentials: LoginRequest) => Promise<void>;
    register: (data: RegisterRequest) => Promise<void>;
    logout: () => Promise<void>;
    refreshToken: () => Promise<void>;

    // Password actions
    changePassword: (data: ChangePasswordRequest) => Promise<void>;
    forgotPassword: (data: ForgotPasswordRequest) => Promise<void>;
    resetPassword: (data: ResetPasswordRequest) => Promise<void>;

    // User actions
    updateUser: (user: Partial<User>) => void;
    fetchUserProfile: () => Promise<void>;

    // Utility actions
    clearError: () => void;
    setLoading: (loading: boolean) => void;
    checkAuth: () => Promise<void>;

    // Token management
    setTokens: (accessToken: string, refreshToken?: string) => void;
    clearTokens: () => void;
    getStoredTokens: () => { accessToken: string | null; refreshToken: string | null };
}

type AuthStore = AuthState & AuthActions;

const COOKIE_OPTIONS = {
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'strict' as const,
    httpOnly: false,
    expires: 7,
};

export const useAuthStore = create<AuthStore>()(
    persist(
        (set, get) => ({
            user: null,
            accessToken: null,
            isAuthenticated: false,
            isLoading: false,
            error: null,

            // Authentication actions
            login: async (credentials: LoginRequest) => {
                try {
                    set({ isLoading: true, error: null });

                    const response = await authApi.login(credentials);

                    get().setTokens(response.access_token);

                    set({
                        user: response.user,
                        accessToken: response.access_token,
                        isAuthenticated: true,
                        isLoading: false,
                        error: null,
                    });

                    toast.success('Login successful!');
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Login failed';
                    set({
                        error: errorMessage,
                        isLoading: false,
                        isAuthenticated: false,
                        user: null,
                        accessToken: null,
                    });
                    toast.error(errorMessage);
                    throw error;
                }
            },

            register: async (data: RegisterRequest) => {
                try {
                    set({ isLoading: true, error: null });

                    const response = await authApi.register(data);

                    get().setTokens(response.access_token);

                    set({
                        user: response.user,
                        accessToken: response.access_token,
                        isAuthenticated: true,
                        isLoading: false,
                        error: null,
                    });

                    toast.success('Registration successful!');
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Registration failed';
                    set({
                        error: errorMessage,
                        isLoading: false,
                        isAuthenticated: false,
                        user: null,
                        accessToken: null,
                    });
                    toast.error(errorMessage);
                    throw error;
                }
            },

            logout: async () => {
                try {
                    set({ isLoading: true });

                    if (get().accessToken) {
                        await authApi.logout();
                    }
                } catch (error) {
                    console.warn('Logout API call failed:', error);
                } finally {
                    get().clearTokens();

                    set({
                        user: null,
                        accessToken: null,
                        isAuthenticated: false,
                        isLoading: false,
                        error: null,
                    });

                    toast.success('Logged out successfully');
                }
            },

            refreshToken: async () => {
                try {
                    const { refreshToken } = get().getStoredTokens();

                    if (!refreshToken) {
                        throw new Error('No refresh token available');
                    }

                    const response = await authApi.refreshToken(refreshToken);

                    get().setTokens(response.access_token, response.refresh_token);

                    set({
                        accessToken: response.access_token,
                        user: response.user,
                        isAuthenticated: true,
                        error: null,
                    });
                } catch (error) {
                    get().logout();
                    throw error;
                }
            },

            // Password actions
            changePassword: async (data: ChangePasswordRequest) => {
                try {
                    set({ isLoading: true, error: null });

                    await authApi.changePassword(data);

                    set({ isLoading: false, error: null });
                    toast.success('Password changed successfully');
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Failed to change password';
                    set({ error: errorMessage, isLoading: false });
                    toast.error(errorMessage);
                    throw error;
                }
            },

            forgotPassword: async (data: ForgotPasswordRequest) => {
                try {
                    set({ isLoading: true, error: null });

                    await authApi.forgotPassword(data);

                    set({ isLoading: false, error: null });
                    toast.success('Password reset email sent');
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Failed to send reset email';
                    set({ error: errorMessage, isLoading: false });
                    toast.error(errorMessage);
                    throw error;
                }
            },

            resetPassword: async (data: ResetPasswordRequest) => {
                try {
                    set({ isLoading: true, error: null });

                    const response = await authApi.resetPassword(data);

                    // Auto-login after successful password reset
                    get().setTokens(response.access_token);

                    set({
                        user: response.user,
                        accessToken: response.access_token,
                        isAuthenticated: true,
                        isLoading: false,
                        error: null,
                    });

                    toast.success('Password reset successful');
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Failed to reset password';
                    set({ error: errorMessage, isLoading: false });
                    toast.error(errorMessage);
                    throw error;
                }
            },

            // User actions
            updateUser: (userData: Partial<User>) => {
                const currentUser = get().user;
                if (currentUser) {
                    set({ user: { ...currentUser, ...userData } });
                }
            },

            fetchUserProfile: async () => {
                try {
                    set({ isLoading: true, error: null });

                    const user = await authApi.getProfile();

                    set({ user, isLoading: false, error: null });
                } catch (error: any) {
                    const errorMessage = error.response?.data?.message || 'Failed to fetch profile';
                    set({ error: errorMessage, isLoading: false });

                    // If unauthorized, force logout
                    if (error.response?.status === 401) {
                        get().logout();
                    }

                    throw error;
                }
            },

            // Utility actions
            clearError: () => set({ error: null }),

            setLoading: (loading: boolean) => set({ isLoading: loading }),

            checkAuth: async () => {
                const { accessToken, refreshToken } = get().getStoredTokens();

                if (!accessToken) {
                    set({ isAuthenticated: false, user: null });
                    return;
                }

                try {
                    await get().fetchUserProfile();
                    set({ accessToken, isAuthenticated: true });
                } catch (error: any) {
                    if (error.response?.status === 401 && refreshToken) {
                        try {
                            await get().refreshToken();
                        } catch (refreshError) {
                            get().clearTokens();
                            set({ isAuthenticated: false, user: null, accessToken: null });
                        }
                    } else {
                        get().clearTokens();
                        set({ isAuthenticated: false, user: null, accessToken: null });
                    }
                }
            },

            // Token management
            setTokens: (accessToken: string, refreshToken?: string) => {
                Cookies.set('access_token', accessToken, COOKIE_OPTIONS);

                if (refreshToken) {
                    Cookies.set('refresh_token', refreshToken, {
                        ...COOKIE_OPTIONS,
                        expires: 30,
                    });
                }
            },

            clearTokens: () => {
                Cookies.remove('access_token');
                Cookies.remove('refresh_token');
            },

            getStoredTokens: () => ({
                accessToken: Cookies.get('access_token') || null,
                refreshToken: Cookies.get('refresh_token') || null,
            }),
        }),
        {
            name: 'auth-store',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                user: state.user,
                isAuthenticated: state.isAuthenticated,
            }),
            onRehydrateStorage: () => (state) => {
                if (state) {
                    state.checkAuth();
                }
            },
        }
    )
);

export const useAuth = () => {
    const store = useAuthStore();
    return {
        user: store.user,
        isAuthenticated: store.isAuthenticated,
        isLoading: store.isLoading,
        error: store.error,
    };
};

export const useAuthActions = () => {
    const store = useAuthStore();
    return {
        login: store.login,
        register: store.register,
        logout: store.logout,
        changePassword: store.changePassword,
        forgotPassword: store.forgotPassword,
        resetPassword: store.resetPassword,
        clearError: store.clearError,
    };
};

export const isUserRole = (user: User | null, role: string): boolean => {
    return user?.role?.name === role;
};

export const hasAnyRole = (user: User | null, roles: string[]): boolean => {
    return user?.role?.name ? roles.includes(user.role.name) : false;
};

export const isAdmin = (user: User | null): boolean => {
    return hasAnyRole(user, ['admin', 'super admin']);
};

export const isVendor = (user: User | null): boolean => {
    return isUserRole(user, 'vendor');
};

export const canAccessRoute = (user: User | null, requiredRoles?: string[]): boolean => {
    if (!requiredRoles || requiredRoles.length === 0) {
        return true;
    }

    return hasAnyRole(user, requiredRoles);
};