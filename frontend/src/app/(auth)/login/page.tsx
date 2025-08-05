import * as React from 'react';
import { Metadata } from 'next';
import AuthLayout from '@/components/layout/AuthLayout';
import LoginForm from '@/components/forms/auth/LoginForm';

export const metadata: Metadata = {
    title: 'Sign In | Creative Business',
    description: 'Sign in to your Creative Business account to manage your orders and access exclusive designs.',
    robots: 'noindex, nofollow',
};

interface LoginPageProps {
    searchParams: {
        redirect?: string;
        message?: string;
        error?: string;
    };
}

export default function LoginPage({ searchParams }: LoginPageProps) {
    const redirectTo = searchParams.redirect || '/dashboard';

    return (
        <AuthLayout
            title="Welcome Back"
            subtitle="Sign in to access your creative projects and manage your orders with ease."
            showBackButton
        >
            <div className="space-y-6">
                {/* Display messages */}
                {searchParams.message && (
                    <div className="alert alert-default">
                        <p className="text-sm">{searchParams.message}</p>
                    </div>
                )}

                {searchParams.error && (
                    <div className="alert alert-error">
                        <p className="text-sm">{searchParams.error}</p>
                    </div>
                )}

                <LoginForm
                    redirectTo={redirectTo}
                    showSignUpLink={true}
                />
            </div>
        </AuthLayout>
    );
}