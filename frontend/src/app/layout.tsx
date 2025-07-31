import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { Toaster } from 'sonner';
import { ThemeProvider } from 'next-themes';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import '@/styles/global.css';

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
    authors: [{ name: 'Creative Business' }],
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

// Create query client
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 60 * 1000, // 1 minute
            gcTime: 10 * 60 * 1000, // 10 minutes
            retry: (failureCount, error: any) => {
                // Don't retry on 4xx errors
                if (error?.status >= 400 && error?.status < 500) {
                    return false;
                }
                return failureCount < 3;
            },
        },
    },
});

export default function RootLayout({
                                       children,
                                   }: {
    children: React.ReactNode;
}) {
    return (
        <html lang="en" suppressHydrationWarning>
        <body className={`${inter.variable} font-sans antialiased`}>
        <ThemeProvider
            attribute="class"
            defaultTheme="light"
            enableSystem
            disableTransitionOnChange
        >
            <QueryClientProvider client={queryClient}>
                {/* Main App Content */}
                <div className="relative min-h-screen bg-background">
                    {children}
                </div>

                {/* Toast Notifications */}
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

                {/* React Query DevTools (only in development) */}
                {process.env.NODE_ENV === 'development' && (
                    <ReactQueryDevtools initialIsOpen={false} />
                )}
            </QueryClientProvider>
        </ThemeProvider>
        </body>
        </html>
    );
}