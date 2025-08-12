'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { User, Mail, Lock, Check } from 'lucide-react';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { RegisterFormData } from '@/types/auth';

const passwordStrength = {
    minLength: (password: string) => password.length >= 8,
    hasUppercase: (password: string) => /[A-Z]/.test(password),
    hasLowercase: (password: string) => /[a-z]/.test(password),
    hasNumber: (password: string) => /\d/.test(password),
    hasSpecial: (password: string) => /[!@#$%^&*(),.?":{}|<>]/.test(password),
};

const registerSchema = z.object({
    name: z
        .string()
        .min(1, 'Full name is required')
        .min(2, 'Name must be at least 2 characters')
        .max(50, 'Name must be less than 50 characters'),
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address'),
    password: z
        .string()
        .min(8, 'Password must be at least 8 characters')
        .refine(
            (password) => Object.values(passwordStrength).every(check => check(password)),
            'Password must contain uppercase, lowercase, number, and special character'
        ),
    password_confirmation: z
        .string()
        .min(1, 'Password confirmation is required'),
    terms_accepted: z
        .boolean()
        .refine(val => val === true, 'You must accept the terms and conditions'),
    marketing_consent: z.boolean().default(false),
}).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
});

interface RegisterFormProps {
    redirectTo?: string;
    showSignInLink?: boolean;
    onSuccess?: () => void;
}

