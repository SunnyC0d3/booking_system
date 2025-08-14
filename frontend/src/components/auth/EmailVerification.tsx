'use client';

import {useState, useEffect} from 'react';
import {useSearchParams} from 'next/navigation';
import {useAuthUtils} from '@/hooks/useAuthUtils';
import {Alert, AlertDescription, Button, Card, CardContent} from '@/components/ui';
import {toast} from 'sonner';
import Link from 'next/link';
import {
    Mail,
    CheckCircle,
    XCircle,
    RefreshCw,
    Clock,
    ArrowRight
} from 'lucide-react';

interface EmailVerificationProps {
    showCard?: boolean;
    autoVerify?: boolean;
}

export function EmailVerification({
                                      showCard = true,
                                      autoVerify = true
                                  }: EmailVerificationProps) {
    const searchParams = useSearchParams();
    const {
        user,
        verifyEmail,
        resendVerification,
        isEmailVerified,
        needsEmailVerification,
        isLoading: authLoading
    } = useAuthUtils();

    const [status, setStatus] = useState<'pending' | 'loading' | 'success' | 'error' | 'expired'>('pending');
    const [isResending, setIsResending] = useState(false);
    const [lastSentTime, setLastSentTime] = useState<Date | null>(null);
    const [countdown, setCountdown] = useState(0);
    const token = searchParams.get('token');
    const email = searchParams.get('email');

    useEffect(() => {
        if (countdown > 0) {
            const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [countdown]);

    useEffect(() => {
        if (autoVerify && token && email && status === 'pending' && !authLoading) {
            handleVerification();
        }
    }, [token, email, status, authLoading, autoVerify]);

    useEffect(() => {
        if (isEmailVerified && status === 'pending') {
            setStatus('success');
        }
    }, [isEmailVerified, status]);

    const handleVerification = async () => {
        if (!token || !email) {
            setStatus('error');
            return;
        }

        setStatus('loading');
        try {
            await verifyEmail({token, email});
            setStatus('success');
            toast.success('Email verified successfully!');
        } catch (error: any) {
            if (error.message?.includes('expired') || error.message?.includes('invalid')) {
                setStatus('expired');
            } else {
                setStatus('error');
            }
        }
    };

    const handleResendVerification = async () => {
        if (countdown > 0) {
            toast.warning(`Please wait ${countdown} seconds before requesting another email`);
            return;
        }

        setIsResending(true);
        try {
            await resendVerification();
            setLastSentTime(new Date());
            setCountdown(60); // 60 second cooldown
            toast.success('Verification email sent! Please check your inbox.');
        } catch (error: any) {
            console.error('Resend verification failed:', error);
        } finally {
            setIsResending(false);
        }
    };

    const getStatusIcon = () => {
        switch (status) {
            case 'loading':
                return <RefreshCw className="h-8 w-8 text-blue-600 animate-spin"/>;
            case 'success':
                return <CheckCircle className="h-8 w-8 text-green-600"/>;
            case 'error':
            case 'expired':
                return <XCircle className="h-8 w-8 text-red-600"/>;
            default:
                return <Mail className="h-8 w-8 text-yellow-600"/>;
        }
    };

    const getStatusTitle = () => {
        switch (status) {
            case 'loading':
                return 'Verifying Email...';
            case 'success':
                return 'Email Verified!';
            case 'error':
                return 'Verification Failed';
            case 'expired':
                return 'Link Expired';
            default:
                return 'Verify Your Email';
        }
    };

    const getStatusMessage = () => {
        switch (status) {
            case 'loading':
                return 'Please wait while we verify your email address...';
            case 'success':
                return 'Your email address has been successfully verified. You now have full access to your account.';
            case 'error':
                return 'We encountered an error while verifying your email. Please try again or request a new verification email.';
            case 'expired':
                return 'This verification link has expired. Please request a new verification email to continue.';
            default:
                return user?.email
                    ? `We've sent a verification link to ${user.email}. Please check your email and click the link to verify your account.`
                    : 'Please verify your email address to complete your account setup.';
        }
    };

    const renderActions = () => {
        switch (status) {
            case 'success':
                return (
                    <div className="space-y-3">
                        <Link href="/dashboard">
                            <Button className="w-full">
                                Go to Dashboard
                                <ArrowRight className="w-4 h-4 ml-2"/>
                            </Button>
                        </Link>
                    </div>
                );

            case 'error':
            case 'expired':
            case 'pending':
                return (
                    <div className="space-y-3">
                        <Button
                            onClick={handleResendVerification}
                            disabled={isResending || countdown > 0}
                            className="w-full"
                            variant={status === 'pending' ? 'outline' : 'default'}
                        >
                            {isResending ? (
                                <>
                                    <RefreshCw className="w-4 h-4 mr-2 animate-spin"/>
                                    Sending...
                                </>
                            ) : countdown > 0 ? (
                                <>
                                    <Clock className="w-4 h-4 mr-2"/>
                                    Resend in {countdown}s
                                </>
                            ) : (
                                'Send New Verification Email'
                            )}
                        </Button>

                        {status !== 'pending' && (
                            <Link href="/dashboard">
                                <Button variant="outline" className="w-full">
                                    Continue to Dashboard
                                </Button>
                            </Link>
                        )}
                    </div>
                );

            case 'loading':
                return (
                    <div className="text-center">
                        <div className="animate-pulse text-sm text-gray-600">
                            This may take a few seconds...
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    if (!needsEmailVerification && !token) {
        return null;
    }

    const content = (
        <div className="space-y-6">
            <div className="text-center">
                <div className="flex justify-center mb-4">
                    <div className="p-3 rounded-full bg-gray-100">
                        {getStatusIcon()}
                    </div>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-2">
                    {getStatusTitle()}
                </h2>
                <p className="text-gray-600 leading-relaxed">
                    {getStatusMessage()}
                </p>
            </div>

            {lastSentTime && (
                <Alert>
                    <CheckCircle className="h-4 w-4"/>
                    <AlertDescription>
                        Verification email sent at {lastSentTime.toLocaleTimeString()}.
                        Please check your inbox and spam folder.
                    </AlertDescription>
                </Alert>
            )}

            {(status === 'pending' || status === 'error' || status === 'expired') && (
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-gray-900 mb-2">Didn't receive the email?</h4>
                    <ul className="text-sm text-gray-600 space-y-1">
                        <li>• Check your spam or junk folder</li>
                        <li>• Make sure {user?.email} is correct</li>
                        <li>• Try adding our domain to your email whitelist</li>
                        <li>• Wait a few minutes and check again</li>
                    </ul>
                </div>
            )}

            {renderActions()}

            {(status === 'error' || status === 'expired') && (
                <div className="text-center">
                    <p className="text-xs text-gray-500">
                        Still having issues?{' '}
                        <Link href="/contact" className="text-blue-600 hover:text-blue-500">
                            Contact support
                        </Link>
                    </p>
                </div>
            )}
        </div>
    );

    if (!showCard) {
        return content;
    }

    return (
        <div className="min-h-screen flex items-center justify-center p-4">
            <Card className="w-full max-w-md">
                <CardContent className="p-6">
                    {content}
                </CardContent>
            </Card>
        </div>
    );
}