import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { toast } from 'sonner';
import { authApi } from '@/api/auth';
import { api } from '@/api/client';
import {
    AuthState,
    AuthActions,
    User,
    LoginRequest,
    RegisterRequest,
    ForgotPasswordRequest,
    ResetPasswordRequest,
    ChangePasswordRequest,
    VerifyEmailRequest,
    UserPreferences,
    UseAuthReturn,
} from '@/types/auth';

const SESSION_TIMEOUT = 30 * 60 * 1000;

const initialState: AuthState = {
    user: null,
    accessToken: null,
    refreshToken: null,
    isAuthenticated: false,
    isLoading: false,
    isInitialized: false,
    error: null,
    lastActivity: null,
};

export const useAuthStore = create<AuthState & AuthActions>()(
    persist(
        immer((set, get) => ({
            ...initialState,
            login: async (credentials: LoginRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const response = await authApi.login(credentials);

                    api.setTokens(response.access_token, response.refresh_token);

                    set((state) => {
                        state.user = response.user;
                        state.accessToken = response.access_token;
                        state.refreshToken = response.refresh_token || null;
                        state.isAuthenticated = true;
                        state.isLoading = false;
                        state.error = null;
                        state.lastActivity = Date.now();
                    });

                    toast.success('Welcome back!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Login failed';
                        state.isLoading = false;
                        state.isAuthenticated = false;
                    });

                    toast.error(error.message || 'Login failed');
                    throw error;
                }
            },

            register: async (data: RegisterRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const response = await authApi.register(data);

                    api.setTokens(response.access_token, response.refresh_token);

                    set((state) => {
                        state.user = response.user;
                        state.accessToken = response.access_token;
                        state.refreshToken = response.refresh_token || null;
                        state.isAuthenticated = true;
                        state.isLoading = false;
                        state.error = null;
                        state.lastActivity = Date.now();
                    });

                    toast.success('Account created successfully!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Registration failed';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Registration failed');
                    throw error;
                }
            },

            logout: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                    });

                    if (get().isAuthenticated) {
                        try {
                            await authApi.logout();
                        } catch (error) {
                            console.warn('Logout API call failed:', error);
                        }
                    }

                    api.clearTokens();

                    set((state) => {
                        Object.assign(state, initialState);
                        state.isInitialized = true;
                    });

                    toast.success('Logged out successfully');
                } catch (error: any) {
                    console.error('Logout error:', error);
                    api.clearTokens();

                    set((state) => {
                        Object.assign(state, initialState);
                        state.isInitialized = true;
                    });
                }
            },

            refreshAuthToken: async () => {
                try {
                    const { refreshToken } = get();

                    if (!refreshToken) {
                        throw new Error('No refresh token available');
                    }

                    const response = await authApi.refreshToken(refreshToken);

                    api.setTokens(response.access_token, response.refresh_token);

                    set((state) => {
                        state.accessToken = response.access_token;
                        if (response.refresh_token) {
                            state.refreshToken = response.refresh_token;
                        }
                        state.lastActivity = Date.now();
                    });

                } catch (error: any) {
                    console.warn('Token refresh failed:', error);

                    get().logout();
                    throw error;
                }
            },

            forgotPassword: async (data: ForgotPasswordRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await authApi.forgotPassword(data);

                    set((state) => {
                        state.isLoading = false;
                    });

                    toast.success('Password reset email sent!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to send reset email';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to send reset email');
                    throw error;
                }
            },

            resetPassword: async (data: ResetPasswordRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const response = await authApi.resetPassword(data);

                    if (response.access_token && response.user) {
                        api.setTokens(response.access_token);

                        set((state) => {
                            state.user = response.user!;
                            state.accessToken = response.access_token!;
                            state.isAuthenticated = true;
                            state.lastActivity = Date.now();
                        });
                    }

                    set((state) => {
                        state.isLoading = false;
                    });

                    toast.success('Password reset successfully!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to reset password';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to reset password');
                    throw error;
                }
            },

            changePassword: async (data: ChangePasswordRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await authApi.changePassword(data);

                    set((state) => {
                        state.isLoading = false;
                    });

                    toast.success('Password changed successfully!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to change password';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to change password');
                    throw error;
                }
            },

            verifyEmail: async (data: VerifyEmailRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await authApi.verifyEmail(data);

                    await get().fetchUserProfile();

                    set((state) => {
                        state.isLoading = false;
                    });

                    toast.success('Email verified successfully!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to verify email';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to verify email');
                    throw error;
                }
            },

            resendVerification: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await authApi.resendVerification();

                    set((state) => {
                        state.isLoading = false;
                    });

                    toast.success('Verification email sent!');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to send verification email';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to send verification email');
                    throw error;
                }
            },

            updateUser: (userData: Partial<User>) => {
                set((state) => {
                    if (state.user) {
                        state.user = { ...state.user, ...userData };
                    }
                });
            },

            fetchUserProfile: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const user = await authApi.getProfile();

                    set((state) => {
                        state.user = user;
                        state.isLoading = false;
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to fetch user profile';
                        state.isLoading = false;
                    });

                    console.warn('Failed to fetch user profile:', error);
                    throw error;
                }
            },

            updateUserPreferences: async (preferences: Partial<UserPreferences>) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const updatedPreferences = await authApi.updatePreferences(preferences);

                    set((state) => {
                        if (state.user) {
                            state.user.preferences = updatedPreferences;
                        }
                        state.isLoading = false;
                    });

                    toast.success('Preferences updated successfully');
                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to update preferences';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to update preferences');
                    throw error;
                }
            },

            checkAuth: async () => {
                try {
                    if (!api.hasValidToken()) {
                        set((state) => {
                            state.isAuthenticated = false;
                            state.user = null;
                            state.accessToken = null;
                            state.refreshToken = null;
                        });
                        return;
                    }

                    const user = await authApi.getProfile();

                    set((state) => {
                        state.user = user;
                        state.isAuthenticated = true;
                        state.lastActivity = Date.now();
                    });

                } catch (error) {
                    console.warn('Auth check failed:', error);
                    get().logout();
                }
            },

            initialize: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                    });

                    if (api.hasValidToken()) {
                        await get().checkAuth();
                    }

                } catch (error) {
                    console.warn('Auth initialization failed:', error);
                } finally {
                    set((state) => {
                        state.isInitialized = true;
                        state.isLoading = false;
                    });
                }
            },

            updateLastActivity: () => {
                set((state) => {
                    state.lastActivity = Date.now();
                });
            },

            setTokens: (accessToken: string, refreshToken?: string) => {
                api.setTokens(accessToken, refreshToken);

                set((state) => {
                    state.accessToken = accessToken;
                    if (refreshToken) {
                        state.refreshToken = refreshToken;
                    }
                    state.lastActivity = Date.now();
                });
            },

            clearTokens: () => {
                api.clearTokens();

                set((state) => {
                    state.accessToken = null;
                    state.refreshToken = null;
                    state.isAuthenticated = false;
                    state.user = null;
                    state.lastActivity = null;
                });
            },

            getStoredTokens: () => {
                return {
                    accessToken: api.getAccessToken(),
                    refreshToken: get().refreshToken,
                };
            },

            clearError: () => {
                set((state) => {
                    state.error = null;
                });
            },

            setLoading: (loading: boolean) => {
                set((state) => {
                    state.isLoading = loading;
                });
            },

            setError: (error: string | null) => {
                set((state) => {
                    state.error = error;
                });
            },
        })),
        {
            name: 'auth-storage',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                user: state.user,
                accessToken: state.accessToken,
                refreshToken: state.refreshToken,
                isAuthenticated: state.isAuthenticated,
                lastActivity: state.lastActivity,
            }),
        }
    )
);

export const useAuth = (): UseAuthReturn => {
    const store = useAuthStore();

    const hasRole = (role: string): boolean => {
        return store.user?.role?.name === role;
    };

    const hasPermission = (permission: string): boolean => {
        return store.user?.role?.permissions?.some(p => p.name === permission) ?? false;
    };

    const hasAnyRole = (roles: string[]): boolean => {
        return roles.some(role => hasRole(role));
    };

    const hasAnyPermission = (permissions: string[]): boolean => {
        return permissions.some(permission => hasPermission(permission));
    };

    const isEmailVerified = Boolean(store.user?.email_verified_at);

    const needsEmailVerification = Boolean(store.user && !store.user.email_verified_at);

    const sessionTimeRemaining = store.lastActivity
        ? Math.max(0, SESSION_TIMEOUT - (Date.now() - store.lastActivity))
        : null;

    return {
        ...store,
        hasRole,
        hasPermission,
        hasAnyRole,
        hasAnyPermission,
        isEmailVerified,
        needsEmailVerification,
        sessionTimeRemaining,
    };
};