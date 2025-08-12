'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { Mail, ArrowLeft, CheckCircle, Loader2, Clock, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { ForgotPasswordFormData } from '@/types/auth';
import { cn } from '@/lib/cn';

const forgotPasswordSchema = z.object({
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address')
        .max(100, 'Email must be less than 100 characters'),
});

interface ForgotPasswordFormProps {
    onSuccess?: () => void;
    className?: string;
}

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

const SuccessState = React.memo(({
                                     email,
                                     onResend,
                                     isLoading,
                                     resendCount
                                 }: {
    email: string;
    onResend: () => void;
    isLoading: boolean;
    resendCount: number;
}) => {
    const [timeLeft, setTimeLeft] = React.useState(60);
    const canResend = timeLeft === 0;

    React.useEffect(() => {
        if (timeLeft > 0) {
            const timer = setTimeout(() => setTimeLeft(prev => prev - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [timeLeft]);

    const handleResend = () => {
        onResend();
        setTimeLeft(60);
    };

    return (
        <Card className="w-full max-w-md mx-auto shadow-lg">
            <CardHeader className="text-center space-y-4">
                <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto">
                    <CheckCircle className="h-8 w-8 text-green-600 dark:text-green-400" />
                </div>
                <div className="space-y-2">
                    <CardTitle className="text-2xl font-bold text-foreground">
                        Check Your Email
                    </CardTitle>
                    <p className="text-muted-foreground text-sm">
                        We've sent password reset instructions to:
                    </p>
                    <p className="text-sm font-medium text-foreground break-all">
                        {email}
                    </p>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800">
                    <div className="flex items-start gap-3">
                        <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                        <div className="space-y-2 text-sm">
                            <p className="text-blue-800 dark:text-blue-200 font-medium">
                                Important Security Information
                            </p>
                            <ul className="text-blue-700 dark:text-blue-300 space-y-1 text-xs">
                                <li>• Check your spam/junk folder if you don't see the email</li>
                                <li>• The reset link expires in 60 minutes for security</li>
                                <li>• Only the most recent reset link will work</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-center space-y-3">
                    <Button
                        variant="outline"
                        onClick={handleResend}
                        disabled={!canResend || isLoading}
                        className="w-full"
                        aria-describedby={!canResend ? 'resend-timer' : undefined}
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Sending...
                            </>
                        ) : (
                            <>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                {canResend ? 'Resend Email' : `Resend in ${timeLeft}s`}
                            </>
                        )}
                    </Button>

                    {resendCount > 0 && (
                        <p className="text-xs text-muted-foreground text-center">
                            Email sent {resendCount + 1} time{resendCount > 0 ? 's' : ''}
                        </p>
                    )}

                    {!canResend && (
                        <p id="resend-timer" className="text-xs text-muted-foreground text-center">
                            Please wait before requesting another email
                        </p>
                    )}
                </div>
            </CardContent>

            <CardFooter className="justify-center pt-4">
                <Link
                    href="/login"
                    className="inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-2 py-1"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to sign in
                </Link>
            </CardFooter>
        </Card>
    );
});

SuccessState.displayName = 'SuccessState';

export const ForgotPasswordForm: React.FC<ForgotPasswordFormProps> = ({
                                                                          onSuccess,
                                                                          className,
                                                                      }) => {
    const router = useRouter();
    const searchParams = useSearchParams();
    const { forgotPassword, isLoading, error, clearError } = useAuth();
    const [isSuccess, setIsSuccess] = React.useState(false);
    const [submittedEmail, setSubmittedEmail] = React.useState('');
    const [resendCount, setResendCount] = React.useState(0);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        getValues,
        clearErrors,
        setValue,
    } = useForm<ForgotPasswordFormData>({
        resolver: zodResolver(forgotPasswordSchema),
        defaultValues: {
            email: '',
        },
        mode: 'onTouched',
    });

    const emailValue = watch('email');

    React.useEffect(() => {
        const emailParam = searchParams.get('email');
        if (emailParam) {
            setValue('email', emailParam);
        }
    }, [searchParams, setValue]);

    const clearErrorsOnChange = React.useCallback(() => {
        if (error) {
            clearError();
        }
        if (errors.email) {
            clearErrors(['email']);
        }
    }, [error, errors.email, clearError, clearErrors]);

    React.useEffect(() => {
        clearErrorsOnChange();
    }, [emailValue, clearErrorsOnChange]);

    const onSubmit = React.useCallback(
        async (data: ForgotPasswordFormData) => {
            try {
                clearError();

                await forgotPassword({
                    email: data.email,
                });

                setSubmittedEmail(data.email);
                setIsSuccess(true);
                setResendCount(0);

                if (onSuccess) {
                    onSuccess();
                }

                toast.success('Password reset email sent successfully');

            } catch (error: any) {
                toast.error(error.message || 'Failed to send reset email');
            }
        },
        [forgotPassword, clearError, onSuccess]
    );

    const handleResend = React.useCallback(async () => {
        const email = getValues('email') || submittedEmail;
        if (!email) return;

        try {
            clearError();

            await forgotPassword({ email });
            setResendCount(prev => prev + 1);

            toast.success('Reset email sent again');
        } catch (error: any) {
            toast.error(error.message || 'Failed to resend email');
        }
    }, [forgotPassword, clearError, getValues, submittedEmail]);

    const handleBackToLogin = React.useCallback(() => {
        const currentEmail = getValues('email');
        if (currentEmail) {
            router.push(`/login?email=${encodeURIComponent(currentEmail)}`);
        } else {
            router.push('/login');
        }
    }, [router, getValues]);

    const isFormLoading = isLoading || isSubmitting;

    if (isSuccess) {
        return (
            <SuccessState
                email={submittedEmail}
                onResend={handleResend}
                isLoading={isLoading}
                resendCount={resendCount}
            />
        );
    }

    return (
        <Card className={cn("w-full max-w-md mx-auto shadow-lg", className)}>
            <CardHeader className="text-center space-y-2">
                <CardTitle className="text-2xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                    Forgot Password?
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    Enter your email address and we'll send you a secure link to reset your password.
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
                    {error && <ErrorAlert message={error} />}

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
                                placeholder="Enter your email address"
                                className={cn(
                                    "pl-10",
                                    errors.email && "border-red-500 focus:ring-red-500"
                                )}
                                autoComplete="email"
                                autoFocus
                                aria-invalid={errors.email ? 'true' : 'false'}
                                aria-describedby={errors.email ? 'email-error' : 'email-help'}
                            />
                        </div>
                        {errors.email && (
                            <p id="email-error" className="text-sm text-red-600" role="alert">
                                {errors.email.message}
                            </p>
                        )}
                        <p id="email-help" className="text-xs text-muted-foreground">
                            We'll send reset instructions to this email address
                        </p>
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
                                <span id="loading-text">Sending reset link...</span>
                            </>
                        ) : (
                            'Send Reset Link'
                        )}
                    </Button>
                </form>
            </CardContent>

            <CardFooter className="justify-center pt-4">
                <button
                    onClick={handleBackToLogin}
                    className="inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-2 py-1"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to sign in
                </button>
            </CardFooter>
        </Card>
    );
};

export default ForgotPasswordForm;