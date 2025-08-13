import { cookies } from 'next/headers'
import { redirect } from 'next/navigation'
import { AccessDenied } from '@/components/auth/AccessDenied'

interface ServerAuthGuardProps {
    children: React.ReactNode
    requiredRoles?: string[]
    requiredPermissions?: string[]
}

export async function ServerAuthGuard({
                                          children,
                                          requiredRoles = [],
                                          requiredPermissions = []
                                      }: ServerAuthGuardProps) {
    const cookieStore = await cookies()
    const authToken = cookieStore.get('auth-token')?.value

    if (!authToken) {
        redirect('/login')
    }

    const user = await validateAuthToken(authToken)

    if (!user) {
        redirect('/login')
    }

    if (requiredRoles.length > 0) {
        const hasRequiredRole = requiredRoles.some(role =>
            user.roles?.includes(role)
        )
        if (!hasRequiredRole) {
            return <AccessDenied type="role" requiredRoles={requiredRoles} />
        }
    }

    if (requiredPermissions.length > 0) {
        const hasRequiredPermission = requiredPermissions.some(permission =>
            user.permissions?.includes(permission)
        )
        if (!hasRequiredPermission) {
            return <AccessDenied type="permission" requiredPermissions={requiredPermissions} />
        }
    }

    return <>{children}</>
}

// Helper function to validate auth token (implement based on your auth system)
async function validateAuthToken(token: string) {
    try {
        const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/auth/validate`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            next: { revalidate: 300 }
        })

        if (!response.ok) {
            return null
        }

        return response.json()
    } catch (error) {
        console.error('Auth validation error:', error)
        return null
    }
}