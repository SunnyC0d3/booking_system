import { Metadata } from 'next';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { EmailVerification } from '@/components/auth/EmailVerification';

export const metadata: Metadata = {
    title: 'Verify Email - Authentication',
    description: 'Verify your email address to complete your account setup.',
};

export default function VerifyEmailPage() {
    return (
        <AuthLayout
            title="Email Verification"
            subtitle="Complete your account setup by verifying your email address"
        >
            <EmailVerification />
        </AuthLayout>
    );
}