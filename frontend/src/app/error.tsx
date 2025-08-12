'use client'

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
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
    Loader2,
    Wifi,
    Clock,
    Shield,
    Search,
    AlertCircle,
} from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';
import { MainLayout } from '@/components/layout/MainLayout';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

interface ErrorPageProps {
    error: Error & { digest?: string };
    reset: () => void;
}

type ErrorType = 'network' | 'timeout' | 'auth' | 'notFound' | 'generic';

interface ErrorConfig {
    title: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
    suggestions: string[];
    actions?: Array<{
        label: string;
        action: () => void;
        variant?: 'default' | 'outline' | 'destructive';
    }>;
}

const getErrorType = (error: Error): ErrorType => {
    const message = error.message.toLowerCase();
    const stack = error.stack?.toLowerCase() || '';

    if (message.includes('network') || message.includes('fetch') || message.includes('connection')) {
        return 'network';
    }
    if (message.includes('timeout') || message.includes('aborted')) {
        return 'timeout';
    }
    if (message.includes('auth') || message.includes('unauthorized') || message.includes('403') || message.includes('401')) {
        return 'auth';
    }
    if (message.includes('not found') || message.includes('404') || stack.includes('404')) {
        return 'notFound';
    }

    return 'generic';
};

const useErrorConfigs = (): Record<ErrorType, ErrorConfig> => {
    const router = useRouter();

    return React.useMemo(() => ({
        network: {
            title: 'Connection Problem',
            description: 'We\'re having trouble connecting to our servers. Please check your internet connection and try again.',
            icon: Wifi,
            color: 'text-blue-600 dark:text-blue-400',
            suggestions: [
                'Check your internet connection',
                'Try refreshing the page',
                'Disable any VPN or proxy',
                'Clear your browser cache',
                'Try switching to a different network'
            ],
            actions: [
                {
                    label: 'Check Connection',
                    action: () => {
                        if (navigator.onLine) {
                            toast.success('You appear to be online');
                        } else {
                            toast.error('You appear to be offline');
                        }
                    },
                    variant: 'outline'
                }
            ]
        },
        timeout: {
            title: 'Request Timeout',
            description: 'The request took too long to complete. Our servers might be experiencing high traffic.',
            icon: Clock,
            color: 'text-orange-600 dark:text-orange-400',
            suggestions: [
                'Wait a moment and try again',
                'Check your connection speed',
                'Try accessing during off-peak hours',
                'Refresh the page to retry'
            ]
        },
        auth: {
            title: 'Authentication Error',
            description: 'There was a problem with your authentication. You may need to sign in again.',
            icon: Shield,
            color: 'text-red-600 dark:text-red-400',
            suggestions: [
                'Try signing out and back in',
                'Clear your browser cookies',
                'Check if your session expired',
                'Verify your account status'
            ],
            actions: [
                {
                    label: 'Go to Login',
                    action: () => router.push('/login'),
                    variant: 'default'
                }
            ]
        },
        notFound: {
            title: 'Resource Not Found',
            description: 'The resource you\'re looking for doesn\'t exist or has been moved.',
            icon: Search,
            color: 'text-purple-600 dark:text-purple-400',
            suggestions: [
                'Check the URL for typos',
                'Go back to the previous page',
                'Use our search feature',
                'Browse our main sections'
            ]
        },
        generic: {
            title: 'Something Went Wrong',
            description: 'An unexpected error occurred. Our team has been notified and is working on a fix.',
            icon: AlertCircle,
            color: 'text-red-600 dark:text-red-400',
            suggestions: [
                'Try refreshing the page',
                'Clear your browser cache',
                'Try again in a few minutes',
                'Contact support if the problem persists'
            ]
        }
    }), [router]);
};

