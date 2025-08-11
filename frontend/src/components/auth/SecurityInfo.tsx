'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Shield, AlertTriangle, CheckCircle2, Clock, Users, Key, RefreshCw } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { authApi } from '@/api/auth';
import { formatDistanceToNow } from 'date-fns';

interface SecurityInfoData {
    requires_password_change: boolean;
    days_until_password_expiry: number;
    security_score: number;
    is_account_locked: boolean;
    last_login_at?: string;
    last_login_ip?: string;
    failed_login_attempts: number;
    active_sessions: number;
    two_factor_enabled: boolean;
    password_changed_at?: string;
}

interface SecurityInfoProps {
    className?: string;
    onSecurityAction?: (action: 'change-password' | 'enable-2fa' | 'view-sessions') => void;
}

export const SecurityInfo: React.FC<SecurityInfoProps> = ({
                                                              className,
                                                              onSecurityAction
                                                          }) => {
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['security-info'],
        queryFn: () => authApi.getSecurityInfo(),
        refetchInterval: 30000, // Refresh every 30 seconds
    });

    const getSecurityScoreColor = (score: number) => {
        if (score >= 80) return 'text-green-600 bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800';
        if (score >= 60) return 'text-yellow-600 bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800';
        return 'text-red-600 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800';
    };

    const getSecurityScoreLabel = (score: number) => {
        if (score >= 80) return 'Strong';
        if (score >= 60) return 'Medium';
        return 'Weak';
    };

    if (isLoading) {
        return (
            <Card className={className}>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        Security Overview
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Skeleton className="h-20 w-full" />
                    <Skeleton className="h-16 w-full" />
                    <Skeleton className="h-16 w-full" />
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Card className={className}>
                <CardContent className="pt-6">
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription className="flex items-center justify-between">
                            <span>Failed to load security information.</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => refetch()}
                                className="gap-2"
                            >
                                <RefreshCw className="h-3 w-3" />
                                Retry
                            </Button>
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        );
    }

    const securityInfo = data as SecurityInfoData;

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Security Overview
                </CardTitle>
                <CardDescription>
                    Monitor your account security status and take action if needed
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Security Score */}
                <div className="flex items-center justify-between p-4 rounded-lg border">
                    <div className="flex items-center gap-3">
                        <div className={`p-2 rounded-full ${getSecurityScoreColor(securityInfo.security_score)}`}>
                            <Shield className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="font-medium">Security Score</p>
                            <p className="text-sm text-muted-foreground">
                                Overall account security rating
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-2xl font-bold">
                            {securityInfo.security_score}/100
                        </div>
                        <Badge variant="outline" className={getSecurityScoreColor(securityInfo.security_score)}>
                            {getSecurityScoreLabel(securityInfo.security_score)}
                        </Badge>
                    </div>
                </div>

                {/* Security Alerts */}
                <div className="space-y-3">
                    {securityInfo.requires_password_change && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>Your password needs to be changed for security reasons.</span>
                                <Button
                                    size="sm"
                                    onClick={() => onSecurityAction?.('change-password')}
                                >
                                    Change Password
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}

                    {securityInfo.is_account_locked && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                Your account is temporarily locked due to security concerns.
                                Contact support if you need assistance.
                            </AlertDescription>
                        </Alert>
                    )}

                    {securityInfo.days_until_password_expiry <= 7 && securityInfo.days_until_password_expiry > 0 && (
                        <Alert>
                            <Clock className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>
                                    Your password expires in {securityInfo.days_until_password_expiry} days.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onSecurityAction?.('change-password')}
                                >
                                    Update Password
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}

                    {!securityInfo.two_factor_enabled && (
                        <Alert>
                            <Key className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>Enable two-factor authentication for enhanced security.</span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => onSecurityAction?.('enable-2fa')}
                                >
                                    Enable 2FA
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                {/* Security Details */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-3">
                        <h4 className="font-medium flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                            Account Status
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Two-Factor Auth:</span>
                                <Badge variant={securityInfo.two_factor_enabled ? "default" : "secondary"}>
                                    {securityInfo.two_factor_enabled ? "Enabled" : "Disabled"}
                                </Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Account Status:</span>
                                <Badge variant={securityInfo.is_account_locked ? "destructive" : "default"}>
                                    {securityInfo.is_account_locked ? "Locked" : "Active"}
                                </Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Failed Attempts:</span>
                                <span className={securityInfo.failed_login_attempts > 0 ? "text-red-600" : ""}>
                                    {securityInfo.failed_login_attempts}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <h4 className="font-medium flex items-center gap-2">
                            <Users className="h-4 w-4 text-blue-600" />
                            Session Info
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Active Sessions:</span>
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="p-0 h-auto text-primary"
                                    onClick={() => onSecurityAction?.('view-sessions')}
                                >
                                    {securityInfo.active_sessions}
                                </Button>
                            </div>
                            {securityInfo.last_login_at && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Last Login:</span>
                                    <span title={new Date(securityInfo.last_login_at).toLocaleString()}>
                                        {formatDistanceToNow(new Date(securityInfo.last_login_at), { addSuffix: true })}
                                    </span>
                                </div>
                            )}
                            {securityInfo.last_login_ip && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Last IP:</span>
                                    <code className="text-xs bg-muted px-1 rounded">
                                        {securityInfo.last_login_ip}
                                    </code>
                                </div>
                            )}
                            {securityInfo.password_changed_at && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Password Changed:</span>
                                    <span title={new Date(securityInfo.password_changed_at).toLocaleString()}>
                                        {formatDistanceToNow(new Date(securityInfo.password_changed_at), { addSuffix: true })}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex gap-2 pt-4 border-t">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onSecurityAction?.('change-password')}
                    >
                        Change Password
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onSecurityAction?.('view-sessions')}
                    >
                        Manage Sessions
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => refetch()}
                        className="gap-2"
                    >
                        <RefreshCw className="h-3 w-3" />
                        Refresh
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
};