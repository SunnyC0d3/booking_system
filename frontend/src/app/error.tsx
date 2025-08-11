'use client'

import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    AlertTriangle,
    RefreshCw,
    Home,
    ArrowLeft,
    Bug,
    Mail,
    Phone,
    Copy,
    CheckCircle,
} from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';
import { MainLayout } from '@/components/layout/MainLayout';

interface ErrorPageProps {
    error: Error & { digest?: string };
    reset: () => void;
}

// Error type detection
const getErrorType = (error: Error) => {
    const message = error.message.toLowerCase();

    if (message.includes('network') || message.includes('fetch')) {
        return 'network';
    }
    if (message.includes('timeout')) {
        return 'timeout';
    }
    if (message.includes('auth') || message.includes('unauthorized')) {
        return 'auth';
    }
    if (message.includes('not found') || message.includes('404')) {
        return 'notFound';
    }

    return 'generic';
};

// Error type configurations
const errorConfigs = {
    network: {
        title: 'Connection Problem',
        description: 'We\'re having trouble connecting to our servers. Please check your internet connection and try again.',
        icon: '🌐',
        suggestions: [
            'Check your internet connection',
            'Try refreshing the page',
            'Disable any VPN or proxy',
            'Clear your browser cache'
        ]
    },
    timeout: {
        title: 'Request Timeout',
        description: 'The request took too long to complete. Our servers might be experiencing high traffic.',
        icon: '⏰',
        suggestions: [
            'Wait a moment and try again',
            'Check your connection speed',
            'Try accessing during off-peak hours'
        ]
    },
    auth: {
        title: 'Authentication Error',
        description: 'There was a problem with your authentication. You may need to sign in again.',
        icon: '🔐',
        suggestions: [
            'Try signing out and back in',
            'Clear your browser cookies',
            'Check if your session expired'
        ]
    },
    notFound: {
        title: 'Resource Not Found',
        description: 'The resource you\'re looking for doesn\'t exist or has been moved.',
        icon: '🔍',
        suggestions: [
            'Check the URL for typos',
            'Go back to the previous page',
            'Use our search feature'
        ]
    },
    generic: {
        title: 'Something Went Wrong',
        description: 'An unexpected error occurred. Our team has been notified and is working on a fix.',
        icon: '⚠️',
        suggestions: [
            'Try refreshing the page',
            'Clear your browser cache',
            'Try again in a few minutes',
            'Contact support if the problem persists'
        ]
    }
};

