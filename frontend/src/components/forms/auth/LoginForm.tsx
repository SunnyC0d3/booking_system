import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Mail, Lock, Eye, EyeOff } from 'lucide-react';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { LoginFormData } from '@/types/auth';

// Validation schema
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

export const LoginForm: React.FC<LoginFormProps> = ({
                                                        redirectTo = '/dashboard',
                                                        showSignUpLink = true,
                                                        onSuccess,
                                                    }) => {
    const router = useRouter();
    const { login, isLoading, error, clearError } = useAuth();
    const [showPassword, setShowPassword] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        setError,
    } = useForm<LoginFormData>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: '',
            password: '',
            remember: false,
        },
    });

    // Clear errors when form changes
    React.useEffect(() => {
        if (error) {
            clearError();
        }
    }, [watch('email'), watch('password')]);

    const onSubmit = async (data: LoginFormData) => {
        try {
            clearError();

            await login({
                email: data.email,
                password: data.password,
                remember: data.remember,
            });

            // Success - redirect or call success callback
            if (onSuccess) {
                onSuccess();
            } else {
                router.push(redirectTo);
            }

        } catch (error: any) {
            // Handle validation errors
            if (error.errors) {
                Object.entries(error.errors).forEach(([field, messages]) => {
                    if (Array.isArray(messages) && messages.length > 0) {
                        setError(field as keyof LoginFormData, {
                            type: 'manual',
                            message: messages[0],
                        });
                    }
                });
            }
        }
    };

    return (
        <Card className="w-full max-w-md mx-auto">
            <CardHeader className="text-center">
                <CardTitle className="text-2xl font-bold text-gradient">
                    Welcome Back
                </CardTitle>
                <p className="text-muted-foreground">
                    Sign in to your Creative Business account
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    {/* Global Error */}
                    {error && !errors.email && !errors.password && (
                        <div className="alert alert-error">
                            <p className="text-sm">{error}</p>
                        </div>
                    )}

                    {/* Email Field */}
                    <Input
                        {...register('email')}
                        type="email"
                        label="Email Address"
                        placeholder="Enter your email"
                        leftIcon={<Mail className="h-4 w-4" />}
                        error={errors.email?.message}
                        required
                        autoComplete="email"
                        autoFocus
                    />

                    {/* Password Field */}
                    <div className="relative">
                        <Input
                            {...register('password')}
                            type={showPassword ? 'text' : 'password'}
                            label="Password"
                            placeholder="Enter your password"
                            leftIcon={<Lock className="h-4 w-4" />}
                            rightIcon={
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-muted-foreground hover:text-foreground transition-colors"
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            }
                            error={errors.password?.message}
                            required
                            autoComplete="current-password"
                        />
                    </div>

                    {/* Remember Me & Forgot Password */}
                    <div className="flex items-center justify-between">
                        <label className="flex items-center space-x-2 cursor-pointer">
                            <input
                                {...register('remember')}
                                type="checkbox"
                                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0"
                            />
                            <span className="text-sm text-muted-foreground">
                Remember me
              </span>
                        </label>

                        <Link
                            href="/forgot-password"
                            className="text-sm text-primary hover:text-primary/80 transition-colors"
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
                        loading={isLoading || isSubmitting}
                        loadingText="Signing in..."
                    >
                        Sign In
                    </Button>
                </form>
            </CardContent>

            {showSignUpLink && (
                <CardFooter className="justify-center">
                    <p className="text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <Link
                            href="/register"
                            className="text-primary hover:text-primary/80 font-medium transition-colors"
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