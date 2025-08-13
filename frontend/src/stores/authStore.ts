import * as React from 'react';
import {create} from 'zustand';
import {persist, createJSONStorage, subscribeWithSelector} from 'zustand/middleware';
import {immer} from 'zustand/middleware/immer';
import {toast} from 'sonner';
import {authApi} from '@/api/auth';
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

interface TokenInfo {
    accessToken: string;
    refreshToken?: string;
    expiresAt?: number;
    issuedAt: number;
    type: 'user';
}

const initialState: AuthState & {
    userToken: TokenInfo | null;
    tokenExpiryTimer: NodeJS.Timeout | null;
} = {
    user: null,
    accessToken: null,
    refreshToken: null,
    isAuthenticated: false,
    isLoading: false,
    isInitialized: false,
    error: null,
    lastActivity: null,
    userToken: null,
    tokenExpiryTimer: null,
};

const createAuthError = (error: any, fallbackMessage: string) => {
    return {
        message: error?.message || error?.error?.message || fallbackMessage,
        code: error?.code || error?.error?.code,
        details: error?.errors || error?.error?.errors,
    };
};

const parseJwtExpiry = (token: string): number | null => {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) return null;

        const payload = JSON.parse(atob(parts[1]));
        return payload.exp ? payload.exp * 1000 : null;
    } catch {
        return null;
    }
};

const isTokenExpiringSoon = (expiresAt: number, threshold = TOKEN_REFRESH_THRESHOLD): boolean => {
    return (expiresAt - Date.now()) < threshold;
};

const isTokenExpired = (expiresAt: number): boolean => {
    return Date.now() >= expiresAt;
};