export default function ErrorPage({ error, reset }: ErrorPageProps) {
    const [isRetrying, setIsRetrying] = React.useState(false);
    const [copySuccess, setCopySuccess] = React.useState(false);
    const [showDetails, setShowDetails] = React.useState(false);

    const errorType = getErrorType(error);
    const config = errorConfigs[errorType];

    // Log error for monitoring
    React.useEffect(() => {
        console.error('Application Error:', {
            message: error.message,
            digest: error.digest,
            stack: error.stack,
            timestamp: new Date().toISOString(),
            url: typeof window !== 'undefined' ? window.location.href : 'unknown',
            userAgent: typeof window !== 'undefined' ? window.navigator.userAgent : 'unknown'
        });

        // In a real app, you'd send this to your error tracking service
        // Example: Sentry, LogRocket, Bugsnag, etc.
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'exception', {
                description: error.message,
                fatal: false
            });
        }
    }, [error]);

    const handleRetry = async () => {
        setIsRetrying(true);

        // Add a small delay to show loading state
        setTimeout(() => {
            reset();
            setIsRetrying(false);
        }, 1000);
    };

    const copyErrorDetails = async () => {
        const errorDetails = `
Error Details:
- Message: ${error.message}
- Digest: ${error.digest || 'N/A'}
- Timestamp: ${new Date().toISOString()}
- URL: ${typeof window !== 'undefined' ? window.location.href : 'unknown'}
- User Agent: ${typeof window !== 'undefined' ? window.navigator.userAgent : 'unknown'}
        `.trim();

        try {
            await navigator.clipboard.writeText(errorDetails);
            setCopySuccess(true);
            setTimeout(() => setCopySuccess(false), 2000);
        } catch (err) {
            console.error('Failed to copy to clipboard:', err);
        }
    };

    return (
        <MainLayout showBreadcrumbs={false}>
            <div className="min-h-screen bg-gradient-to-br from-background via-background to-destructive/5">
                <section className="min-h-screen flex items-center py-20">
                    <div className="container mx-auto px-4">
                        <div className="max-w-4xl mx-auto text-center">
                            {/* Error Icon Animation */}
                            <motion.div
                                initial={{ opacity: 0, scale: 0.8 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.6 }}
                                className="mb-8"
                            >
                                <div className="relative">
                                    <div className="text-6xl lg:text-8xl mb-4">
                                        {config.icon}
                                    </div>
                                    <motion.div
                                        initial={{ rotate: 0 }}
                                        animate={{ rotate: [0, -5, 5, 0] }}
                                        transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                                        className="absolute top-0 right-1/2 transform translate-x-8 -translate-y-2"
                                    >
                                        <AlertTriangle className="h-8 w-8 lg:h-12 lg:w-12 text-destructive" />
                                    </motion.div>
                                </div>
                            </motion.div>

                            {/* Error Message */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                className="mb-8"
                            >
                                <h1 className="text-3xl lg:text-4xl font-bold text-foreground mb-4">
                                    {config.title}
                                </h1>
                                <p className="text-xl text-muted-foreground mb-8 leading-relaxed max-w-2xl mx-auto">
                                    {config.description}
                                </p>
                            </motion.div>

                            {/* Action Buttons */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                                className="mb-12"
                            >
                                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                    <Button
                                        onClick={handleRetry}
                                        size="lg"
                                        leftIcon={isRetrying ? <RefreshCw className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                        disabled={isRetrying}
                                        className="min-w-[140px]"
                                    >
                                        {isRetrying ? 'Retrying...' : 'Try Again'}
                                    </Button>

                                    <Link href="/">
                                        <Button
                                            variant="outline"
                                            size="lg"
                                            leftIcon={<Home className="h-4 w-4" />}
                                        >
                                            Go Home
                                        </Button>
                                    </Link>

                                    <Button
                                        variant="outline"
                                        size="lg"
                                        onClick={() => window.history.back()}
                                        leftIcon={<ArrowLeft className="h-4 w-4" />}
                                    >
                                        Go Back
                                    </Button>
                                </div>
                            </motion.div>

                            {/* Troubleshooting Suggestions */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.6 }}
                                className="mb-12"
                            >
                                <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                                    <CardContent className="p-6">
                                        <h3 className="text-lg font-semibold text-foreground mb-4">
                                            What you can try:
                                        </h3>
                                        <div className="grid md:grid-cols-2 gap-3 text-left">
                                            {config.suggestions.map((suggestion, index) => (
                                                <motion.div
                                                    key={index}
                                                    initial={{ opacity: 0, x: -20 }}
                                                    animate={{ opacity: 1, x: 0 }}
                                                    transition={{ duration: 0.5, delay: 0.1 * index }}
                                                    className="flex items-center gap-3 text-muted-foreground"
                                                >
                                                    <div className="w-2 h-2 bg-primary rounded-full flex-shrink-0" />
                                                    <span className="text-sm">{suggestion}</span>
                                                </motion.div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>

                            {/* Error Details (Collapsible) */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.8 }}
                                className="mb-12"
                            >
                                <Button
                                    variant="link"
                                    onClick={() => setShowDetails(!showDetails)}
                                    leftIcon={<Bug className="h-4 w-4" />}
                                    className="text-muted-foreground hover:text-foreground mb-4"
                                >
                                    {showDetails ? 'Hide' : 'Show'} Technical Details
                                </Button>

                                {showDetails && (
                                    <motion.div
                                        initial={{ opacity: 0, height: 0 }}
                                        animate={{ opacity: 1, height: 'auto' }}
                                        transition={{ duration: 0.3 }}
                                    >
                                        <Card className="bg-muted/30 backdrop-blur-sm border-border/50">
                                            <CardContent className="p-4">
                                                <div className="text-left space-y-3">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="font-medium text-foreground">Error Details</h4>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={copyErrorDetails}
                                                            leftIcon={copySuccess ? <CheckCircle className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
                                                            className="text-xs"
                                                        >
                                                            {copySuccess ? 'Copied!' : 'Copy'}
                                                        </Button>
                                                    </div>
                                                    <div className="text-sm text-muted-foreground space-y-1">
                                                        <div><strong>Message:</strong> {error.message}</div>
                                                        {error.digest && <div><strong>Error ID:</strong> {error.digest}</div>}
                                                        <div><strong>Timestamp:</strong> {new Date().toLocaleString()}</div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </motion.div>
                                )}
                            </motion.div>

                            {/* Contact Support */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 1.0 }}
                                className="border-t border-border/30 pt-8"
                            >
                                <Card className="bg-muted/30 backdrop-blur-sm border-border/50">
                                    <CardContent className="p-6">
                                        <h4 className="text-lg font-semibold text-foreground mb-4">
                                            Still Having Issues?
                                        </h4>
                                        <p className="text-muted-foreground mb-6">
                                            Our support team is here to help. Please include the error details above when contacting us.
                                        </p>

                                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                            <Link href="/contact">
                                                <Button
                                                    variant="outline"
                                                    leftIcon={<Mail className="h-4 w-4" />}
                                                >
                                                    Contact Support
                                                </Button>
                                            </Link>

                                            <div className="flex flex-col sm:flex-row gap-4 text-sm text-muted-foreground">
                                                <a
                                                    href="mailto:support@creativebusiness.com"
                                                    className="flex items-center gap-2 hover:text-primary transition-colors"
                                                >
                                                    <Mail className="h-4 w-4" />
                                                    support@creativebusiness.com
                                                </a>
                                                <a
                                                    href="tel:+442071234567"
                                                    className="flex items-center gap-2 hover:text-primary transition-colors"
                                                >
                                                    <Phone className="h-4 w-4" />
                                                    +44 20 7123 4567
                                                </a>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </div>
                    </div>
                </section>
            </div>
        </MainLayout>
    );
}