'use client';

import { useState, useEffect } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import {
    Shield,
    Eye,
    Clock,
    Globe,
    Smartphone,
    Monitor,
    AlertTriangle,
    CheckCircle,
    Key,
    Activity
} from 'lucide-react';

interface SecurityEvent {
    id: string;
    type: 'login' | 'logout' | 'password_change' | 'failed_login' | 'token_refresh';
    timestamp: string;
    ip_address: string;
    user_agent: string;
    location?: string;
    success: boolean;
}

interface Session {
    id: string;
    ip_address: string;
    user_agent: string;
    location?: string;
    last_activity: string;
    is_current: boolean;
}

export function SecurityDashboard() {
    const {
        user,
        requireAuth,
        isEmailVerified,
        getAuthHeaders,
        handleLogout
    } = useAuthUtils();

    const [sessions, setSessions] = useState<Session[]>([]);
    const [securityEvents, setSecurityEvents] = useState<SecurityEvent[]>([]);
    const [twoFactorEnabled, setTwoFactorEnabled] = useState(false);
    const [emailNotifications, setEmailNotifications] = useState(true);
    const [isLoading, setIsLoading] = useState(true);
    const [isUpdating, setIsUpdating] = useState(false);

    useEffect(() => {
        if (!requireAuth()) return;
    }, [requireAuth]);

    useEffect(() => {
        if (user) {
            fetchSecurityData();
        }
    }, [user]);

    const fetchSecurityData = async () => {
        setIsLoading(true);
        try {
            const sessionsResponse = await fetch('/api/user/sessions', {
                headers: getAuthHeaders(),
            });

            if (sessionsResponse.ok) {
                const sessionsData = await sessionsResponse.json();
                setSessions(sessionsData.sessions || []);
            }

            const eventsResponse = await fetch('/api/user/security-events', {
                headers: getAuthHeaders(),
            });

            if (eventsResponse.ok) {
                const eventsData = await eventsResponse.json();
                setSecurityEvents(eventsData.events || []);
            }

            const settingsResponse = await fetch('/api/user/security-settings', {
                headers: getAuthHeaders(),
            });

            if (settingsResponse.ok) {
                const settingsData = await settingsResponse.json();
                setTwoFactorEnabled(settingsData.two_factor_enabled || false);
                setEmailNotifications(settingsData.email_notifications || true);
            }

        } catch (error) {
            console.error('Failed to fetch security data:', error);
            toast.error('Failed to load security information');
        } finally {
            setIsLoading(false);
        }
    };

    const handleTwoFactorToggle = async (enabled: boolean) => {
        setIsUpdating(true);
        try {
            const response = await fetch('/api/user/two-factor', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify({ enabled }),
            });

            if (response.ok) {
                setTwoFactorEnabled(enabled);
                toast.success(enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled');
            } else {
                throw new Error('Failed to update two-factor authentication');
            }
        } catch (error) {
            console.error('Two-factor toggle failed:', error);
            toast.error('Failed to update two-factor authentication');
        } finally {
            setIsUpdating(false);
        }
    };

    const handleEmailNotificationsToggle = async (enabled: boolean) => {
        setIsUpdating(true);
        try {
            const response = await fetch('/api/user/security-settings', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify({ email_notifications: enabled }),
            });

            if (response.ok) {
                setEmailNotifications(enabled);
                toast.success('Email notification settings updated');
            } else {
                throw new Error('Failed to update email notifications');
            }
        } catch (error) {
            console.error('Email notifications toggle failed:', error);
            toast.error('Failed to update email notification settings');
        } finally {
            setIsUpdating(false);
        }
    };

    const handleTerminateSession = async (sessionId: string) => {
        try {
            const response = await fetch(`/api/user/sessions/${sessionId}`, {
                method: 'DELETE',
                headers: getAuthHeaders(),
            });

            if (response.ok) {
                setSessions(prev => prev.filter(session => session.id !== sessionId));
                toast.success('Session terminated successfully');
            } else {
                throw new Error('Failed to terminate session');
            }
        } catch (error) {
            console.error('Session termination failed:', error);
            toast.error('Failed to terminate session');
        }
    };

    const handleTerminateAllSessions = async () => {
        try {
            const response = await fetch('/api/user/sessions/terminate-all', {
                method: 'POST',
                headers: getAuthHeaders(),
            });

            if (response.ok) {
                toast.success('All sessions terminated. You will be logged out.');
                setTimeout(() => {
                    handleLogout('/login', 'All sessions terminated');
                }, 2000);
            } else {
                throw new Error('Failed to terminate all sessions');
            }
        } catch (error) {
            console.error('Terminate all sessions failed:', error);
            toast.error('Failed to terminate all sessions');
        }
    };

    const getDeviceIcon = (userAgent: string) => {
        if (userAgent.includes('Mobile')) return <Smartphone className="h-4 w-4" />;
        if (userAgent.includes('Tablet')) return <Smartphone className="h-4 w-4" />;
        return <Monitor className="h-4 w-4" />;
    };

    const getEventIcon = (type: string, success: boolean) => {
        if (!success) return <AlertTriangle className="h-4 w-4 text-red-500" />;

        switch (type) {
            case 'login': return <CheckCircle className="h-4 w-4 text-green-500" />;
            case 'logout': return <Eye className="h-4 w-4 text-gray-500" />;
            case 'password_change': return <Key className="h-4 w-4 text-blue-500" />;
            default: return <Activity className="h-4 w-4 text-gray-500" />;
        }
    };

    if (isLoading) {
        return (
            <div className="space-y-6">
                {[1, 2, 3].map(i => (
                    <Card key={i}>
                        <CardContent className="p-6">
                            <div className="animate-pulse space-y-4">
                                <div className="h-4 bg-gray-200 rounded w-1/4"></div>
                                <div className="h-20 bg-gray-200 rounded"></div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                        <Shield className="h-5 w-5" />
                        <span>Security Overview</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="flex items-center space-x-3">
                            <div className={`p-2 rounded-full ${isEmailVerified ? 'bg-green-100' : 'bg-yellow-100'}`}>
                                {isEmailVerified ? (
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                ) : (
                                    <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                )}
                            </div>
                            <div>
                                <p className="font-medium">Email Verification</p>
                                <p className={`text-sm ${isEmailVerified ? 'text-green-600' : 'text-yellow-600'}`}>
                                    {isEmailVerified ? 'Verified' : 'Pending Verification'}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center space-x-3">
                            <div className={`p-2 rounded-full ${twoFactorEnabled ? 'bg-green-100' : 'bg-gray-100'}`}>
                                <Shield className={`h-4 w-4 ${twoFactorEnabled ? 'text-green-600' : 'text-gray-400'}`} />
                            </div>
                            <div>
                                <p className="font-medium">Two-Factor Auth</p>
                                <p className={`text-sm ${twoFactorEnabled ? 'text-green-600' : 'text-gray-500'}`}>
                                    {twoFactorEnabled ? 'Enabled' : 'Disabled'}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center space-x-3">
                            <div className="p-2 rounded-full bg-blue-100">
                                <Activity className="h-4 w-4 text-blue-600" />
                            </div>
                            <div>
                                <p className="font-medium">Active Sessions</p>
                                <p className="text-sm text-blue-600">
                                    {sessions.length} session{sessions.length !== 1 ? 's' : ''}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Security Settings</CardTitle>
                    <p className="text-sm text-gray-600">
                        Manage your account security preferences
                    </p>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div className="space-y-1">
                            <Label>Two-Factor Authentication</Label>
                            <p className="text-sm text-gray-600">
                                Add an extra layer of security to your account
                            </p>
                        </div>
                        <Switch
                            checked={twoFactorEnabled}
                            onCheckedChange={handleTwoFactorToggle}
                            disabled={isUpdating}
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <div className="space-y-1">
                            <Label>Email Security Notifications</Label>
                            <p className="text-sm text-gray-600">
                                Get notified of suspicious account activity
                            </p>
                        </div>
                        <Switch
                            checked={emailNotifications}
                            onCheckedChange={handleEmailNotificationsToggle}
                            disabled={isUpdating}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Active Sessions</CardTitle>
                        <p className="text-sm text-gray-600">
                            Manage devices that are currently signed into your account
                        </p>
                    </div>
                    {sessions.length > 1 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleTerminateAllSessions}
                        >
                            Terminate All
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {sessions.map((session) => (
                            <div
                                key={session.id}
                                className="flex items-center justify-between p-4 border rounded-lg"
                            >
                                <div className="flex items-center space-x-3">
                                    {getDeviceIcon(session.user_agent)}
                                    <div>
                                        <div className="flex items-center space-x-2">
                                            <p className="font-medium">
                                                {session.user_agent.split(' ')[0] || 'Unknown Device'}
                                            </p>
                                            {session.is_current && (
                                                <Badge variant="outline">Current</Badge>
                                            )}
                                        </div>
                                        <div className="flex items-center space-x-4 text-sm text-gray-600">
                      <span className="flex items-center space-x-1">
                        <Globe className="h-3 w-3" />
                        <span>{session.ip_address}</span>
                      </span>
                                            <span className="flex items-center space-x-1">
                        <Clock className="h-3 w-3" />
                        <span>
                          {new Date(session.last_activity).toLocaleString()}
                        </span>
                      </span>
                                        </div>
                                        {session.location && (
                                            <p className="text-sm text-gray-500">{session.location}</p>
                                        )}
                                    </div>
                                </div>

                                {!session.is_current && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleTerminateSession(session.id)}
                                    >
                                        Terminate
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recent Security Events</CardTitle>
                    <p className="text-sm text-gray-600">
                        Review recent security-related activities on your account
                    </p>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {securityEvents.slice(0, 10).map((event) => (
                            <div
                                key={event.id}
                                className="flex items-center space-x-3 p-3 border rounded-lg"
                            >
                                {getEventIcon(event.type, event.success)}
                                <div className="flex-1">
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium capitalize">
                                            {event.type.replace('_', ' ')}
                                        </p>
                                        <span className="text-sm text-gray-500">
                      {new Date(event.timestamp).toLocaleString()}
                    </span>
                                    </div>
                                    <div className="flex items-center space-x-4 text-sm text-gray-600">
                                        <span>{event.ip_address}</span>
                                        {event.location && <span>{event.location}</span>}
                                    </div>
                                    {!event.success && (
                                        <p className="text-sm text-red-600">Failed attempt</p>
                                    )}
                                </div>
                            </div>
                        ))}

                        {securityEvents.length === 0 && (
                            <p className="text-center text-gray-500 py-8">
                                No recent security events
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}