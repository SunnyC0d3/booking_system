'use client';

import { useState } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button, Checkbox, Input, Label } from '@/components/ui';
import { toast } from 'sonner';
import Link from 'next/link';
import { Eye, EyeOff } from 'lucide-react';

export function RegisterForm() {
    const { register, redirectAfterLogin, isLoading } = useAuthUtils();
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms_accepted: false,
        marketing_consent: false
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Validation
        if (!formData.name || !formData.email || !formData.password || !formData.password_confirmation) {
            toast.error('Please fill in all fields');
            return;
        }

        if (formData.password !== formData.password_confirmation) {
            toast.error('Passwords do not match');
            return;
        }

        if (formData.password.length < 8) {
            toast.error('Password must be at least 8 characters');
            return;
        }

        if (!formData.terms_accepted) {
            toast.error('Please accept the terms and conditions');
            return;
        }

        try {
            await register(formData);

            toast.success('Account created successfully!');

            // Redirect after successful registration
            redirectAfterLogin();
        } catch (error: any) {
            // Error is already handled by register method with toast
            console.error('Registration failed:', error);
        }
    };

    const handleInputChange = (field: string, value: string | boolean) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
                <Label htmlFor="name">Full Name</Label>
                <Input
                    id="name"
                    type="text"
                    placeholder="Enter your full name"
                    value={formData.name}
                    onChange={(e) => handleInputChange('name', e.target.value)}
                    required
                    disabled={isLoading}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="email">Email Address</Label>
                <Input
                    id="email"
                    type="email"
                    placeholder="Enter your email"
                    value={formData.email}
                    onChange={(e) => handleInputChange('email', e.target.value)}
                    required
                    disabled={isLoading}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <div className="relative">
                    <Input
                        id="password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Create a password (min. 8 characters)"
                        value={formData.password}
                        onChange={(e) => handleInputChange('password', e.target.value)}
                        required
                        disabled={isLoading}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => setShowPassword(!showPassword)}
                        disabled={isLoading}
                    >
                        {showPassword ? (
                            <EyeOff className="h-4 w-4" />
                        ) : (
                            <Eye className="h-4 w-4" />
                        )}
                    </Button>
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirm Password</Label>
                <div className="relative">
                    <Input
                        id="password_confirmation"
                        type={showConfirmPassword ? 'text' : 'password'}
                        placeholder="Confirm your password"
                        value={formData.password_confirmation}
                        onChange={(e) => handleInputChange('password_confirmation', e.target.value)}
                        required
                        disabled={isLoading}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                        disabled={isLoading}
                    >
                        {showConfirmPassword ? (
                            <EyeOff className="h-4 w-4" />
                        ) : (
                            <Eye className="h-4 w-4" />
                        )}
                    </Button>
                </div>
            </div>

            <div className="space-y-4">
                <div className="flex items-start space-x-2">
                    <Checkbox
                        id="terms"
                        checked={formData.terms_accepted}
                        onCheckedChange={(checked) => handleInputChange('terms_accepted', checked as boolean)}
                        disabled={isLoading}
                        required
                    />
                    <Label htmlFor="terms" className="text-sm leading-relaxed">
                        I agree to the{' '}
                        <Link href="/terms" className="text-blue-600 hover:text-blue-500">
                            Terms and Conditions
                        </Link>{' '}
                        and{' '}
                        <Link href="/privacy" className="text-blue-600 hover:text-blue-500">
                            Privacy Policy
                        </Link>
                    </Label>
                </div>

                <div className="flex items-start space-x-2">
                    <Checkbox
                        id="marketing"
                        checked={formData.marketing_consent}
                        onCheckedChange={(checked) => handleInputChange('marketing_consent', checked as boolean)}
                        disabled={isLoading}
                    />
                    <Label htmlFor="marketing" className="text-sm leading-relaxed">
                        I would like to receive marketing emails and updates (optional)
                    </Label>
                </div>
            </div>

            <Button
                type="submit"
                disabled={isLoading}
                className="w-full"
            >
                {isLoading ? 'Creating account...' : 'Create Account'}
            </Button>

            <div className="text-center text-sm">
                Already have an account?{' '}
                <Link href="/login" className="text-blue-600 hover:text-blue-500">
                    Sign in
                </Link>
            </div>
        </form>
    );
}