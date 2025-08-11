import * as React from 'react';
import { Metadata } from 'next';
import AuthLayout from '@/components/auth/AuthLayout';
import RegisterForm from '@/components/forms/auth/RegisterForm';

export const metadata: Metadata = {
    title: 'Create Account | Creative Business',
    description: 'Join Creative Business today and access thousands of custom labels, invitations, stickers, and more for your creative projects.',
    robots: 'noindex, nofollow',
};

interface RegisterPageProps {
    searchParams: {
        redirect?: string;
        message?: string;
    };
}

export default function RegisterPage({ searchParams }: RegisterPageProps) {
    const redirectTo = searchParams.redirect || '/dashboard';

    return (
        <AuthLayout
            title="Join Creative Business"
            subtitle="Create your account and unlock access to thousands of professional designs for all your creative projects."
            showBackButton
        >
            <div className="space-y-6">
                {/* Display messages */}
                {searchParams.message && (
                    <div className="alert alert-default">
                        <p className="text-sm">{searchParams.message}</p>
                    </div>
                )}

                <RegisterForm
                    redirectTo={redirectTo}
                    showSignInLink={true}
                />
            </div>
        </AuthLayout>
    );
}