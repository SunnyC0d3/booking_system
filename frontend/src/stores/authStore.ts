import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { subscribeWithSelector } from 'zustand/middleware';
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
const TOKEN_REFRESH_THRESHOLD = 5 * 60 * 1000;
const MAX_RETRY_ATTEMPTS = 3;

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

const createAuthError = (error: any, fallbackMessage: string) => {
    return {
        message: error?.message || error?.error?.message || fallbackMessage,
        code: error?.code || error?.error?.code,
        details: error?.errors || error?.error?.errors,
    };
};

export const useAuthStore = create<AuthState & AuthActions>()(
    subscribeWithSelector(
        persist(
            immer((set, get) => ({
                ...initialState,
                login: async (credentials: LoginRequest, retryCount = 0) => {
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

                        get().setupSessionMonitoring?.();

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Login failed');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                            state.isAuthenticated = false;
                        });

                        if (retryCount < MAX_RETRY_ATTEMPTS && error.code === 'NETWORK_ERROR') {
                            await new Promise(resolve => setTimeout(resolve, 1000 * (retryCount + 1)));
                            return get().login(credentials, retryCount + 1);
                        }

                        throw authError;
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

                        get().setupSessionMonitoring?.();

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Registration failed');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
                    }
                },

                logout: async (silent = false) => {
                    const wasAuthenticated = get().isAuthenticated;

                    try {
                        set((state) => {
                            state.isLoading = true;
                        });

                        get().cleanupSessionMonitoring?.();

                        if (wasAuthenticated) {
                            try {
                                await authApi.logout();
                            } catch (error) {
                                console.warn('Logout API call failed:', error);
                            }
                        }

                        api.clearTokens();

                        set((state) => {
                            Object.assign(state, {
                                ...initialState,
                                isInitialized: true,
                            });
                        });

                        if (!silent) {
                            toast.success('Logged out successfully');
                        }

                    } catch (error: any) {
                        console.error('Logout error:', error);

                        api.clearTokens();
                        get().cleanupSessionMonitoring?.();

                        set((state) => {
                            Object.assign(state, {
                                ...initialState,
                                isInitialized: true,
                            });
                        });
                    }
                },

                refreshAuthToken: async (showErrorToast = false) => {
                    const state = get();

                    try {
                        if (!state.refreshToken) {
                            throw new Error('No refresh token available');
                        }

                        const response = await authApi.refreshToken(state.refreshToken);

                        api.setTokens(response.access_token, response.refresh_token);

                        set((state) => {
                            state.accessToken = response.access_token;
                            if (response.refresh_token) {
                                state.refreshToken = response.refresh_token;
                            }
                            state.lastActivity = Date.now();
                            state.error = null;
                        });

                        return response.access_token;

                    } catch (error: any) {
                        console.warn('Token refresh failed:', error);

                        if (showErrorToast) {
                            toast.error('Session expired. Please sign in again.');
                        }

                        await get().logout(true);
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

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to send reset email');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
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
                            api.setTokens(response.access_token, response.refresh_token);

                            set((state) => {
                                state.user = response.user!;
                                state.accessToken = response.access_token!;
                                state.refreshToken = response.refresh_token || null;
                                state.isAuthenticated = true;
                                state.lastActivity = Date.now();
                            });

                            get().setupSessionMonitoring?.();
                        }

                        set((state) => {
                            state.isLoading = false;
                        });

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to reset password');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
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

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to change password');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
                    }
                },

                verifyEmail: async (data: VerifyEmailRequest) => {
                    try {
                        set((state) => {
                            state.isLoading = true;
                            state.error = null;
                        });

                        await authApi.verifyEmail(data);

                        // Refresh user profile to get updated verification status
                        await get().fetchUserProfile();

                        set((state) => {
                            state.isLoading = false;
                        });

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to verify email');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
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

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to send verification email');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        throw authError;
                    }
                },

                // Optimistic user updates
                updateUser: (userData: Partial<User>) => {
                    set((state) => {
                        if (state.user) {
                            state.user = { ...state.user, ...userData };
                            state.lastActivity = Date.now();
                        }
                    });
                },

                // Enhanced profile fetching with caching
                fetchUserProfile: async (force = false) => {
                    const state = get();

                    // Skip if recently fetched (unless forced)
                    if (!force && state.lastActivity && (Date.now() - state.lastActivity) < 60000) {
                        return state.user;
                    }

                    try {
                        set((state) => {
                            state.isLoading = true;
                            state.error = null;
                        });

                        const user = await authApi.getProfile();

                        set((state) => {
                            state.user = user;
                            state.isLoading = false;
                            state.lastActivity = Date.now();
                        });

                        return user;

                    } catch (error: any) {
                        const authError = createAuthError(error, 'Failed to fetch user profile');

                        set((state) => {
                            state.error = authError.message;
                            state.isLoading = false;
                        });

                        // Don't auto-logout on profile fetch failure
                        console.warn('Failed to fetch user profile:', authError);
                        throw authError;
                    }
                },

                // Optimistic preferences update
                updateUserPreferences: async (preferences: Partial<UserPreferences>) => {
                    const currentPreferences = get().user?.preferences;

                    try {
                        // Optimistic update
                        set((state) => {
                            if (state.user) {
                                state.user.preferences = { ...state.user.preferences, ...preferences };
                            }
                        });

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

                        return updatedPreferences;

                    } catch (error: any) {
                        // Revert optimistic update on failure
                        set((state) => {
                            if (state.user && currentPreferences) {
                                state.user.preferences = currentPreferences;
                            }
                            state.error = error.message || 'Failed to update preferences';
                            state.isLoading = false;
                        });

                        throw error;
                    }
                },

                // Enhanced auth check with token validation
                checkAuth: async () => {
                    try {
                        if (!api.hasValidToken()) {
                            set((state) => {
                                state.isAuthenticated = false;
                                state.user = null;
                                state.accessToken = null;
                                state.refreshToken = null;
                            });
                            return false;
                        }

                        const user = await authApi.getProfile();

                        set((state) => {
                            state.user = user;
                            state.isAuthenticated = true;
                            state.lastActivity = Date.now();
                            state.error = null;
                        });

                        return true;

                    } catch (error) {
                        console.warn('Auth check failed:', error);
                        await get().logout(true); // Silent logout
                        return false;
                    }
                },

                // Enhanced initialization with better error handling
                initialize: async () => {
                    try {
                        set((state) => {
                            state.isLoading = true;
                        });

                        // Check if we have valid tokens
                        if (api.hasValidToken()) {
                            const isValid = await get().checkAuth();

                            if (isValid) {
                                get().setupSessionMonitoring?.();
                            }
                        }

                    } catch (error) {
                        console.warn('Auth initialization failed:', error);
                        // Clear potentially corrupted state
                        await get().logout(true);
                    } finally {
                        set((state) => {
                            state.isInitialized = true;
                            state.isLoading = false;
                        });
                    }
                },

                // Session monitoring
                updateLastActivity: () => {
                    set((state) => {
                        state.lastActivity = Date.now();
                    });
                },

                // Enhanced token management
                setTokens: (accessToken: string, refreshToken?: string) => {
                    api.setTokens(accessToken, refreshToken);

                    set((state) => {
                        state.accessToken = accessToken;
                        if (refreshToken) {
                            state.refreshToken = refreshToken;
                        }
                        state.lastActivity = Date.now();
                        state.error = null;
                    });
                },

                clearTokens: () => {
                    api.clearTokens();
                    get().cleanupSessionMonitoring?.();

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

                // Utility methods
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

                // Session monitoring setup/cleanup (will be set by session monitoring hook)
                setupSessionMonitoring: undefined,
                cleanupSessionMonitoring: undefined,

                // Token validation and auto-refresh
                ensureValidToken: async () => {
                    const state = get();

                    if (!state.accessToken || !api.hasValidToken()) {
                        if (state.refreshToken) {
                            try {
                                await get().refreshAuthToken();
                                return true;
                            } catch {
                                await get().logout(true);
                                return false;
                            }
                        }
                        return false;
                    }

                    return true;
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
                // Enhanced storage options
                onRehydrateStorage: () => (state) => {
                    // Validate tokens on rehydration
                    if (state && !api.hasValidToken()) {
                        state.isAuthenticated = false;
                        state.accessToken = null;
                        state.user = null;
                    }
                },
            }
        )
    )
);

