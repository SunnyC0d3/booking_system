'use client';

import { useCallback } from 'react';
import { useAuth } from '@/stores/authStore';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

export function useAuthUtils() {
    const auth = useAuth();
    const router = useRouter();

    const requireAuth = useCallback((
        redirectTo?: string,
        message?: string
    ): boolean => {
        if (!auth.isAuthenticated) {
            const redirect = redirectTo || '/login';
            const currentPath = window.location.pathname;

            if (message) {
                toast.error(message);
            }

            router.push(`${redirect}?redirect=${encodeURIComponent(currentPath)}`);
            return false;
        }
        return true;
    }, [auth.isAuthenticated, router]);

    const requireRole = useCallback((
        role: string,
        redirectTo?: string,
        message?: string
    ): boolean => {
        if (!requireAuth(redirectTo, message)) {
            return false;
        }

        if (!auth.hasRole(role)) {
            const redirect = redirectTo || '/unauthorized';

            if (message) {
                toast.error(message);
            } else {
                toast.error(`Access denied. Required role: ${role}`);
            }

            router.push(redirect);
            return false;
        }
        return true;
    }, [auth.hasRole, requireAuth, router]);

    const requirePermission = useCallback((
        permission: string,
        redirectTo?: string,
        message?: string
    ): boolean => {
        if (!requireAuth(redirectTo, message)) {
            return false;
        }

        if (!auth.hasPermission(permission)) {
            const redirect = redirectTo || '/unauthorized';

            if (message) {
                toast.error(message);
            } else {
                toast.error(`Access denied. Required permission: ${permission}`);
            }

            router.push(redirect);
            return false;
        }
        return true;
    }, [auth.hasPermission, requireAuth, router]);

    const requireEmailVerified = useCallback((
        redirectTo?: string,
        message?: string
    ): boolean => {
        if (!requireAuth(redirectTo, message)) {
            return false;
        }

        if (!auth.isEmailVerified) {
            const redirect = redirectTo || '/verify-email';

            if (message) {
                toast.error(message);
            } else {
                toast.error('Please verify your email address to continue');
            }

            router.push(redirect);
            return false;
        }
        return true;
    }, [auth.isEmailVerified, requireAuth, router]);

    const handleLogout = useCallback(async (
        redirectTo?: string,
        message?: string
    ) => {
        try {
            await auth.logout();

            if (message) {
                toast.success(message);
            }

            router.push(redirectTo || '/login');
        } catch (error) {
            console.error('Logout failed:', error);
            toast.error('Logout failed. Please try again.');
        }
    }, [auth.logout, router]);

    const redirectAfterLogin = useCallback(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const redirect = urlParams.get('redirect');

        if (redirect && redirect.startsWith('/')) {
            router.replace(redirect);
        } else {
            router.replace('/dashboard');
        }
    }, [router]);

    const getAuthHeaders = useCallback(() => {
        const token = auth.accessToken;
        return token ? { Authorization: `Bearer ${token}` } : {};
    }, [auth.accessToken]);

    return {
        ...auth,
        requireAuth,
        requireRole,
        requirePermission,
        requireEmailVerified,
        handleLogout,
        redirectAfterLogin,
        getAuthHeaders,
        canAccess: (roles?: string[], permissions?: string[]) => {
            if (!auth.isAuthenticated) return false;
            if (roles?.length && !roles.some(role => auth.hasRole(role))) return false;
            if (permissions?.length && !permissions.some(perm => auth.hasPermission(perm))) return false;
            return true;
        },

        isAdmin: auth.hasRole('admin'),
        isModerator: auth.hasRole('moderator'),

        isTokenValid: () => auth.accessToken && !auth.isLoading,
        needsRefresh: () => auth.userToken && auth.isUserTokenExpiring(),
    };
}