import * as React from 'react';
import { Metadata } from 'next';
import { useRouter, useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Mail, CheckCircle, AlertCircle, RefreshCw } from 'lucide-react';
import { Button, Card, CardContent, CardHeader, CardTitle, Input } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { useAuth } from '@/stores/authStore';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

export const metadata: Metadata = {
    title: 'Verify Email | Creative Business',
    description: 'Verify your email address to complete your account setup.',
};

const verifyEmailSchema = z.object({
    code: z.string().min(6, 'Verification code must be 6 digits').max(6),
});

type VerifyEmailForm = z.infer<typeof verifyEmailSchema>;

function VerifyEmailPage() {
    const router = useRouter();
    const searchParams = useSearchParams();
    const { user, verifyEmail, resendVerificationEmail, isLoading } = useAuth();
    const [isResending, setIsResending] = React.useState(false);
    const [countdown, setCountdown] = React.useState(0);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        setValue,
        watch,
    } = useForm<VerifyEmailForm>({
        resolver: zodResolver(verifyEmailSchema),
    });

    const code = watch('code');

    // Auto-verify if code is in URL
    React.useEffect(() => {
        const urlCode = searchParams.get('code');
        if (urlCode && urlCode.length === 6) {
            setValue('code', urlCode);
            handleSubmit(onSubmit)();
        }
    }, [searchParams, setValue, handleSubmit]);

    // Countdown timer for resend button
    React.useEffect(() => {
        if (countdown > 0) {
            const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [countdown]);

    const onSubmit = async (data: VerifyEmailForm) => {
        try {
            await verifyEmail({ code: data.code });
            toast.success('Email verified successfully!');
            router.push('/dashboard');
        } catch (error: any) {
            toast.error(error.message || 'Failed to verify email');
        }
    };

    const handleResendEmail = async () => {
        if (countdown > 0) return;

        setIsResending(true);
        try {
            await resendVerificationEmail();
            toast.success('Verification email sent!');
            setCountdown(60); // 1 minute cooldown
        } catch (error: any) {
            toast.error(error.message || 'Failed to resend email');
        } finally {
            setIsResending(false);
        }
    };

    // Auto-submit when code is complete
    React.useEffect(() => {
        if (code && code.length === 6 && !isSubmitting) {
            handleSubmit(onSubmit)();
        }
    }, [code, isSubmitting, handleSubmit]);

    return (
        <AuthLayout
            title="Verify Your Email"
            description="We've sent a verification code to your email address"
        >
            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center">
                    <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        transition={{ duration: 0.5 }}
                        className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10"
                    >
                        <Mail className="h-8 w-8 text-primary" />
                    </motion.div>
                    <CardTitle>Check Your Email</CardTitle>
                    <p className="text-sm text-muted-foreground">
                        We've sent a 6-digit verification code to{' '}
                        <strong>{user?.email}</strong>
                    </p>
                </CardHeader>

                <CardContent className="space-y-6">
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Verification Code
                            </label>
                            <Input
                                {...register('code')}
                                placeholder="Enter 6-digit code"
                                className={cn(
                                    "text-center text-lg tracking-widest font-mono",
                                    errors.code && "border-destructive"
                                )}
                                maxLength={6}
                                autoComplete="one-time-code"
                                autoFocus
                            />
                            {errors.code && (
                                <p className="text-sm text-destructive">
                                    {errors.code.message}
                                </p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={isSubmitting || !code || code.length !== 6}
                        >
                            {isSubmitting ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Verifying...
                                </>
                            ) : (
                                'Verify Email'
                            )}
                        </Button>
                    </form>

                    <div className="text-center space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Didn't receive the code?
                        </p>
                        <Button
                            variant="outline"
                            onClick={handleResendEmail}
                            disabled={countdown > 0 || isResending}
                            className="w-full"
                        >
                            {isResending ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Sending...
                                </>
                            ) : countdown > 0 ? (
                                `Resend in ${countdown}s`
                            ) : (
                                'Resend Email'
                            )}
                        </Button>
                    </div>

                    <div className="text-center">
                        <Button
                            variant="ghost"
                            onClick={() => router.push('/login')}
                            className="text-sm"
                        >
                            Back to Sign In
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}

export default VerifyEmailPage;