// Enhanced useAuth hook with memoized selectors
export const useAuth = (): UseAuthReturn => {
    const store = useAuthStore();

    // Memoized permission checkers
    const hasRole = React.useCallback((role: string): boolean => {
        return store.user?.role?.name === role;
    }, [store.user?.role?.name]);

    const hasPermission = React.useCallback((permission: string): boolean => {
        return store.user?.role?.permissions?.some(p => p.name === permission) ?? false;
    }, [store.user?.role?.permissions]);

    const hasAnyRole = React.useCallback((roles: string[]): boolean => {
        return roles.some(role => hasRole(role));
    }, [hasRole]);

    const hasAnyPermission = React.useCallback((permissions: string[]): boolean => {
        return permissions.some(permission => hasPermission(permission));
    }, [hasPermission]);

    // Computed values
    const isEmailVerified = React.useMemo(
        () => Boolean(store.user?.email_verified_at),
        [store.user?.email_verified_at]
    );

    const needsEmailVerification = React.useMemo(
        () => Boolean(store.user && !store.user.email_verified_at),
        [store.user, store.user?.email_verified_at]
    );

    const sessionTimeRemaining = React.useMemo(
        () => store.lastActivity
            ? Math.max(0, SESSION_TIMEOUT - (Date.now() - store.lastActivity))
            : null,
        [store.lastActivity]
    );

    const isSessionExpired = React.useMemo(
        () => sessionTimeRemaining !== null && sessionTimeRemaining <= 0,
        [sessionTimeRemaining]
    );

    return {
        ...store,
        hasRole,
        hasPermission,
        hasAnyRole,
        hasAnyPermission,
        isEmailVerified,
        needsEmailVerification,
        sessionTimeRemaining,
        isSessionExpired,
    };
};

