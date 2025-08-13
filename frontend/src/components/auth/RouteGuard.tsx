'use client'

import * as React from 'react';
import {useRouter} from 'next/navigation';
import {useAuth} from '@/stores/authStore';
import {LoadingPage} from '@/components/ui';
import {RouteGuardProps} from '@/types/auth';

export const RouteGuard: React.FC<RouteGuardProps> = ({
                                                          children,
                                                          requireAuth = false,
                                                          requireGuest = false,
                                                          requiredRoles = [],
                                                          requiredPermissions = [],
                                                          fallback,
                                                          redirectTo,
                                                      }) => {
    const router = useRouter();
    const {
        isAuthenticated,
        isInitialized,
        isLoading,
        hasRole,
        hasPermission,
        initialize
    } = useAuth();

    React.useEffect(() => {
        if (!isInitialized) {
            initialize();
        }
    }, [isInitialized, initialize]);

    if (!isInitialized || isLoading) {
        return fallback || <LoadingPage message="Loading..."/>;
    }

    if (requireGuest && isAuthenticated) {
        const redirect = redirectTo || '/dashboard';
        router.replace(redirect);
        return fallback || <LoadingPage message="Redirecting..."/>;
    }

    if (requireAuth && !isAuthenticated) {
        const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
        const redirect = redirectTo || `/login?redirect=${encodeURIComponent(currentPath)}`;
        router.replace(redirect);
        return fallback || <LoadingPage message="Please sign in..."/>;
    }

    if (requiredRoles.length > 0 && isAuthenticated) {
        const hasRequiredRole = requiredRoles.some(role => hasRole(role));
        if (!hasRequiredRole) {
            const redirect = redirectTo || '/unauthorized';
            router.replace(redirect);
            return fallback || (
                <div className="min-h-screen flex items-center justify-center">
                    <div className="text-center space-y-4">
                        <h1 className="text-2xl font-bold text-foreground">Access Denied</h1>
                        <p className="text-muted-foreground">
                            You don't have the required permissions to access this page.
                        </p>
                    </div>
                </div>
            );
        }
    }

    if (requiredPermissions.length > 0 && isAuthenticated) {
        const hasRequiredPermission = requiredPermissions.some(permission =>
            hasPermission(permission)
        );
        if (!hasRequiredPermission) {
            const redirect = redirectTo || '/unauthorized';
            router.replace(redirect);
            return fallback || (
                <div className="min-h-screen flex items-center justify-center">
                    <div className="text-center space-y-4">
                        <h1 className="text-2xl font-bold text-foreground">Access Denied</h1>
                        <p className="text-muted-foreground">
                            You don't have the required permissions to access this page.
                        </p>
                    </div>
                </div>
            );
        }
    }

    return <>{children}</>;
};

export function withAuth<P extends object>(
    Component: React.ComponentType<P>,
    options: Omit<RouteGuardProps, 'children'> = {}
) {
    return function AuthenticatedComponent(props: P) {
        return (
            <RouteGuard {...options}>
                <Component {...props} />
            </RouteGuard>
        );
    };
}

export function useAuthGuard() {
    const {
        isAuthenticated,
        isInitialized,
        user,
        hasRole,
        hasPermission,
        isEmailVerified,
        needsEmailVerification
    } = useAuth();

    const canAccess = (roles?: string[], permissions?: string[]) => {
        if (!isAuthenticated) return false;

        if (roles && roles.length > 0) {
            const hasRequiredRole = roles.some(role => hasRole(role));
            if (!hasRequiredRole) return false;
        }

        if (permissions && permissions.length > 0) {
            const hasRequiredPermission = permissions.some(permission =>
                hasPermission(permission)
            );
            if (!hasRequiredPermission) return false;
        }

        return true;
    };

    return {
        isAuthenticated,
        isInitialized,
        user,
        hasRole,
        hasPermission,
        canAccess,
        isEmailVerified,
        needsEmailVerification,
    };
}

export default RouteGuard;