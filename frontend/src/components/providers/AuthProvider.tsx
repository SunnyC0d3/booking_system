'use client'

import * as React from 'react'
import { useAuth } from '@/stores/authStore'
import { LoadingPage } from '@/components/ui'

interface AuthProviderProps {
    children: React.ReactNode
}

export function AuthProvider({ children }: AuthProviderProps) {
    const { isInitialized, initialize } = useAuth()

    React.useEffect(() => {
        if (!isInitialized) {
            initialize()
        }
    }, [isInitialized, initialize])

    if (!isInitialized) {
        return <LoadingPage message="Initializing..." />
    }

    return <>{children}</>
}