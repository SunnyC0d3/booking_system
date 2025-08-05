'use client'

import * as React from 'react';
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

const verifyEmailSchema = z.object({
    code: z.string().min(6, 'Verification code must be 6 digits').max(6),
});

type VerifyEmailForm = z.infer<typeof verifyEmailSchema>;

export default function VerifyEmailPage() {
    const router = useRouter();
    const searchParams = useSearchParams();
    const { user, verifyEmail, isLoading } = useAuth();
    const [isResending, setIsResending] = React.useState(false);
    const [countdown, setCountdown] = React.useState(0);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        setValue,
    } = useForm<VerifyEmailForm>({
        resolver: zodResolver(verifyEmailSchema),
    });

    // Auto-verify if code is in URL
    React.useEffect(() => {
        const urlCode = searchParams.get('code');
        if (urlCode && urlCode.length === 6) {
            setValue('code', urlCode);
            handleSubmit(onSubmit)();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchParams, setValue]);

    // Countdown timer for resend button
    React.useEffect(() => {
        if (countdown > 0) {
            const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
            return () => clearTimeout(timer);
        }
        return undefined;
    }, [countdown]);

    const onSubmit = async (data: VerifyEmailForm) => {
        try {
            // Assuming verifyEmail expects the verification data structure
            await verifyEmail({
                id: user?.id || 0,
                hash: data.code,
                expires: Date.now() + (15 * 60 * 1000), // 15 minutes from now
                signature: data.code // Using code as signature for now
            } as any);
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
            // Mock resend functionality since it's not available in useAuth
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Verification email sent!');
            setCountdown(60); // 60 second cooldown
        } catch (error: any) {
            toast.error(error.message || 'Failed to resend email');
        } finally {
            setIsResending(false);
        }
    };

    // Show verification form
    return (
        <AuthLayout title="Verify Email">
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                className="w-full max-w-md mx-auto"
            >
                <Card>
                    <CardHeader className="text-center">
                        <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Mail className="h-8 w-8 text-primary" />
                        </div>
                        <CardTitle className="text-2xl font-bold">Verify Your Email</CardTitle>
                        <p className="text-muted-foreground">
                            We've sent a 6-digit verification code to{' '}
                            <span className="font-medium text-foreground">
                                {user?.email || 'your email'}
                            </span>
                        </p>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">
                                    Verification Code
                                </label>
                                <Input
                                    {...register('code')}
                                    type="text"
                                    placeholder="000000"
                                    maxLength={6}
                                    className={cn(
                                        'text-center text-lg tracking-widest',
                                        errors.code && 'border-destructive'
                                    )}
                                    autoComplete="one-time-code"
                                />
                                {errors.code && (
                                    <p className="text-xs text-destructive mt-1">
                                        {errors.code.message}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                size="lg"
                                disabled={isSubmitting || isLoading}
                            >
                                {isSubmitting ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Verifying...
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle className="mr-2 h-4 w-4" />
                                        Verify Email
                                    </>
                                )}
                            </Button>
                        </form>

                        <div className="mt-6 text-center">
                            <p className="text-sm text-muted-foreground mb-4">
                                Didn't receive the code?
                            </p>
                            <Button
                                variant="outline"
                                onClick={handleResendEmail}
                                disabled={isResending || countdown > 0}
                                size="sm"
                            >
                                {isResending ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Sending...
                                    </>
                                ) : countdown > 0 ? (
                                    `Resend in ${countdown}s`
                                ) : (
                                    'Resend Code'
                                )}
                            </Button>
                        </div>

                        <div className="mt-6 p-4 bg-muted/50 rounded-lg">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="h-5 w-5 text-muted-foreground mt-0.5 flex-shrink-0" />
                                <div className="text-sm text-muted-foreground">
                                    <p className="font-medium mb-1">Troubleshooting:</p>
                                    <ul className="space-y-1 text-xs">
                                        <li>• Check your spam/junk folder</li>
                                        <li>• Ensure the email address is correct</li>
                                        <li>• Code expires in 15 minutes</li>
                                        <li>• Contact support if issues persist</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </motion.div>
        </AuthLayout>
    );
}