const ErrorIcon = React.memo(({
                                  IconComponent,
                                  color
                              }: {
    IconComponent: React.ComponentType<{ className?: string }>;
    color: string;
}) => (
    <motion.div
        initial={{ opacity: 0, scale: 0.8 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.6 }}
        className="mb-8"
    >
        <div className="relative">
            <div className={cn(
                "w-24 h-24 lg:w-32 lg:h-32 mx-auto mb-4 rounded-full flex items-center justify-center",
                "bg-muted/50 border border-border/50"
            )}>
                <IconComponent className={cn("h-12 w-12 lg:h-16 lg:w-16", color)} />
            </div>
            <motion.div
                initial={{ rotate: 0 }}
                animate={{ rotate: [0, -5, 5, 0] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                className="absolute top-0 right-1/2 transform translate-x-8 -translate-y-2"
            >
                <AlertTriangle className="h-6 w-6 lg:h-8 lg:w-8 text-red-500" />
            </motion.div>
        </div>
    </motion.div>
));

ErrorIcon.displayName = 'ErrorIcon';

const SuggestionsList = React.memo(({
                                        suggestions
                                    }: {
    suggestions: string[];
}) => (
    <div className="grid md:grid-cols-2 gap-3 text-left">
        {suggestions.map((suggestion, index) => (
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
));

SuggestionsList.displayName = 'SuggestionsList';

const ErrorDetails = React.memo(({
                                     error,
                                     showDetails,
                                     onToggle,
                                     onCopy,
                                     copySuccess
                                 }: {
    error: Error & { digest?: string };
    showDetails: boolean;
    onToggle: () => void;
    onCopy: () => void;
    copySuccess: boolean;
}) => (
    <div>
        <Button
            variant="ghost"
            onClick={onToggle}
            className="text-muted-foreground hover:text-foreground mb-4 p-0 h-auto"
        >
            <Bug className="h-4 w-4 mr-2" />
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
                                    onClick={onCopy}
                                    className="text-xs"
                                >
                                    {copySuccess ? (
                                        <>
                                            <CheckCircle className="h-3 w-3 mr-1" />
                                            Copied!
                                        </>
                                    ) : (
                                        <>
                                            <Copy className="h-3 w-3 mr-1" />
                                            Copy
                                        </>
                                    )}
                                </Button>
                            </div>
                            <div className="text-sm text-muted-foreground space-y-2 font-mono">
                                <div><strong>Message:</strong> {error.message}</div>
                                {error.digest && <div><strong>Error ID:</strong> {error.digest}</div>}
                                <div><strong>Timestamp:</strong> {new Date().toLocaleString()}</div>
                                {typeof window !== 'undefined' && (
                                    <div><strong>URL:</strong> {window.location.href}</div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </motion.div>
        )}
    </div>
));

ErrorDetails.displayName = 'ErrorDetails';

export default function ErrorPage({ error, reset }: ErrorPageProps) {
    const router = useRouter();
    const [isRetrying, setIsRetrying] = React.useState(false);
    const [copySuccess, setCopySuccess] = React.useState(false);
    const [showDetails, setShowDetails] = React.useState(false);
    const [retryCount, setRetryCount] = React.useState(0);

    const errorConfigs = useErrorConfigs();
    const errorType = React.useMemo(() => getErrorType(error), [error]);
    const config = errorConfigs[errorType];

    React.useEffect(() => {
        const errorDetails = {
            message: error.message,
            digest: error.digest,
            stack: error.stack,
            timestamp: new Date().toISOString(),
            url: typeof window !== 'undefined' ? window.location.href : 'unknown',
            userAgent: typeof window !== 'undefined' ? window.navigator.userAgent : 'unknown',
            errorType,
            retryCount,
        };

        console.error('Application Error:', errorDetails);

        if (typeof window !== 'undefined') {
            try {
                if (window.gtag) {
                    window.gtag('event', 'exception', {
                        description: error.message,
                        fatal: false,
                        custom_map: {
                            error_type: errorType,
                            error_digest: error.digest,
                        }
                    });
                }

                localStorage.setItem('lastError', JSON.stringify({
                    ...errorDetails,
                    stack: undefined // Don't store stack in localStorage
                }));
            } catch (e) {
                console.warn('Failed to log error details:', e);
            }
        }
    }, [error, errorType, retryCount]);

    const handleRetry = React.useCallback(async () => {
        setIsRetrying(true);
        setRetryCount(prev => prev + 1);

        setTimeout(() => {
            try {
                reset();
                toast.success('Page refreshed successfully');
            } catch (resetError) {
                console.error('Reset failed:', resetError);
                toast.error('Failed to refresh. Please try manually.');
            } finally {
                setIsRetrying(false);
            }
        }, 1000);
    }, [reset]);

    const handleGoBack = React.useCallback(() => {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();
        } else {
            router.push('/');
        }
    }, [router]);

    const copyErrorDetails = React.useCallback(async () => {
        const errorDetails = `
Error Report - Creative Business
=============================
Message: ${error.message}
Error ID: ${error.digest || 'N/A'}
Type: ${errorType}
Timestamp: ${new Date().toISOString()}
URL: ${typeof window !== 'undefined' ? window.location.href : 'unknown'}
User Agent: ${typeof window !== 'undefined' ? window.navigator.userAgent : 'unknown'}
Retry Count: ${retryCount}
        `.trim();

        try {
            await navigator.clipboard.writeText(errorDetails);
            setCopySuccess(true);
            toast.success('Error details copied to clipboard');
            setTimeout(() => setCopySuccess(false), 2000);
        } catch (err) {
            console.error('Failed to copy to clipboard:', err);
            toast.error('Failed to copy to clipboard');
        }
    }, [error, errorType, retryCount]);

    const handleContactSupport = React.useCallback(() => {
        const subject = encodeURIComponent(`Error Report: ${config.title}`);
        const body = encodeURIComponent(`
Hello Support Team,

I encountered an error on your website:

Error: ${error.message}
Error ID: ${error.digest || 'N/A'}
Time: ${new Date().toLocaleString()}
Page: ${typeof window !== 'undefined' ? window.location.href : 'unknown'}

Please help me resolve this issue.

Thank you.
        `);

        window.open(`mailto:support@creativebusiness.com?subject=${subject}&body=${body}`);
    }, [config.title, error]);

    return (
        <MainLayout showBreadcrumbs={false}>
            <div className="min-h-screen bg-gradient-to-br from-background via-background to-muted/20">
                <section className="min-h-screen flex items-center py-20">
                    <div className="container mx-auto px-4">
                        <div className="max-w-4xl mx-auto text-center">
                            <ErrorIcon IconComponent={config.icon} color={config.color} />

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
                                {retryCount > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Retry attempts: {retryCount}
                                    </p>
                                )}
                            </motion.div>

                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                                className="mb-12"
                            >
                                <div className="flex flex-col sm:flex-row gap-4 justify-center flex-wrap">
                                    <Button
                                        onClick={handleRetry}
                                        size="lg"
                                        disabled={isRetrying}
                                        className="min-w-[140px]"
                                    >
                                        {isRetrying ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Retrying...
                                            </>
                                        ) : (
                                            <>
                                                <RefreshCw className="mr-2 h-4 w-4" />
                                                Try Again
                                            </>
                                        )}
                                    </Button>

                                    <Link href="/">
                                        <Button variant="outline" size="lg">
                                            <Home className="mr-2 h-4 w-4" />
                                            Go Home
                                        </Button>
                                    </Link>

                                    <Button
                                        variant="outline"
                                        size="lg"
                                        onClick={handleGoBack}
                                    >
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Go Back
                                    </Button>

                                    {config.actions?.map((action, index) => (
                                        <Button
                                            key={index}
                                            variant={action.variant || 'outline'}
                                            size="lg"
                                            onClick={action.action}
                                        >
                                            {action.label}
                                        </Button>
                                    ))}
                                </div>
                            </motion.div>

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
                                        <SuggestionsList suggestions={config.suggestions} />
                                    </CardContent>
                                </Card>
                            </motion.div>

                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.8 }}
                                className="mb-12"
                            >
                                <ErrorDetails
                                    error={error}
                                    showDetails={showDetails}
                                    onToggle={() => setShowDetails(!showDetails)}
                                    onCopy={copyErrorDetails}
                                    copySuccess={copySuccess}
                                />
                            </motion.div>

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

                                        <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                            <div className="flex flex-col sm:flex-row gap-4 text-sm text-muted-foreground">
                                                <a
                                                    href="mailto:support@creativebusiness.com"
                                                    className="flex items-center gap-2 hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
                                                >
                                                    <Mail className="h-4 w-4" />
                                                    Contact Support
                                                </a>
                                                <a
                                                    href="mailto:support@creativebusiness.com"
                                                    className="flex items-center gap-2 hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
                                                >
                                                    <Mail className="h-4 w-4" />
                                                    support@creativebusiness.com
                                                </a>
                                                <a
                                                    href="tel:+442071234567"
                                                    className="flex items-center gap-2 hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 rounded px-1"
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