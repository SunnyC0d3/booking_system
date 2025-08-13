import { Suspense } from 'react';
import { AuthHeader } from './AuthHeader';
import { AuthFooter } from './AuthFooter';
import { AuthBranding } from './AuthBranding';
import { AuthFormContainer } from './AuthFormContainer';
import type { AuthLayoutProps } from '@/types/auth';

function AuthBrandingFallback() {
    return (
        <div className="hidden lg:flex flex-col justify-center space-y-8">
            <div className="space-y-6">
                <div className="space-y-4">
                    <div className="h-12 bg-gray-200 rounded animate-pulse" />
                    <div className="h-6 bg-gray-200 rounded animate-pulse w-3/4" />
                </div>
                <div className="space-y-4">
                    <div className="h-6 bg-gray-200 rounded animate-pulse w-1/2" />
                    <div className="grid grid-cols-2 gap-4">
                        {[...Array(6)].map((_, i) => (
                            <div key={i} className="h-12 bg-gray-200 rounded animate-pulse" />
                        ))}
                    </div>
                </div>
            </div>
            <div className="h-32 bg-gray-200 rounded animate-pulse" />
        </div>
    );
}

export function AuthLayout({
                               children,
                               title,
                               subtitle,
                               showBackButton = false,
                               backHref = '/',
                               className,
                           }: AuthLayoutProps) {
    return (
        <div className="min-h-screen bg-gradient-creative flex flex-col">
            <AuthHeader
                showBackButton={showBackButton}
                backHref={backHref}
            />

            <main className="flex-1 flex items-center justify-center p-6">
                <div className="w-full max-w-6xl mx-auto">
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        <Suspense fallback={<AuthBrandingFallback />}>
                            <AuthBranding title={title} subtitle={subtitle} />
                        </Suspense>

                        <AuthFormContainer className={className}>
                            {children}
                        </AuthFormContainer>
                    </div>
                </div>
            </main>

            <AuthFooter />
        </div>
    );
}

export default AuthLayout;