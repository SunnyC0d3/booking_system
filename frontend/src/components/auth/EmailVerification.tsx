'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { Mail, CheckCircle, AlertCircle, RefreshCw, Clock } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/stores/authStore';
import { toast } from 'sonner';
import { cn } from '@/lib/cn';

interface EmailVerificationProps {
    className?: string;
    showCard?: boolean;
    redirectTo?: string;
}

export const EmailVerification: React.FC<EmailVerificationProps> = ({
                                                                        className,
                                                                        showCard = true,
                                                                        redirectTo = '/dashboard'
                                                                    }) => {
    const router = useRouter();
    const { user, resendVerification, isLoading } = useAuth();
    const [isResending, setIsResending] = React.useState(false);
    const [countdown, setCountdown] = React.useState(0);
    const [lastSentAt, setLastSentAt] = React.useState<number | null>(null);

    // Countdown timer for resend button
    React.useEffect(() => {
        if (countdown > 0) {
            const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
            return () => clearTimeout(timer);
        }
        return undefined;
    }, [countdown]);

    // Start countdown when component mounts if recently sent
    React.useEffect(() => {
        const lastSent = localStorage.getItem('verification_email_sent_at');
        if (lastSent) {
            const sentTime = parseInt(lastSent);
            const timeSince = Date.now() - sentTime;
            const remainingTime = Math.max(0, 60 - Math.floor(timeSince / 1000));

            if (remainingTime > 0) {
                setCountdown(remainingTime);
                setLastSentAt(sentTime);
            }
        }
    }, []);

    const handleResendEmail = async () => {
        if (countdown > 0) return;

        setIsResending(true);
        try {
            await resendVerification();

            const now = Date.now();
            setLastSentAt(now);
            setCountdown(60); // 60 second cooldown
            localStorage.setItem('verification_email_sent_at', now.toString());

            toast.success('Verification email sent! Check your inbox.');
        } catch (error: any) {
            toast.error(error.message || 'Failed to send verification email');
        } finally {
            setIsResending(false);
        }
    };

    const handleGoToDashboard = () => {
        router.push(redirectTo);
    };

    // If user is already verified, show success state
    if (user?.email_verified_at) {
        const content = (
            <div className="text-center space-y-4">
                <div className="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mx-auto">
                    <CheckCircle className="h-8 w-8 text-green-600" />
                </div>
                <div>
                    <h3 className="text-lg font-semibold text-green-600">Email Verified!</h3>
                    <p className="text-muted-foreground">
                        Your email address has been successfully verified.
                    </p>
                </div>
                <Button onClick={handleGoToDashboard} className="w-full">
                    Continue to Dashboard
                </Button>
            </div>
        );

        if (showCard) {
            return (
                <Card className={className}>
                    <CardContent className="pt-6">
                        {content}
                    </CardContent>
                </Card>
            );
        }

        return <div className={className}>{content}</div>;
    }

    const content = (
        <div className="space-y-6">
            {/* Header */}
            <div className="text-center space-y-2">
                <div className="w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center mx-auto">
                    <Mail className="h-8 w-8 text-blue-600" />
                </div>
                <div>
                    <h3 className="text-lg font-semibold">Verify Your Email</h3>
                    <p className="text-muted-foreground">
                        We've sent a verification link to <strong>{user?.email}</strong>
                    </p>
                </div>
            </div>

            {/* Instructions */}
            <Alert>
                <Mail className="h-4 w-4" />
                <AlertDescription>
                    <strong>Check your email inbox</strong> and click the verification link to activate your account.
                    Don't forget to check your spam folder if you don't see it within a few minutes.
                </AlertDescription>
            </Alert>

            {/* Status badges */}
            <div className="flex justify-center gap-2">
                <Badge variant="outline" className="gap-1">
                    <AlertCircle className="h-3 w-3" />
                    Pending Verification
                </Badge>
                {lastSentAt && (
                    <Badge variant="secondary" className="gap-1">
                        <Clock className="h-3 w-3" />
                        Sent {Math.floor((Date.now() - lastSentAt) / 1000 / 60)}m ago
                    </Badge>
                )}
            </div>

            {/* Resend button */}
            <div className="space-y-3">
                <Button
                    onClick={handleResendEmail}
                    disabled={isResending || countdown > 0 || isLoading}
                    variant="outline"
                    className="w-full gap-2"
                >
                    {isResending ? (
                        <>
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Sending...
                        </>
                    ) : countdown > 0 ? (
                        <>
                            <Clock className="h-4 w-4" />
                            Resend in {countdown}s
                        </>
                    ) : (
                        <>
                            <RefreshCw className="h-4 w-4" />
                            Resend Email
                        </>
                    )}
                </Button>

                <div className="text-center">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleGoToDashboard}
                        className="text-muted-foreground"
                    >
                        Skip for now
                    </Button>
                </div>
            </div>

            {/* Additional help */}
            <div className="text-center space-y-2">
                <p className="text-xs text-muted-foreground">
                    Not receiving emails? Check your spam folder or contact support.
                </p>
                <div className="text-xs text-muted-foreground">
                    Wrong email? You can update it in your
                    <Button variant="link" size="sm" className="px-1 h-auto text-xs">
                        account settings
                    </Button>
                </div>
            </div>
        </div>
    );

    if (showCard) {
        return (
            <Card className={cn('w-full max-w-md mx-auto', className)}>
                <CardHeader className="text-center">
                    <CardTitle>Email Verification Required</CardTitle>
                    <CardDescription>
                        Please verify your email address to continue
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {content}
                </CardContent>
            </Card>
        );
    }

    return <div className={className}>{content}</div>;
};