export const useAuthStore = create<AuthState & AuthActions & {
    userToken: TokenInfo | null;
    tokenExpiryTimer: NodeJS.Timeout | null;
    setUserTokens: (accessToken: string, refreshToken?: string) => void;
    clearAllTokens: () => void;
    getUserToken: () => string | null;
    isUserTokenValid: () => boolean;
    isUserTokenExpiring: () => boolean;
    scheduleTokenRefresh: () => void;
    clearTokenRefreshTimer: () => void;
}>()(
    subscribeWithSelector(
        persist(
            immer((set, get) => ({
                ...initialState,
                setUserTokens: (accessToken: string, refreshToken?: string) => {
                    const expiresAt = parseJwtExpiry(accessToken);
                    const now = Date.now();

                    set((state) => {
                        state.userToken = {
                            accessToken,
                            refreshToken,
                            expiresAt: expiresAt || (now + (60 * 60 * 1000)),
                            issuedAt: now,
                            type: 'user',
                        };
                        state.accessToken = accessToken;
                        state.refreshToken = refreshToken || null;
                        state.lastActivity = now;
                        state.error = null;
                    });

                    get().scheduleTokenRefresh();
                },

                clearAllTokens: () => {
                    get().clearTokenRefreshTimer();

                    set((state) => {
                        state.userToken = null;
                        state.accessToken = null;
                        state.refreshToken = null;
                        state.isAuthenticated = false;
                        state.user = null;
                        state.lastActivity = null;
                    });
                },

                getUserToken: () => {
                    const {userToken} = get();
                    return userToken?.accessToken || null;
                },

                isUserTokenValid: () => {
                    const {userToken} = get();
                    return userToken ? !isTokenExpired(userToken.expiresAt || 0) : false;
                },

                isUserTokenExpiring: () => {
                    const {userToken} = get();
                    return userToken ? isTokenExpiringSoon(userToken.expiresAt || 0) : false;
                },

                scheduleTokenRefresh: () => {
                    const {userToken, tokenExpiryTimer} = get();

                    if (tokenExpiryTimer) {
                        clearTimeout(tokenExpiryTimer);
                    }

                    if (!userToken?.expiresAt || !userToken.refreshToken) return;

                    const timeUntilRefresh = Math.max(
                        0,
                        userToken.expiresAt - Date.now() - TOKEN_REFRESH_THRESHOLD
                    );

                    const newTimer = setTimeout(async () => {
                        try {
                            await get().refreshAuthToken(false);
                        } catch (error) {
                            console.warn('Scheduled token refresh failed:', error);
                        }
                    }, timeUntilRefresh);

                    set((state) => {
                        state.tokenExpiryTimer = newTimer;
                    });
                },

                clearTokenRefreshTimer: () => {
                    const {tokenExpiryTimer} = get();
                    if (tokenExpiryTimer) {
                        clearTimeout(tokenExpiryTimer);
                        set((state) => {
                            state.tokenExpiryTimer = null;
                        });
                    }
                },

                login: async (credentials: LoginRequest, retryCount = 0) => {
                    try {
                        set((state) => {
                            state.isLoading = true;
                            state.error = null;
                        });

                        const response = await authApi.login(credentials);

                        if (!response.access_token || !response.user) {
                            throw new Error('Invalid login response format');
                        }

                        get().setUserTokens(response.access_token, response.refresh_token);

                        set((state) => {
                            state.user = response.user;
                            state.isAuthenticated = true;
                            state.isLoading = false;
                            state.error = null;
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

                        if (!response.access_token || !response.user) {
                            throw new Error('Invalid registration response format');
                        }

                        get().setUserTokens(response.access_token, response.refresh_token);

                        set((state) => {
                            state.user = response.user;
                            state.isAuthenticated = true;
                            state.isLoading = false;
                            state.error = null;
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

                        get().clearAllTokens();

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
                        get().clearAllTokens();
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
                    const {userToken} = get();

                    try {
                        if (!userToken?.refreshToken) {
                            throw new Error('No refresh token available');
                        }

                        const response = await authApi.refreshToken(userToken.refreshToken);

                        get().setUserTokens(response.access_token, response.refresh_token);

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
                ensureValidToken: async (): Promise<boolean> => {
                    const state = get();

                    if (!state.isUserTokenValid()) {
                        if (state.userToken?.refreshToken) {
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

                    if (state.isUserTokenExpiring() && state.userToken?.refreshToken) {
                        try {
                            await get().refreshAuthToken();
                        } catch (error) {
                            console.warn('Proactive token refresh failed:', error);
                        }
                    }

                    return true;
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
                            get().setUserTokens(response.access_token, response.refresh_token);

                            set((state) => {
                                state.user = response.user!;
                                state.isAuthenticated = true;
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

                updateUser: (userData: Partial<User>) => {
                    set((state) => {
                        if (state.user) {
                            state.user = {...state.user, ...userData};
                            state.lastActivity = Date.now();
                        }
                    });
                },

                fetchUserProfile: async (force = false) => {
                    const state = get();

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

                        console.warn('Failed to fetch user profile:', authError);
                        throw authError;
                    }
                },

                updateUserPreferences: async (preferences: Partial<UserPreferences>) => {
                    const currentPreferences = get().user?.preferences;

                    try {
                        set((state) => {
                            if (state.user) {
                                state.user.preferences = {...state.user.preferences, ...preferences};
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

                checkAuth: async () => {
                    try {
                        if (!get().isUserTokenValid()) {
                            set((state) => {
                                state.isAuthenticated = false;
                                state.user = null;
                            });
                            get().clearAllTokens();
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
                        await get().logout(true);
                        return false;
                    }
                },

                initialize: async () => {
                    try {
                        set((state) => {
                            state.isLoading = true;
                        });

                        if (get().isUserTokenValid()) {
                            const isValid = await get().checkAuth();

                            if (isValid) {
                                get().setupSessionMonitoring?.();
                                get().scheduleTokenRefresh();
                            }
                        }

                    } catch (error) {
                        console.warn('Auth initialization failed:', error);
                        await get().logout(true);
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

                setupSessionMonitoring: undefined,
                cleanupSessionMonitoring: undefined,
            })),
            {
                name: 'auth-storage',
                storage: createJSONStorage(() => localStorage),
                partialize: (state) => ({
                    user: state.user,
                    userToken: state.userToken,
                    isAuthenticated: state.isAuthenticated,
                    lastActivity: state.lastActivity,
                }),
                onRehydrateStorage: () => (state) => {
                    if (state?.userToken && state.userToken.expiresAt) {
                        // Check if stored token is expired
                        if (isTokenExpired(state.userToken.expiresAt)) {
                            state.isAuthenticated = false;
                            state.userToken = null;
                            state.user = null;
                        }
                    }
                },
            }
        )
    )
);

export const useAuth = (): UseAuthReturn & {
    userToken: TokenInfo | null;
    isUserTokenExpiring: boolean;
    timeUntilTokenExpiry: number | null;
} => {
    const store = useAuthStore();

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

    const timeUntilTokenExpiry = React.useMemo(() => {
        if (!store.userToken?.expiresAt) return null;
        return Math.max(0, store.userToken.expiresAt - Date.now());
    }, [store.userToken?.expiresAt]);

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
        isUserTokenExpiring: store.isUserTokenExpiring(),
        timeUntilTokenExpiry,
    };
};
