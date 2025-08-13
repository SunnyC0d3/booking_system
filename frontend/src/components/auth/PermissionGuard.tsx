'use client'

import * as React from 'react'
import { useAuth } from '@/stores/authStore'
import { AccessDenied } from '@/components/auth/AccessDenied'
import { Spinner } from '@/components/ui/loading'

interface PermissionGuardProps {
    children: React.ReactNode
    requiredRoles?: string[]
    requiredPermissions?: string[]
    fallback?: React.ReactNode
}

export function PermissionGuard({
                                    children,
                                    requiredRoles = [],
                                    requiredPermissions = [],
                                    fallback
                                }: PermissionGuardProps) {
    const {
        isAuthenticated,
        isLoading,
        hasRole,
        hasPermission
    } = useAuth()

    if (isLoading) {
        return fallback ?? <Spinner />
    }

    if (!isAuthenticated) {
        return <AccessDenied type="authentication" />
    }

    if (requiredRoles.length > 0) {
        const hasRequiredRole = requiredRoles.some(role => hasRole(role))
        if (!hasRequiredRole) {
            return <AccessDenied type="role" requiredRoles={requiredRoles} />
        }
    }

    if (requiredPermissions.length > 0) {
        const hasRequiredPermission = requiredPermissions.some(permission =>
            hasPermission(permission)
        )
        if (!hasRequiredPermission) {
            return <AccessDenied type="permission" requiredPermissions={requiredPermissions} />
        }
    }

    return <>{children}</>
}