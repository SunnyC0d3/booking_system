'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import type { RouteGuardProps } from '@/types/auth';
import {Spinner} from "@/components/ui";

export function RouteGuard({
                               children,
                               requireAuth = false,
                               requireGuest = false,
                               requiredRoles = [],
                               requiredPermissions = [],
                               fallback,
                               redirectTo
                           }: RouteGuardProps) {
    const router = useRouter();
    const {
        isAuthenticated,
        isLoading,
        isInitialized,
        hasRole,
        hasPermission,
        initialize
    } = useAuthUtils();

    useEffect(() => {
        if (!isInitialized) {
            initialize();
        }
    }, [isInitialized, initialize]);

    if (!isInitialized || isLoading) {
        return fallback || <Spinner />;
    }

    if (requireGuest && isAuthenticated) {
        const redirectPath = redirectTo || '/dashboard';
        router.replace(redirectPath);
        return fallback || <Spinner />;
    }

    if (requireAuth && !isAuthenticated) {
        const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
        const loginPath = redirectTo || `/login${currentPath ? `?redirect=${encodeURIComponent(currentPath)}` : ''}`;
        router.replace(loginPath);
        return fallback || <Spinner />;
    }

    if (requireAuth && isAuthenticated && requiredRoles.length > 0) {
        const hasRequiredRole = requiredRoles.some(role => hasRole(role));
        if (!hasRequiredRole) {
            router.replace('/unauthorized');
            return fallback || <Spinner />;
        }
    }

    if (requireAuth && isAuthenticated && requiredPermissions.length > 0) {
        const hasRequiredPermission = requiredPermissions.some(permission => hasPermission(permission));
        if (!hasRequiredPermission) {
            router.replace('/unauthorized');
            return fallback || <Spinner />;
        }
    }

    return <>{children}</>;
}