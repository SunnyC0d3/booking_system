'use client';

import { useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import { toast } from 'sonner';
import Link from 'next/link';
import { Mail, CheckCircle, XCircle, RefreshCw } from 'lucide-react';

export default function VerifyEmailPage() {
    const searchParams = useSearchParams();
    const {
        verifyEmail,
        resendVerification,
        user,
        isEmailVerified,
        requireAuth,
        isLoading
    } = useAuthUtils();

    const [status, setStatus] = useState<'loading' | 'success' | 'error' | 'pending'>('pending');
    const [isResending, setIsResending] = useState(false);

    const token = searchParams.get('token');
    const email = searchParams.get('email');

    useEffect(() => {
        if (!requireAuth()) return;
    }, [requireAuth]);

    useEffect(() => {
        if (token && email && status === 'pending') {
            handleVerification();
        }
    }, [token, email, status]);

    const handleVerification = async () => {
        if (!token || !email) return;

        setStatus('loading');
        try {
            await verifyEmail({ token, email });
            setStatus('success');
            toast.success('Email verified successfully!');
        } catch (error: any) {
            setStatus('error');
            console.error('Email verification failed:', error);
        }
    };

    const handleResendVerification = async () => {
        setIsResending(true);
        try {
            await resendVerification();
            toast.success('Verification email sent!');
        } catch (error: any) {
            console.error('Resend verification failed:', error);
        } finally {
            setIsResending(false);
        }
    };

    // Show loading state
    if (isLoading || !user) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Card className="w-full max-w-md">
                    <CardContent className="p-6">
                        <div className="animate-pulse space-y-4">
                            <div className="h-12 w-12 bg-gray-200 rounded-full mx-auto"></div>
                            <div className="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
                            <div className="h-4 bg-gray-200 rounded w-1/2 mx-auto"></div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    // Already verified
    if (isEmailVerified) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <CheckCircle className="h-6 w-6 text-green-600" />
                        </div>
                        <CardTitle>Email Already Verified</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-center text-sm text-gray-600">
                            Your email address <span className="font-medium">{user.email}</span> is already verified.
                        </p>

                        <Link href="/dashboard">
                            <Button className="w-full">
                                Go to Dashboard
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (status === 'success') {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <CheckCircle className="h-6 w-6 text-green-600" />
                        </div>
                        <CardTitle>Email Verified!</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-center text-sm text-gray-600">
                            Your email address has been successfully verified. You now have full access to your account.
                        </p>

                        <Link href="/dashboard">
                            <Button className="w-full">
                                Go to Dashboard
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                            <XCircle className="h-6 w-6 text-red-600" />
                        </div>
                        <CardTitle>Verification Failed</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-center text-sm text-gray-600">
                            The verification link is invalid or has expired. Please request a new verification email.
                        </p>

                        <Button
                            onClick={handleResendVerification}
                            disabled={isResending}
                            className="w-full"
                        >
                            {isResending ? (
                                <>
                                    <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                                    Sending...
                                </>
                            ) : (
                                'Send New Verification Email'
                            )}
                        </Button>

                        <Link href="/dashboard">
                            <Button variant="outline" className="w-full">
                                Go to Dashboard
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (status === 'loading') {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                            <RefreshCw className="h-6 w-6 text-blue-600 animate-spin" />
                        </div>
                        <CardTitle>Verifying Email...</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-center text-sm text-gray-600">
                            Please wait while we verify your email address.
                        </p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center">
            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100">
                        <Mail className="h-6 w-6 text-yellow-600" />
                    </div>
                    <CardTitle>Verify Your Email</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-center text-sm text-gray-600">
                        Please check your email <span className="font-medium">{user.email}</span> and click the verification link to activate your account.
                    </p>

                    <p className="text-center text-xs text-gray-500">
                        Didn't receive the email? Check your spam folder or request a new one.
                    </p>

                    <Button
                        onClick={handleResendVerification}
                        disabled={isResending}
                        variant="outline"
                        className="w-full"
                    >
                        {isResending ? (
                            <>
                                <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                                Sending...
                            </>
                        ) : (
                            'Send New Verification Email'
                        )}
                    </Button>

                    <Link href="/dashboard">
                        <Button variant="ghost" className="w-full">
                            Continue to Dashboard
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    );
}