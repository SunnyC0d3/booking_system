'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { Shield, Lock, Key, AlertTriangle, CheckCircle2, Clock, Users, Settings } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { SecurityInfo } from './SecurityInfo';
import { ChangePasswordForm } from '../forms/auth/ChangePasswordForm';
import { useAuth } from '@/stores/authStore';
import { cn } from '@/lib/cn';

interface SecurityDashboardProps {
    className?: string;
}

export const SecurityDashboard: React.FC<SecurityDashboardProps> = ({ className }) => {
    const router = useRouter();
    const { user } = useAuth();
    const [activeTab, setActiveTab] = React.useState('overview');
    const [showPasswordDialog, setShowPasswordDialog] = React.useState(false);

    const handleSecurityAction = (action: 'change-password' | 'enable-2fa' | 'view-sessions') => {
        switch (action) {
            case 'change-password':
                setShowPasswordDialog(true);
                break;
            case 'enable-2fa':
                setActiveTab('two-factor');
                break;
            case 'view-sessions':
                setActiveTab('sessions');
                break;
        }
    };

    const securityFeatures = [
        {
            icon: Lock,
            title: 'Strong Password',
            description: 'Use a unique, complex password for your account',
            status: 'active' as const,
            action: () => setShowPasswordDialog(true),
            actionText: 'Change Password'
        },
        {
            icon: Key,
            title: 'Two-Factor Authentication',
            description: 'Add an extra layer of security to your account',
            status: user?.security_info?.two_factor_enabled ? 'active' : 'inactive' as const,
            action: () => setActiveTab('two-factor'),
            actionText: user?.security_info?.two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'
        },
        {
            icon: Users,
            title: 'Active Sessions',
            description: 'Monitor and manage your active login sessions',
            status: 'active' as const,
            action: () => setActiveTab('sessions'),
            actionText: 'View Sessions'
        },
        {
            icon: Settings,
            title: 'Account Recovery',
            description: 'Ensure you can recover your account if needed',
            status: user?.email_verified_at ? 'active' : 'warning' as const,
            action: () => router.push('/account/settings'),
            actionText: 'Update Settings'
        }
    ];

    return (
        <div className={cn('space-y-6', className)}>
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold">Security Dashboard</h1>
                    <p className="text-muted-foreground">
                        Manage your account security settings and monitor activity
                    </p>
                </div>
                <Button
                    variant="outline"
                    onClick={() => setShowPasswordDialog(true)}
                    className="gap-2"
                >
                    <Lock className="h-4 w-4" />
                    Change Password
                </Button>
            </div>

            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="password">Password</TabsTrigger>
                    <TabsTrigger value="two-factor">Two-Factor Auth</TabsTrigger>
                    <TabsTrigger value="sessions">Sessions</TabsTrigger>
                </TabsList>

                {/* Overview Tab */}
                <TabsContent value="overview" className="space-y-6">
                    {/* Security Info Card */}
                    <SecurityInfo onSecurityAction={handleSecurityAction} />

                    {/* Security Features Grid */}
                    <div>
                        <h2 className="text-lg font-semibold mb-4">Security Features</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {securityFeatures.map((feature, index) => {
                                const Icon = feature.icon;
                                return (
                                    <Card key={index} className="relative">
                                        <CardHeader className="pb-2">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className={cn(
                                                        "p-2 rounded-lg",
                                                        feature.status === 'active'
                                                            ? "bg-green-100 text-green-600 dark:bg-green-900/20"
                                                            : feature.status === 'warning'
                                                                ? "bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20"
                                                                : "bg-gray-100 text-gray-600 dark:bg-gray-800"
                                                    )}>
                                                        <Icon className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-sm">{feature.title}</CardTitle>
                                                    </div>
                                                </div>
                                                <Badge
                                                    variant={
                                                        feature.status === 'active'
                                                            ? 'default'
                                                            : feature.status === 'warning'
                                                                ? 'secondary'
                                                                : 'outline'
                                                    }
                                                    className={cn(
                                                        feature.status === 'active' && "bg-green-100 text-green-800 border-green-200",
                                                        feature.status === 'warning' && "bg-yellow-100 text-yellow-800 border-yellow-200"
                                                    )}
                                                >
                                                    {feature.status === 'active' ? 'Active' :
                                                        feature.status === 'warning' ? 'Needs Setup' : 'Inactive'}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <CardDescription className="mb-3">
                                                {feature.description}
                                            </CardDescription>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={feature.action}
                                                className="w-full"
                                            >
                                                {feature.actionText}
                                            </Button>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </TabsContent>

                {/* Password Tab */}
                <TabsContent value="password" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="h-5 w-5" />
                                Password Security
                            </CardTitle>
                            <CardDescription>
                                Keep your account secure with a strong, unique password
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChangePasswordForm
                                onSuccess={() => {
                                    // Password changed successfully
                                }}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Two-Factor Auth Tab */}
                <TabsContent value="two-factor" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                Two-Factor Authentication
                            </CardTitle>
                            <CardDescription>
                                Add an extra layer of security to your account
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="text-center py-8">
                                <Key className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">Two-Factor Authentication</h3>
                                <p className="text-muted-foreground mb-4">
                                    Two-factor authentication is not yet implemented. This feature will be available soon.
                                </p>
                                <Badge variant="secondary">Coming Soon</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Sessions Tab */}
                <TabsContent value="sessions" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Active Sessions
                            </CardTitle>
                            <CardDescription>
                                Monitor and manage your active login sessions
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="text-center py-8">
                                <Users className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">Session Management</h3>
                                <p className="text-muted-foreground mb-4">
                                    Session management is not yet implemented. This feature will be available soon.
                                </p>
                                <Badge variant="secondary">Coming Soon</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            {/* Change Password Dialog */}
            <Dialog open={showPasswordDialog} onOpenChange={setShowPasswordDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Change Password</DialogTitle>
                    </DialogHeader>
                    <ChangePasswordForm
                        onSuccess={() => setShowPasswordDialog(false)}
                        onCancel={() => setShowPasswordDialog(false)}
                    />
                </DialogContent>
            </Dialog>
        </div>
    );
};