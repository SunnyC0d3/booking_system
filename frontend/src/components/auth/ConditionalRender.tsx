'use client'

import { useAuthGuard } from '@/hooks/useAuthGuard'

interface ConditionalRenderProps {
    children: React.ReactNode
    fallback?: React.ReactNode
    roles?: string[]
    permissions?: string[]
    requireAuth?: boolean
}

export function ConditionalRender({
                                      children,
                                      fallback = null,
                                      roles = [],
                                      permissions = [],
                                      requireAuth = false
                                  }: ConditionalRenderProps) {
    const { isAuthenticated, canAccess } = useAuthGuard()

    if (requireAuth && !isAuthenticated) {
        return <>{fallback}</>
    }

    if (!canAccess(roles, permissions)) {
        return <>{fallback}</>
    }

    return <>{children}</>
}