'use client'

import { useEffect, useState } from 'react'
import { useAuth } from '@/stores/authStore'
import { LoadingPage } from '@/components/ui'

interface MiddlewareAuthBoundaryProps {
    children: React.ReactNode
}

export function MiddlewareAuthBoundary({ children }: MiddlewareAuthBoundaryProps) {
    const { isAuthenticated, isInitialized, initialize } = useAuth()
    const [isHydrated, setIsHydrated] = useState(false)

    useEffect(() => {
        if (!isInitialized) {
            initialize()
        }
        setIsHydrated(true)
    }, [isInitialized, initialize])

    if (!isHydrated) {
        return <LoadingPage message="Loading..." />
    }

    if (isInitialized && !isAuthenticated) {
        return <LoadingPage message="Syncing authentication..." />
    }

    return <>{children}</>
}