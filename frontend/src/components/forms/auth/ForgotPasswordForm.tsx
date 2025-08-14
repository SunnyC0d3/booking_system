'use client';

import { useState } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import Link from 'next/link';
import { Mail, ArrowLeft } from 'lucide-react';

export function ForgotPasswordForm() {
    const { forgotPassword, isLoading } = useAuthUtils();
    const [email, setEmail] = useState('');
    const [isSubmitted, setIsSubmitted] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!email.trim()) {
            toast.error('Please enter your email address');
            return;
        }

        if (!email.includes('@')) {
            toast.error('Please enter a valid email address');
            return;
        }

        try {
            await forgotPassword({ email: email.trim() });

            setIsSubmitted(true);
            toast.success('Password reset instructions sent to your email');
        } catch (error: any) {
            console.error('Password reset failed:', error);
        }
    };

    if (isSubmitted) {
        return (
            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <Mail className="h-6 w-6 text-green-600" />
                    </div>
                    <CardTitle>Check Your Email</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-center text-sm text-gray-600">
                        We've sent password reset instructions to{' '}
                        <span className="font-medium">{email}</span>
                    </p>

                    <p className="text-center text-xs text-gray-500">
                        Didn't receive the email? Check your spam folder or try again.
                    </p>

                    <div className="space-y-3">
                        <Button
                            onClick={() => setIsSubmitted(false)}
                            variant="outline"
                            className="w-full"
                        >
                            Try Different Email
                        </Button>

                        <Link href="/login">
                            <Button variant="ghost" className="w-full">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                Back to Sign In
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="w-full max-w-md mx-auto">
            <CardHeader className="text-center">
                <CardTitle>Reset Your Password</CardTitle>
                <p className="text-sm text-gray-600">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="email">Email Address</Label>
                        <Input
                            id="email"
                            type="email"
                            placeholder="Enter your email address"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            disabled={isLoading}
                            autoFocus
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={isLoading}
                        className="w-full"
                    >
                        {isLoading ? 'Sending...' : 'Send Reset Instructions'}
                    </Button>

                    <div className="text-center">
                        <Link
                            href="/login"
                            className="text-sm text-blue-600 hover:text-blue-500 inline-flex items-center"
                        >
                            <ArrowLeft className="w-3 h-3 mr-1" />
                            Back to Sign In
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}