// Selective store hooks for better performance
export const useAuthUser = () => useAuthStore((state) => state.user);
export const useAuthLoading = () => useAuthStore((state) => state.isLoading);
export const useAuthError = () => useAuthStore((state) => state.error);
export const useIsAuthenticated = () => useAuthStore((state) => state.isAuthenticated);
export const useIsInitialized = () => useAuthStore((state) => state.isInitialized);

// Session monitoring hook (to be used in app component)
export const useSessionMonitoring = () => {
    const { updateLastActivity, logout, sessionTimeRemaining, isAuthenticated, setupSessionMonitoring, cleanupSessionMonitoring } = useAuthStore();

    React.useEffect(() => {
        if (!isAuthenticated) return;

        let sessionTimeout: NodeJS.Timeout;
        let activityListener: (() => void) | undefined;

        const setupMonitoring = () => {
            // Update activity on user interactions
            activityListener = () => updateLastActivity();

            const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
            events.forEach(event => {
                document.addEventListener(event, activityListener!, { passive: true });
            });

            // Check session expiry
            const checkSession = () => {
                if (sessionTimeRemaining !== null && sessionTimeRemaining <= 0) {
                    logout(false);
                    toast.error('Your session has expired. Please sign in again.');
                }
            };

            sessionTimeout = setInterval(checkSession, 60000); // Check every minute
        };

        const cleanup = () => {
            if (activityListener) {
                const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
                events.forEach(event => {
                    document.removeEventListener(event, activityListener!);
                });
            }

            if (sessionTimeout) {
                clearInterval(sessionTimeout);
            }
        };

        // Set up monitoring functions in store
        useAuthStore.setState({
            setupSessionMonitoring: setupMonitoring,
            cleanupSessionMonitoring: cleanup,
        });

        setupMonitoring();

        return cleanup;
    }, [isAuthenticated, updateLastActivity, logout, sessionTimeRemaining]);
};