'use client';

import { useState, useTransition, useCallback } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button, Input, Label, Checkbox } from '@/components/ui';
import Link from 'next/link';
import { Eye, EyeOff, Loader2 } from 'lucide-react';
import { z } from 'zod';

const loginSchema = z.object({
    email: z.string().email('Please enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
    remember: z.boolean().default(false)
});

type LoginFormData = z.infer<typeof loginSchema>;

interface LoginFormProps {
    onSuccess?: () => void;
    redirectPath?: string;
    className?: string;
}

export default function LoginForm({
                                      onSuccess,
                                      redirectPath,
                                      className
                                  }: LoginFormProps) {
    const { login, redirectAfterLogin, isLoading } = useAuthUtils();
    const [isPending, startTransition] = useTransition();
    const [formData, setFormData] = useState<LoginFormData>({
        email: '',
        password: '',
        remember: false
    });
    const [showPassword, setShowPassword] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof LoginFormData, string>>>({});

    const validateField = useCallback((field: keyof LoginFormData, value: any) => {
        try {
            loginSchema.pick({ [field]: true }).parse({ [field]: value });
            setFieldErrors(prev => ({ ...prev, [field]: undefined }));
            return true;
        } catch (error) {
            if (error instanceof z.ZodError) {
                setFieldErrors(prev => ({
                    ...prev,
                    [field]: error.errors[0]?.message
                }));
            }
            return false;
        }
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        setFieldErrors({});

        const validation = loginSchema.safeParse(formData);
        if (!validation.success) {
            const errors: Partial<Record<keyof LoginFormData, string>> = {};
            validation.error.errors.forEach(error => {
                if (error.path[0]) {
                    errors[error.path[0] as keyof LoginFormData] = error.message;
                }
            });
            setFieldErrors(errors);

            const firstErrorField = Object.keys(errors)[0];
            if (firstErrorField) {
                const element = document.getElementById(firstErrorField);
                element?.focus();
            }
            return;
        }

        startTransition(async () => {
            try {
                await login({
                    email: formData.email,
                    password: formData.password,
                    remember: formData.remember
                });

                onSuccess?.();

                if (redirectPath) {
                    window.location.href = redirectPath;
                } else {
                    redirectAfterLogin();
                }
            } catch (error) {
                console.error('Login failed:', error);
            }
        });
    };

    const handleInputChange = useCallback((field: keyof LoginFormData, value: string | boolean) => {
        setFormData(prev => ({ ...prev, [field]: value }));

        if (typeof value === 'string' && value.length > 0) {
            validateField(field, value);
        } else if (field === 'remember') {
            validateField(field, value);
        }
    }, [validateField]);

    const togglePasswordVisibility = useCallback(() => {
        setShowPassword(prev => !prev);
    }, []);

    const isSubmitting = isLoading || isPending;

    return (
        <form onSubmit={handleSubmit} className={`space-y-6 ${className}`} noValidate>
            <div className="space-y-2">
                <Label htmlFor="email">
                    Email Address
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="Enter your email"
                    value={formData.email}
                    onChange={(e) => handleInputChange('email', e.target.value)}
                    onBlur={() => formData.email && validateField('email', formData.email)}
                    required
                    disabled={isSubmitting}
                    aria-invalid={!!fieldErrors.email}
                    aria-describedby={fieldErrors.email ? 'email-error' : undefined}
                    autoComplete="email"
                    className={fieldErrors.email ? 'border-red-500 focus:border-red-500' : ''}
                />
                {fieldErrors.email && (
                    <p id="email-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.email}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="password">
                    Password
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <div className="relative">
                    <Input
                        id="password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Enter your password"
                        value={formData.password}
                        onChange={(e) => handleInputChange('password', e.target.value)}
                        onBlur={() => formData.password && validateField('password', formData.password)}
                        required
                        disabled={isSubmitting}
                        aria-invalid={!!fieldErrors.password}
                        aria-describedby={fieldErrors.password ? 'password-error' : 'password-toggle'}
                        autoComplete="current-password"
                        className={`pr-10 ${fieldErrors.password ? 'border-red-500 focus:border-red-500' : ''}`}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={togglePasswordVisibility}
                        disabled={isSubmitting}
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                        id="password-toggle"
                    >
                        {showPassword ? (
                            <EyeOff className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <Eye className="h-4 w-4" aria-hidden="true" />
                        )}
                    </Button>
                </div>
                {fieldErrors.password && (
                    <p id="password-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.password}
                    </p>
                )}
            </div>

            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="remember"
                        name="remember"
                        checked={formData.remember}
                        onCheckedChange={(checked) => handleInputChange('remember', checked as boolean)}
                        disabled={isSubmitting}
                        aria-describedby="remember-description"
                    />
                    <Label htmlFor="remember" className="text-sm cursor-pointer">
                        Remember me
                    </Label>
                </div>

                <Link
                    href="/forgot-password"
                    className="text-sm text-blue-600 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    tabIndex={isSubmitting ? -1 : 0}
                >
                    Forgot password?
                </Link>
            </div>

            <Button
                type="submit"
                disabled={isSubmitting}
                className="w-full relative"
                aria-describedby="submit-status"
            >
                {isSubmitting && (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                )}
                {isSubmitting ? 'Signing in...' : 'Sign In'}
            </Button>

            <div className="text-center text-sm">
                Don't have an account?{' '}
                <Link
                    href="/register"
                    className="text-blue-600 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    tabIndex={isSubmitting ? -1 : 0}
                >
                    Sign up
                </Link>
            </div>

            <div className="sr-only" aria-live="polite" aria-atomic="true" id="submit-status">
                {isSubmitting && 'Signing in, please wait...'}
            </div>
        </form>
    );
}