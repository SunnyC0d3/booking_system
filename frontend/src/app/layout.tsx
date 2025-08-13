import * as React from 'react';
import type {Metadata} from 'next';
import {Inter} from 'next/font/google';
import {Toaster} from 'sonner';
import {ThemeProvider} from 'next-themes';
import {ErrorBoundary} from '@/components/error/ErrorBoundary';
import {QueryProvider} from '@/components/providers/QueryProvider';
import {ClientTokenProvider, useClientToken} from '@/components/providers/ClientTokenProvider';
import '@/app/globals.css';

const inter = Inter({
    subsets: ['latin'],
    variable: '--font-inter',
    display: 'swap',
});

export const metadata: Metadata = {
    title: {
        default: 'Creative Business | Professional Labels, Invitations & Stickers',
        template: '%s | Creative Business',
    },
    description: 'Professional custom labels, gift tags, invitations, packaging inserts, greeting cards, stickers, and flower stand services for your creative projects.',
    keywords: ['labels', 'invitations', 'stickers', 'gift tags', 'packaging', 'greeting cards', 'flower stands', 'custom printing'],
    authors: [{name: 'Creative Business'}],
    creator: 'Creative Business',
    publisher: 'Creative Business',
    formatDetection: {
        email: false,
        address: false,
        telephone: false,
    },
    metadataBase: new URL(process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000'),
    alternates: {
        canonical: '/',
    },
    robots: {
        index: true,
        follow: true,
        googleBot: {
            index: true,
            follow: true,
            'max-video-preview': -1,
            'max-image-preview': 'large',
            'max-snippet': -1,
        },
    },
    openGraph: {
        type: 'website',
        locale: 'en_US',
        url: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
        siteName: 'Creative Business',
        title: 'Creative Business | Professional Labels, Invitations & Stickers',
        description: 'Professional custom labels, gift tags, invitations, packaging inserts, greeting cards, stickers, and flower stand services.',
        images: [
            {
                url: '/og-image.jpg',
                width: 1200,
                height: 630,
                alt: 'Creative Business - Professional printing services',
            },
        ],
    },
    twitter: {
        card: 'summary_large_image',
        title: 'Creative Business | Professional Labels, Invitations & Stickers',
        description: 'Professional custom labels, gift tags, invitations, packaging inserts, greeting cards, stickers, and flower stand services.',
        images: ['/og-image.jpg'],
        creator: '@creativebusiness',
    },
    icons: {
        icon: '/favicon.ico',
        shortcut: '/favicon-16x16.png',
        apple: '/apple-touch-icon.png',
        other: {
            rel: 'apple-touch-icon-precomposed',
            url: '/apple-touch-icon-precomposed.png',
        },
    },
    manifest: '/site.webmanifest',
};

const ClientTokenLoader: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const { isInitialized, isError, retry } = useClientToken();

    if (isError) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-background">
                <div className="text-center space-y-4">
                    <div className="text-destructive">
                        <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h2 className="text-lg font-semibold text-foreground">Connection Error</h2>
                    <p className="text-muted-foreground">Unable to initialize application</p>
                    <button
                        onClick={retry}
                        className="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    if (!isInitialized) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-background">
                <div className="text-center space-y-4">
                    <div className="inline-flex items-center justify-center w-16 h-16 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                    <p className="text-muted-foreground">Initializing application...</p>
                </div>
            </div>
        );
    }

    return <>{children}</>;
};

export default function RootLayout({children,}: { children: React.ReactNode; }) {
    return (
        <html lang="en" suppressHydrationWarning>
        <body className={`${inter.variable} font-sans antialiased`}>
        <ErrorBoundary>
            <ThemeProvider
                attribute="class"
                defaultTheme="light"
                enableSystem
                disableTransitionOnChange
            >
                <ClientTokenProvider>
                    <QueryProvider>
                        <ClientTokenLoader>
                            <div className="relative min-h-screen bg-background">
                                {children}
                            </div>
                        </ClientTokenLoader>
                        <Toaster
                            position="top-right"
                            expand={true}
                            richColors
                            closeButton
                            toastOptions={{
                                duration: 4000,
                                style: {
                                    background: 'hsl(var(--background))',
                                    border: '1px solid hsl(var(--border))',
                                    color: 'hsl(var(--foreground))',
                                },
                            }}
                        />
                    </QueryProvider>
                </ClientTokenProvider>
            </ThemeProvider>
        </ErrorBoundary>
        </body>
        </html>
    );
}