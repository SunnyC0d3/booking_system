'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { User, Mail, Lock, Check, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { RegisterFormData } from '@/types/auth';
import { cn } from '@/lib/cn';

const passwordStrength = {
    minLength: (password: string) => password.length >= 8,
    hasUppercase: (password: string) => /[A-Z]/.test(password),
    hasLowercase: (password: string) => /[a-z]/.test(password),
    hasNumber: (password: string) => /\d/.test(password),
    hasSpecial: (password: string) => /[!@#$%^&*(),.?":{}|<>]/.test(password),
} as const;

const registerSchema = z.object({
    name: z
        .string()
        .min(1, 'Full name is required')
        .min(2, 'Name must be at least 2 characters')
        .max(50, 'Name must be less than 50 characters')
        .regex(/^[a-zA-Z\s'-]+$/, 'Name can only contain letters, spaces, hyphens, and apostrophes'),
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address')
        .max(100, 'Email must be less than 100 characters'),
    password: z
        .string()
        .min(8, 'Password must be at least 8 characters')
        .max(128, 'Password must be less than 128 characters')
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

const PasswordRequirement = React.memo(({
                                            met,
                                            children
                                        }: {
    met: boolean;
    children: React.ReactNode;
}) => (
    <div className={cn(
        "flex items-center gap-1 text-xs transition-colors",
        met ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'
    )}>
        <Check className={cn(
            "h-3 w-3 transition-colors",
            met ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'
        )} />
        {children}
    </div>
));

PasswordRequirement.displayName = 'PasswordRequirement';

const PasswordStrengthIndicator = React.memo(({ password }: { password: string }) => {
    const getPasswordStrength = React.useMemo(() => {
        if (!password) return { score: 0, text: '', color: '' };

        const checks = Object.values(passwordStrength).map(check => check(password));
        const score = checks.filter(Boolean).length;

        if (score < 2) return { score, text: 'Weak', color: 'text-red-600 dark:text-red-400' };
        if (score < 4) return { score, text: 'Medium', color: 'text-yellow-600 dark:text-yellow-400' };
        if (score < 5) return { score, text: 'Strong', color: 'text-green-600 dark:text-green-400' };
        return { score, text: 'Very Strong', color: 'text-green-600 dark:text-green-400' };
    }, [password]);

    const strengthChecks = React.useMemo(() => [
        { check: passwordStrength.minLength(password), label: '8+ characters' },
        { check: passwordStrength.hasUppercase(password), label: 'Uppercase' },
        { check: passwordStrength.hasLowercase(password), label: 'Lowercase' },
        { check: passwordStrength.hasNumber(password), label: 'Number' },
        { check: passwordStrength.hasSpecial(password), label: 'Special char' },
    ], [password]);

    if (!password) return null;

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Password strength:</span>
                <span className={getPasswordStrength.color}>{getPasswordStrength.text}</span>
            </div>

            <div className="flex space-x-1">
                {[...Array(5)].map((_, i) => (
                    <div
                        key={i}
                        className={cn(
                            "h-1 flex-1 rounded transition-colors",
                            i < getPasswordStrength.score
                                ? getPasswordStrength.score < 2
                                    ? 'bg-red-500'
                                    : getPasswordStrength.score < 4
                                        ? 'bg-yellow-500'
                                        : 'bg-green-500'
                                : 'bg-muted'
                        )}
                    />
                ))}
            </div>

            <div className="grid grid-cols-2 gap-1">
                {strengthChecks.map((item, index) => (
                    <PasswordRequirement key={index} met={item.check}>
                        {item.label}
                    </PasswordRequirement>
                ))}
            </div>
        </div>
    );
});

PasswordStrengthIndicator.displayName = 'PasswordStrengthIndicator';

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

export const RegisterForm: React.FC<RegisterFormProps> = ({
                                                              redirectTo = '/dashboard',
                                                              showSignInLink = true,
                                                              onSuccess,
                                                          }) => {
    const router = useRouter();
    const { register: registerUser, isLoading, error, clearError } = useAuth();

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        setError,
        clearErrors,
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
        mode: 'onTouched',
    });

    const watchedPassword = watch('password', '');
    const emailValue = watch('email');
    const nameValue = watch('name');

    const clearErrorsOnChange = React.useCallback(() => {
        if (error) {
            clearError();
        }
        if (errors.email || errors.name || errors.password) {
            clearErrors(['email', 'name', 'password']);
        }
    }, [error, errors.email, errors.name, errors.password, clearError, clearErrors]);

    React.useEffect(() => {
        clearErrorsOnChange();
    }, [emailValue, nameValue, watchedPassword, clearErrorsOnChange]);

    const onSubmit = React.useCallback(
        async (data: RegisterFormData) => {
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

                if (onSuccess) {
                    onSuccess();
                } else {
                    router.push(redirectTo);
                }

                toast.success('Account created successfully! Welcome to Creative Business.');

            } catch (error: any) {
                if (error.errors) {
                    Object.entries(error.errors).forEach(([field, messages]) => {
                        if (Array.isArray(messages) && messages.length > 0) {
                            setError(field as keyof RegisterFormData, {
                                type: 'server',
                                message: messages[0],
                            });
                        }
                    });
                } else {
                    toast.error(error.message || 'Registration failed. Please try again.');
                }
            }
        },
        [registerUser, clearError, onSuccess, router, redirectTo, setError]
    );

    const isFormLoading = isLoading || isSubmitting;

    return (
        <Card className="w-full max-w-md mx-auto shadow-lg">
            <CardHeader className="text-center space-y-2">
                <CardTitle className="text-2xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                    Create Account
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    Join Creative Business today
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
                    {error && <ErrorAlert message={error} />}

                    <div className="space-y-2">
                        <label htmlFor="name" className="text-sm font-medium text-foreground">
                            Full Name
                        </label>
                        <div className="relative">
                            <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                {...register('name')}
                                id="name"
                                type="text"
                                placeholder="Enter your full name"
                                className={cn(
                                    "pl-10",
                                    errors.name && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="name"
                                autoFocus
                                aria-invalid={errors.name ? 'true' : 'false'}
                                aria-describedby={errors.name ? 'name-error' : undefined}
                            />
                        </div>
                        {errors.name && (
                            <p id="name-error" className="text-sm text-red-600" role="alert">
                                {errors.name.message}
                            </p>
                        )}
                    </div>

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

                    <div className="space-y-2">
                        <label htmlFor="password" className="text-sm font-medium text-foreground">
                            Password
                        </label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                {...register('password')}
                                id="password"
                                type="password"
                                placeholder="Create a strong password"
                                className={cn(
                                    "pl-10",
                                    errors.password && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="new-password"
                                aria-invalid={errors.password ? 'true' : 'false'}
                                aria-describedby={errors.password ? 'password-error password-strength' : 'password-strength'}
                            />
                        </div>
                        {errors.password && (
                            <p id="password-error" className="text-sm text-red-600" role="alert">
                                {errors.password.message}
                            </p>
                        )}
                        <div id="password-strength" aria-live="polite">
                            <PasswordStrengthIndicator password={watchedPassword} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label htmlFor="password_confirmation" className="text-sm font-medium text-foreground">
                            Confirm Password
                        </label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                {...register('password_confirmation')}
                                id="password_confirmation"
                                type="password"
                                placeholder="Confirm your password"
                                className={cn(
                                    "pl-10",
                                    errors.password_confirmation && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="new-password"
                                aria-invalid={errors.password_confirmation ? 'true' : 'false'}
                                aria-describedby={errors.password_confirmation ? 'password-confirmation-error' : undefined}
                            />
                        </div>
                        {errors.password_confirmation && (
                            <p id="password-confirmation-error" className="text-sm text-red-600" role="alert">
                                {errors.password_confirmation.message}
                            </p>
                        )}
                    </div>

                    <div className="space-y-3">
                        <div className="space-y-1">
                            <label className="flex items-start space-x-3 cursor-pointer group">
                                <input
                                    {...register('terms_accepted')}
                                    id="terms_accepted"
                                    type="checkbox"
                                    className={cn(
                                        "rounded border-input text-primary focus:ring-primary focus:ring-offset-0 mt-1 h-4 w-4 transition-colors",
                                        errors.terms_accepted && "border-red-500"
                                    )}
                                    aria-invalid={errors.terms_accepted ? 'true' : 'false'}
                                    aria-describedby={errors.terms_accepted ? 'terms-error' : undefined}
                                />
                                <span className="text-sm text-muted-foreground leading-relaxed select-none group-hover:text-foreground transition-colors">
                                    I agree to the{' '}
                                    <Link
                                        href="/terms"
                                        className="text-primary hover:text-primary/80 underline focus:outline-none focus:ring-2 focus:ring-primary/20 rounded"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Terms of Service
                                    </Link>{' '}
                                    and{' '}
                                    <Link
                                        href="/privacy"
                                        className="text-primary hover:text-primary/80 underline focus:outline-none focus:ring-2 focus:ring-primary/20 rounded"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Privacy Policy
                                    </Link>
                                </span>
                            </label>
                            {errors.terms_accepted && (
                                <p id="terms-error" className="text-sm text-red-600 ml-7" role="alert">
                                    {errors.terms_accepted.message}
                                </p>
                            )}
                        </div>

                        <label className="flex items-start space-x-3 cursor-pointer group">
                            <input
                                {...register('marketing_consent')}
                                id="marketing_consent"
                                type="checkbox"
                                className="rounded border-input text-primary focus:ring-primary focus:ring-offset-0 mt-1 h-4 w-4 transition-colors"
                            />
                            <span className="text-sm text-muted-foreground leading-relaxed select-none group-hover:text-foreground transition-colors">
                                I'd like to receive marketing emails about new products and promotions
                            </span>
                        </label>
                    </div>

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
                                <span id="loading-text">Creating account...</span>
                            </>
                        ) : (
                            'Create Account'
                        )}
                    </Button>
                </form>
            </CardContent>

            {showSignInLink && (
                <CardFooter className="justify-center pt-4">
                    <p className="text-sm text-muted-foreground">
                        Already have an account?{' '}
                        <Link
                            href="/login"
                            className="text-primary hover:text-primary/80 font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
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