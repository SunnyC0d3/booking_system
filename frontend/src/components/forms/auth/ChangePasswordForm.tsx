'use client';

import { useState, useEffect } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { Eye, EyeOff, Lock } from 'lucide-react';

export function ChangePasswordForm() {
    const { changePassword, requireAuth, isLoading } = useAuthUtils();
    const [formData, setFormData] = useState({
        current_password: '',
        password: '',
        password_confirmation: ''
    });
    const [showPasswords, setShowPasswords] = useState({
        current: false,
        new: false,
        confirm: false
    });
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (!requireAuth()) return;
    }, [requireAuth]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Validation
        if (!formData.current_password) {
            toast.error('Please enter your current password');
            return;
        }

        if (!formData.password) {
            toast.error('Please enter a new password');
            return;
        }

        if (formData.password.length < 8) {
            toast.error('New password must be at least 8 characters');
            return;
        }

        if (formData.password !== formData.password_confirmation) {
            toast.error('New passwords do not match');
            return;
        }

        if (formData.current_password === formData.password) {
            toast.error('New password must be different from current password');
            return;
        }

        setIsSaving(true);
        try {
            await changePassword(formData);

            toast.success('Password changed successfully');

            setFormData({
                current_password: '',
                password: '',
                password_confirmation: ''
            });
        } catch (error: any) {
            console.error('Password change failed:', error);
        } finally {
            setIsSaving(false);
        }
    };

    const handleInputChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const togglePasswordVisibility = (field: 'current' | 'new' | 'confirm') => {
        setShowPasswords(prev => ({ ...prev, [field]: !prev[field] }));
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                    <Lock className="h-5 w-5" />
                    <span>Change Password</span>
                </CardTitle>
                <p className="text-sm text-gray-600">
                    Update your password to keep your account secure.
                </p>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="current_password">Current Password</Label>
                        <div className="relative">
                            <Input
                                id="current_password"
                                type={showPasswords.current ? 'text' : 'password'}
                                placeholder="Enter your current password"
                                value={formData.current_password}
                                onChange={(e) => handleInputChange('current_password', e.target.value)}
                                required
                                disabled={isSaving || isLoading}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                                onClick={() => togglePasswordVisibility('current')}
                                disabled={isSaving || isLoading}
                            >
                                {showPasswords.current ? (
                                    <EyeOff className="h-4 w-4" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">New Password</Label>
                        <div className="relative">
                            <Input
                                id="password"
                                type={showPasswords.new ? 'text' : 'password'}
                                placeholder="Enter your new password (min. 8 characters)"
                                value={formData.password}
                                onChange={(e) => handleInputChange('password', e.target.value)}
                                required
                                disabled={isSaving || isLoading}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                                onClick={() => togglePasswordVisibility('new')}
                                disabled={isSaving || isLoading}
                            >
                                {showPasswords.new ? (
                                    <EyeOff className="h-4 w-4" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">Confirm New Password</Label>
                        <div className="relative">
                            <Input
                                id="password_confirmation"
                                type={showPasswords.confirm ? 'text' : 'password'}
                                placeholder="Confirm your new password"
                                value={formData.password_confirmation}
                                onChange={(e) => handleInputChange('password_confirmation', e.target.value)}
                                required
                                disabled={isSaving || isLoading}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                                onClick={() => togglePasswordVisibility('confirm')}
                                disabled={isSaving || isLoading}
                            >
                                {showPasswords.confirm ? (
                                    <EyeOff className="h-4 w-4" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    <div className="bg-gray-50 p-3 rounded-lg">
                        <p className="text-xs font-medium text-gray-700 mb-2">Password Requirements:</p>
                        <ul className="text-xs text-gray-600 space-y-1">
                            <li className={formData.password.length >= 8 ? 'text-green-600' : ''}>
                                • At least 8 characters
                            </li>
                            <li className={/[A-Z]/.test(formData.password) ? 'text-green-600' : ''}>
                                • At least one uppercase letter (recommended)
                            </li>
                            <li className={/[a-z]/.test(formData.password) ? 'text-green-600' : ''}>
                                • At least one lowercase letter (recommended)
                            </li>
                            <li className={/\d/.test(formData.password) ? 'text-green-600' : ''}>
                                • At least one number (recommended)
                            </li>
                        </ul>
                    </div>

                    <Button
                        type="submit"
                        disabled={isSaving || isLoading}
                        className="w-full"
                    >
                        {isSaving ? 'Changing Password...' : 'Change Password'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}