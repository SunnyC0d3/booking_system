'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import type { RouteGuardProps } from '@/types/auth';
import { Spinner } from "@/components/ui";

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
    const [shouldRender, setShouldRender] = useState(false);
    const [isRedirecting, setIsRedirecting] = useState(false);

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

    useEffect(() => {
        if (!isInitialized || isLoading || isRedirecting) {
            return;
        }

        if (requireGuest && isAuthenticated) {
            setIsRedirecting(true);
            const redirectPath = redirectTo || '/dashboard';
            router.replace(redirectPath);
            return;
        }

        if (requireAuth && !isAuthenticated) {
            setIsRedirecting(true);
            const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
            const loginPath = redirectTo || `/login${currentPath ? `?redirect=${encodeURIComponent(currentPath)}` : ''}`;
            router.replace(loginPath);
            return;
        }

        if (requireAuth && isAuthenticated && requiredRoles.length > 0) {
            const hasRequiredRole = requiredRoles.some(role => hasRole(role));
            if (!hasRequiredRole) {
                setIsRedirecting(true);
                router.replace('/unauthorized');
                return;
            }
        }

        if (requireAuth && isAuthenticated && requiredPermissions.length > 0) {
            const hasRequiredPermission = requiredPermissions.some(permission => hasPermission(permission));
            if (!hasRequiredPermission) {
                setIsRedirecting(true);
                router.replace('/unauthorized');
                return;
            }
        }

        setShouldRender(true);
    }, [
        isInitialized,
        isLoading,
        isAuthenticated,
        requireAuth,
        requireGuest,
        requiredRoles,
        requiredPermissions,
        hasRole,
        hasPermission,
        router,
        redirectTo,
        isRedirecting
    ]);

    if (!isInitialized || isLoading || isRedirecting || !shouldRender) {
        return fallback || (
            <div className="flex items-center justify-center min-h-[200px]">
                <Spinner size="lg" />
            </div>
        );
    }

    return <>{children}</>;
}