import { AlertTriangle, Shield, Key } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import Link from 'next/link'

interface AccessDeniedProps {
    type: 'authentication' | 'role' | 'permission'
    requiredRoles?: string[]
    requiredPermissions?: string[]
}

export function AccessDenied({
                                 type,
                                 requiredRoles,
                                 requiredPermissions
                             }: AccessDeniedProps) {
    const getContent = () => {
        switch (type) {
            case 'authentication':
                return {
                    icon: Shield,
                    title: 'Authentication Required',
                    description: 'Please sign in to access this page.',
                    action: (
                        <Button asChild>
                            <Link href="/login">Sign In</Link>
                        </Button>
                    )
                }
            case 'role':
                return {
                    icon: Key,
                    title: 'Insufficient Role',
                    description: `You need one of the following roles: ${requiredRoles?.join(', ')}`,
                    action: (
                        <Button variant="outline" asChild>
                            <Link href="/dashboard">Go to Dashboard</Link>
                        </Button>
                    )
                }
            case 'permission':
                return {
                    icon: AlertTriangle,
                    title: 'Access Denied',
                    description: `You don't have the required permissions: ${requiredPermissions?.join(', ')}`,
                    action: (
                        <Button variant="outline" asChild>
                            <Link href="/dashboard">Go to Dashboard</Link>
                        </Button>
                    )
                }
        }
    }

    const { icon: Icon, title, description, action } = getContent()

    return (
        <div className="min-h-screen flex items-center justify-center p-4">
            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                        <Icon className="w-6 h-6 text-red-600" />
                    </div>
                    <CardTitle className="text-xl">{title}</CardTitle>
                </CardHeader>
                <CardContent className="text-center space-y-4">
                    <p className="text-muted-foreground">{description}</p>
                    {action}
                </CardContent>
            </Card>
        </div>
    )
}