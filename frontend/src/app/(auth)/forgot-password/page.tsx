'use client'

import * as React from 'react';
import { Metadata } from 'next';
import AuthLayout from '@/components/layout/AuthLayout';
import ForgotPasswordForm from '@/components/forms/auth/ForgotPasswordForm';

export const metadata: Metadata = {
    title: 'Reset Password | Creative Business',
    description: 'Reset your Creative Business account password. Enter your email to receive reset instructions.',
    robots: 'noindex, nofollow',
};

export default function ForgotPasswordPage() {
    return (
        <AuthLayout
            title="Password Reset"
            subtitle="Don't worry, it happens to the best of us. Enter your email and we'll help you get back to creating."
            showBackButton
            backHref="/login"
        >
            <ForgotPasswordForm />
        </AuthLayout>
    );
}