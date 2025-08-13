'use client'

import { useAuth } from '@/stores/authStore'
import { useMemo } from 'react'

export function useAuthGuard() {
    const {
        isAuthenticated,
        isInitialized,
        isLoading,
        user,
        hasRole,
        hasPermission,
        isEmailVerified,
        needsEmailVerification
    } = useAuth()

    const canAccess = useMemo(() =>
        (roles?: string[], permissions?: string[]) => {
            if (!isAuthenticated) return false

            if (roles && roles.length > 0) {
                const hasRequiredRole = roles.some(role => hasRole(role))
                if (!hasRequiredRole) return false
            }

            if (permissions && permissions.length > 0) {
                const hasRequiredPermission = permissions.some(permission =>
                    hasPermission(permission)
                )
                if (!hasRequiredPermission) return false
            }

            return true
        }, [isAuthenticated, hasRole, hasPermission]
    )

    return {
        isAuthenticated,
        isInitialized,
        isLoading,
        user,
        hasRole,
        hasPermission,
        canAccess,
        isEmailVerified,
        needsEmailVerification,
    }
}