export const RegisterForm: React.FC<RegisterFormProps> = ({redirectTo = '/dashboard', showSignInLink = true, onSuccess,}) => {
    const router = useRouter();
    const { register: registerUser, isLoading, error, clearError } = useAuth();

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        setError,
    } = useForm<RegisterFormData>({
        resolver: zodResolver(registerSchema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            terms_accepted: false,
            marketing_consent: false,
        },
    });

    const watchedPassword = watch('password', '');

    // Clear errors when form changes
    React.useEffect(() => {
        if (error) {
            clearError();
        }
    }, [watch('email'), watch('password'), error, clearError]);

    const onSubmit = async (data: RegisterFormData) => {
        try {
            clearError();

            await registerUser({
                name: data.name,
                email: data.email,
                password: data.password,
                password_confirmation: data.password_confirmation,
                terms_accepted: data.terms_accepted,
                marketing_consent: data.marketing_consent,
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
                        setError(field as keyof RegisterFormData, {
                            type: 'manual',
                            message: messages[0],
                        });
                    }
                });
            }
        }
    };

    // Password strength indicator
    const getPasswordStrength = () => {
        if (!watchedPassword) return { score: 0, text: '', color: '' };

        const checks = Object.values(passwordStrength).map(check => check(watchedPassword));
        const score = checks.filter(Boolean).length;

        if (score < 2) return { score, text: 'Weak', color: 'text-error' };
        if (score < 4) return { score, text: 'Medium', color: 'text-warning' };
        if (score < 5) return { score, text: 'Strong', color: 'text-success' };
        return { score, text: 'Very Strong', color: 'text-success' };
    };

    const strength = getPasswordStrength();

    return (
        <Card className="w-full max-w-md mx-auto">
            <CardHeader className="text-center">
                <CardTitle className="text-2xl font-bold text-gradient">
                    Create Account
                </CardTitle>
                <p className="text-muted-foreground">
                    Join Creative Business today
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    {/* Global Error */}
                    {error && (
                        <div className="alert alert-error">
                            <p className="text-sm">{error}</p>
                        </div>
                    )}

                    {/* Name Field */}
                    <Input
                        {...register('name')}
                        type="text"
                        label="Full Name"
                        placeholder="Enter your full name"
                        leftIcon={<User className="h-4 w-4" />}
                        error={errors.name?.message || ''}
                        required
                        autoComplete="name"
                        autoFocus
                    />

                    {/* Email Field */}
                    <Input
                        {...register('email')}
                        type="email"
                        label="Email Address"
                        placeholder="Enter your email"
                        leftIcon={<Mail className="h-4 w-4" />}
                        error={errors.email?.message || ''}
                        required
                        autoComplete="email"
                    />

                    {/* Password Field */}
                    <div className="space-y-2">
                        <Input
                            {...register('password')}
                            type="password"
                            label="Password"
                            placeholder="Create a strong password"
                            leftIcon={<Lock className="h-4 w-4" />}
                            error={errors.password?.message || ''}
                            required
                            autoComplete="new-password"
                        />

                        {/* Password Strength Indicator */}
                        {watchedPassword && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-xs">
                                    <span>Password strength:</span>
                                    <span className={strength.color}>{strength.text}</span>
                                </div>
                                <div className="flex space-x-1">
                                    {[...Array(5)].map((_, i) => (
                                        <div
                                            key={i}
                                            className={`h-1 flex-1 rounded ${
                                                i < strength.score
                                                    ? strength.score < 2
                                                        ? 'bg-error'
                                                        : strength.score < 4
                                                            ? 'bg-warning'
                                                            : 'bg-success'
                                                    : 'bg-muted'
                                            }`}
                                        />
                                    ))}
                                </div>

                                {/* Password Requirements */}
                                <div className="grid grid-cols-2 gap-1 text-xs">
                                    <div className={`flex items-center gap-1 ${
                                        passwordStrength.minLength(watchedPassword) ? 'text-success' : 'text-muted-foreground'
                                    }`}>
                                        <Check className="h-3 w-3" />
                                        8+ characters
                                    </div>
                                    <div className={`flex items-center gap-1 ${
                                        passwordStrength.hasUppercase(watchedPassword) ? 'text-success' : 'text-muted-foreground'
                                    }`}>
                                        <Check className="h-3 w-3" />
                                        Uppercase
                                    </div>
                                    <div className={`flex items-center gap-1 ${
                                        passwordStrength.hasLowercase(watchedPassword) ? 'text-success' : 'text-muted-foreground'
                                    }`}>
                                        <Check className="h-3 w-3" />
                                        Lowercase
                                    </div>
                                    <div className={`flex items-center gap-1 ${
                                        passwordStrength.hasNumber(watchedPassword) ? 'text-success' : 'text-muted-foreground'
                                    }`}>
                                        <Check className="h-3 w-3" />
                                        Number
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Confirm Password Field */}
                    <Input
                        {...register('password_confirmation')}
                        type="password"
                        label="Confirm Password"
                        placeholder="Confirm your password"
                        leftIcon={<Lock className="h-4 w-4" />}
                        error={errors.password_confirmation?.message || ''}
                        required
                        autoComplete="new-password"
                    />

                    {/* Terms & Conditions */}
                    <div className="space-y-3">
                        <label className="flex items-start space-x-2 cursor-pointer">
                            <input
                                {...register('terms_accepted')}
                                type="checkbox"
                                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0 mt-1"
                            />
                            <span className="text-sm text-muted-foreground leading-relaxed">
                                I agree to the{' '}
                                <Link href="/terms" className="text-primary hover:text-primary/80">
                                    Terms of Service
                                </Link>{' '}
                                and{' '}
                                <Link href="/privacy" className="text-primary hover:text-primary/80">
                                    Privacy Policy
                                </Link>
                            </span>
                        </label>
                        {errors.terms_accepted && (
                            <p className="text-sm text-error">{errors.terms_accepted.message}</p>
                        )}

                        <label className="flex items-start space-x-2 cursor-pointer">
                            <input
                                {...register('marketing_consent')}
                                type="checkbox"
                                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0 mt-1"
                            />
                            <span className="text-sm text-muted-foreground leading-relaxed">
                                I'd like to receive marketing emails about new products and promotions
                            </span>
                        </label>
                    </div>

                    {/* Submit Button */}
                    <Button
                        type="submit"
                        variant="default"
                        size="lg"
                        className="w-full"
                        loading={isLoading || isSubmitting}
                    >
                        Create Account
                    </Button>
                </form>
            </CardContent>

            {showSignInLink && (
                <CardFooter className="justify-center">
                    <p className="text-sm text-muted-foreground">
                        Already have an account?{' '}
                        <Link
                            href="/login"
                            className="text-primary hover:text-primary/80 font-medium transition-colors"
                        >
                            Sign in here
                        </Link>
                    </p>
                </CardFooter>
            )}
        </Card>
    );
};

export default RegisterForm;