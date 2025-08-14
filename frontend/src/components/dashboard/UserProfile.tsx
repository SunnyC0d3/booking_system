'use client';

import { useState, useEffect } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Badge, Button, Input, Label, Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import { toast } from 'sonner';
import { User, Mail, Calendar, Shield } from 'lucide-react';

export function UserProfile() {
    const {
        user,
        updateUser,
        isLoading,
        requireAuth,
        isEmailVerified,
        needsEmailVerification
    } = useAuthUtils();

    const [formData, setFormData] = useState({
        name: '',
        email: ''
    });
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (!requireAuth()) return;
    }, [requireAuth]);

    useEffect(() => {
        if (user) {
            setFormData({
                name: user.name || '',
                email: user.email || ''
            });
        }
    }, [user]);

    const handleSave = async () => {
        if (!user) return;

        if (!formData.name.trim()) {
            toast.error('Name is required');
            return;
        }

        setIsSaving(true);
        try {
            await updateUser({
                name: formData.name.trim()
            });

            toast.success('Profile updated successfully');
        } catch (error: any) {
            console.error('Profile update failed:', error);
            toast.error('Failed to update profile');
        } finally {
            setIsSaving(false);
        }
    };

    const handleInputChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    if (isLoading || !user) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="animate-pulse space-y-4">
                        <div className="h-4 bg-gray-200 rounded w-1/4"></div>
                        <div className="h-10 bg-gray-200 rounded"></div>
                        <div className="h-4 bg-gray-200 rounded w-1/4"></div>
                        <div className="h-10 bg-gray-200 rounded"></div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {needsEmailVerification && (
                <Card className="border-yellow-200 bg-yellow-50">
                    <CardContent className="p-4">
                        <div className="flex items-center space-x-2">
                            <Mail className="h-4 w-4 text-yellow-600" />
                            <span className="text-sm text-yellow-800">
                Please verify your email address to access all features.
              </span>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                        <User className="h-5 w-5" />
                        <span>Profile Information</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="name">Full Name</Label>
                        <Input
                            id="name"
                            value={formData.name}
                            onChange={(e) => handleInputChange('name', e.target.value)}
                            disabled={isSaving}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Email Address</Label>
                        <div className="flex items-center space-x-2">
                            <Input
                                id="email"
                                value={formData.email}
                                disabled
                                className="flex-1"
                            />
                            <Badge variant={isEmailVerified ? 'default' : 'secondary'}>
                                {isEmailVerified ? 'Verified' : 'Unverified'}
                            </Badge>
                        </div>
                        <p className="text-xs text-gray-500">
                            Email address cannot be changed. Contact support if needed.
                        </p>
                    </div>

                    <Button
                        onClick={handleSave}
                        disabled={isSaving || formData.name === user.name}
                    >
                        {isSaving ? 'Saving...' : 'Save Changes'}
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                        <Shield className="h-5 w-5" />
                        <span>Account Details</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label className="text-sm font-medium">Role</Label>
                            <p className="text-sm text-gray-600">
                                {user.role?.name || 'User'}
                            </p>
                        </div>

                        <div>
                            <Label className="text-sm font-medium">Member Since</Label>
                            <p className="text-sm text-gray-600 flex items-center space-x-1">
                                <Calendar className="h-3 w-3" />
                                <span>
                  {user.created_at
                      ? new Date(user.created_at).toLocaleDateString()
                      : 'Unknown'
                  }
                </span>
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}