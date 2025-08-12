'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Mail, Lock, Eye, EyeOff, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { LoginFormData } from '@/types/auth';
import { cn } from '@/lib/cn';

const loginSchema = z.object({
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address'),
    password: z
        .string()
        .min(1, 'Password is required')
        .min(6, 'Password must be at least 6 characters'),
    remember: z.boolean().default(false),
});

interface LoginFormProps {
    redirectTo?: string;
    showSignUpLink?: boolean;
    onSuccess?: () => void;
}

const PasswordToggle = React.memo(({
                                       showPassword,
                                       onToggle
                                   }: {
    showPassword: boolean;
    onToggle: () => void;
}) => (
    <button
        type="button"
        onClick={onToggle}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded"
        tabIndex={-1}
        aria-label={showPassword ? 'Hide password' : 'Show password'}
    >
        {showPassword ? (
            <EyeOff className="h-4 w-4" />
        ) : (
            <Eye className="h-4 w-4" />
        )}
    </button>
));

PasswordToggle.displayName = 'PasswordToggle';

const ErrorAlert = React.memo(({ message }: { message: string }) => (
    <div
        className="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        role="alert"
        aria-live="polite"
    >
        <p className="text-sm font-medium">{message}</p>
    </div>
));

ErrorAlert.displayName = 'ErrorAlert';

export const LoginForm: React.FC<LoginFormProps> = ({
                                                        redirectTo = '/dashboard',
                                                        showSignUpLink = true,
                                                        onSuccess,
                                                    }) => {
    const router = useRouter();
    const { login, isLoading, error, clearError, isAuthenticated } = useAuth();
    const [showPassword, setShowPassword] = React.useState(false);
    const [isSubmittingForm, setIsSubmittingForm] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
        watch,
        setError,
        clearErrors,
        reset,
    } = useForm<LoginFormData>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: '',
            password: '',
            remember: false,
        },
        mode: 'onTouched',
    });

    // Handle successful authentication redirect
    React.useEffect(() => {
        if (isAuthenticated && !isLoading && !isSubmittingForm) {
            console.log('User authenticated, redirecting...');
            if (onSuccess) {
                onSuccess();
            } else {
                router.push(redirectTo);
            }
        }
    }, [isAuthenticated, isLoading, isSubmittingForm, onSuccess, router, redirectTo]);

    const clearErrorsOnChange = React.useCallback(() => {
        if (error) {
            clearError();
        }
        if (errors.email || errors.password) {
            clearErrors(['email', 'password']);
        }
    }, [error, errors.email, errors.password, clearError, clearErrors]);

    const emailValue = watch('email');
    const passwordValue = watch('password');

    React.useEffect(() => {
        clearErrorsOnChange();
    }, [emailValue, passwordValue, clearErrorsOnChange]);

    const onSubmit = React.useCallback(
        async (data: LoginFormData) => {
            if (isSubmittingForm || isLoading) {
                return; // Prevent double submission
            }

            try {
                setIsSubmittingForm(true);
                clearError();

                console.log('Submitting login form with:', { email: data.email, remember: data.remember });

                await login({
                    email: data.email,
                    password: data.password,
                    remember: data.remember,
                });

                console.log('Login successful');
                toast.success('Welcome back!');

                // Don't redirect here, let the useEffect handle it
                // The form state will be handled by the loading states

            } catch (error: any) {
                console.error('Login error:', error);
                setIsSubmittingForm(false);

                // Handle validation errors
                if (error.errors) {
                    Object.entries(error.errors).forEach(([field, messages]) => {
                        if (Array.isArray(messages) && messages.length > 0) {
                            setError(field as keyof LoginFormData, {
                                type: 'server',
                                message: messages[0],
                            });
                        }
                    });
                } else {
                    toast.error(error.message || 'Login failed. Please try again.');
                }
            }
        },
        [login, clearError, setError, isSubmittingForm, isLoading]
    );

    const togglePasswordVisibility = React.useCallback(() => {
        setShowPassword(prev => !prev);
    }, []);

    // Reset form submission state when loading completes
    React.useEffect(() => {
        if (!isLoading && isSubmittingForm) {
            setIsSubmittingForm(false);
        }
    }, [isLoading, isSubmittingForm]);

    const isFormLoading = isLoading || isSubmittingForm;

    return (
        <Card className="w-full max-w-md mx-auto shadow-lg">
            <CardHeader className="text-center space-y-2">
                <CardTitle className="text-2xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                    Welcome Back
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    Sign in to your Creative Business account
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
                    {/* Global Error Display */}
                    {error && !errors.email && !errors.password && (
                        <ErrorAlert message={error} />
                    )}

                    {/* Email Field */}
                    <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium text-foreground">
                            Email Address
                        </label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                {...register('email')}
                                id="email"
                                type="email"
                                placeholder="Enter your email"
                                className={cn(
                                    "pl-10",
                                    errors.email && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="email"
                                autoFocus
                                disabled={isFormLoading}
                                aria-invalid={errors.email ? 'true' : 'false'}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                            />
                        </div>
                        {errors.email && (
                            <p id="email-error" className="text-sm text-red-600" role="alert">
                                {errors.email.message}
                            </p>
                        )}
                    </div>

                    {/* Password Field */}
                    <div className="space-y-2">
                        <label htmlFor="password" className="text-sm font-medium text-foreground">
                            Password
                        </label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                {...register('password')}
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                placeholder="Enter your password"
                                className={cn(
                                    "pl-10 pr-10",
                                    errors.password && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="current-password"
                                disabled={isFormLoading}
                                aria-invalid={errors.password ? 'true' : 'false'}
                                aria-describedby={errors.password ? 'password-error' : undefined}
                            />
                            <PasswordToggle
                                showPassword={showPassword}
                                onToggle={togglePasswordVisibility}
                            />
                        </div>
                        {errors.password && (
                            <p id="password-error" className="text-sm text-red-600" role="alert">
                                {errors.password.message}
                            </p>
                        )}
                    </div>

                    {/* Remember Me & Forgot Password */}
                    <div className="flex items-center justify-between">
                        <label className="flex items-center space-x-2 cursor-pointer">
                            <input
                                {...register('remember')}
                                id="remember"
                                type="checkbox"
                                disabled={isFormLoading}
                                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0 h-4 w-4 disabled:opacity-50"
                            />
                            <span className="text-sm text-muted-foreground select-none">
                                Remember me
                            </span>
                        </label>

                        <Link
                            href="/forgot-password"
                            className="text-sm text-primary hover:text-primary/80 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
                            tabIndex={isFormLoading ? -1 : 0}
                        >
                            Forgot password?
                        </Link>
                    </div>

                    {/* Submit Button */}
                    <Button
                        type="submit"
                        variant="default"
                        size="lg"
                        className="w-full"
                        disabled={isFormLoading}
                        aria-describedby={isFormLoading ? 'loading-text' : undefined}
                    >
                        {isFormLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                <span id="loading-text">
                                    {isAuthenticated ? 'Redirecting...' : 'Signing in...'}
                                </span>
                            </>
                        ) : (
                            'Sign In'
                        )}
                    </Button>
                </form>
            </CardContent>

            {showSignUpLink && (
                <CardFooter className="justify-center pt-4">
                    <p className="text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <Link
                            href="/register"
                            className="text-primary hover:text-primary/80 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
                            tabIndex={isFormLoading ? -1 : 0}
                        >
                            Sign up here
                        </Link>
                    </p>
                </CardFooter>
            )}
        </Card>
    );
};

export default LoginForm;