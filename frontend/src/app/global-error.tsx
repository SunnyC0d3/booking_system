'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, RefreshCw, Home, Bug } from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';

interface GlobalErrorProps {
    error: Error & { digest?: string };
    reset: () => void;
}

// Global Error component for Next.js App Router
export default function GlobalError({ error, reset }: GlobalErrorProps) {
    const [showDetails, setShowDetails] = React.useState(false);
    const [isRetrying, setIsRetrying] = React.useState(false);

    // Log error for monitoring (only in browser)
    React.useEffect(() => {
        console.error('Global Error Boundary caught:', {
            message: error.message,
            digest: error.digest,
            stack: error.stack,
            timestamp: new Date().toISOString(),
            url: typeof window !== 'undefined' ? window.location.href : 'unknown',
        });

        // Send to analytics/error tracking
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'exception', {
                description: `Global Error: ${error.message}`,
                fatal: true,
            });
        }
    }, [error]);

    const handleRetry = async () => {
        setIsRetrying(true);

        // Add small delay for UX
        setTimeout(() => {
            reset();
            setIsRetrying(false);
        }, 500);
    };

    const handleGoHome = () => {
        window.location.href = '/';
    };

    return (
        <html>
        <body>
        <div className="min-h-screen flex items-center justify-center p-6 bg-background">
            <Card className="max-w-lg w-full bg-card/50 backdrop-blur-sm border-destructive/20">
                <CardContent className="p-8 text-center">
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: 0.5 }}
                    >
                        {/* Error Icon */}
                        <div className="w-20 h-20 bg-destructive/10 rounded-full flex items-center justify-center mx-auto mb-6">
                            <AlertTriangle className="h-10 w-10 text-destructive" />
                        </div>

                        {/* Error Title */}
                        <h1 className="text-2xl font-bold text-foreground mb-3">
                            Application Error
                        </h1>

                        {/* Error Description */}
                        <p className="text-muted-foreground mb-8 text-sm leading-relaxed">
                            Something went wrong with the application. This error has been logged and our team has been notified.
                        </p>

                        {/* Action Buttons */}
                        <div className="flex flex-col gap-3 mb-6">
                            <Button
                                onClick={handleRetry}
                                size="lg"
                                leftIcon={
                                    isRetrying ? (
                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="h-4 w-4" />
                                    )
                                }
                                disabled={isRetrying}
                                className="w-full"
                            >
                                {isRetrying ? 'Retrying...' : 'Try Again'}
                            </Button>

                            <Button
                                variant="outline"
                                size="lg"
                                onClick={handleGoHome}
                                leftIcon={<Home className="h-4 w-4" />}
                                className="w-full"
                            >
                                Go to Homepage
                            </Button>
                        </div>

                        {/* Error Details Toggle */}
                        <Button
                            variant="link"
                            size="sm"
                            onClick={() => setShowDetails(!showDetails)}
                            leftIcon={<Bug className="h-4 w-4" />}
                            className="text-xs text-muted-foreground"
                        >
                            {showDetails ? 'Hide' : 'Show'} Error Details
                        </Button>

                        {/* Error Details */}
                        {showDetails && (
                            <motion.div
                                initial={{ opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: 'auto' }}
                                transition={{ duration: 0.3 }}
                                className="mt-4 pt-4 border-t border-border/30"
                            >
                                <div className="text-left text-xs font-mono bg-muted/30 rounded p-3 break-all">
                                    <div className="text-destructive mb-2 font-semibold">
                                        {error.message}
                                    </div>
                                    {error.digest && (
                                        <div className="text-muted-foreground mb-2">
                                            <span className="font-semibold">Error ID:</span> {error.digest}
                                        </div>
                                    )}
                                    <div className="text-muted-foreground text-xs">
                                        <span className="font-semibold">Time:</span> {new Date().toLocaleString()}
                                    </div>
                                </div>
                            </motion.div>
                        )}
                    </motion.div>
                </CardContent>
            </Card>
        </div>
        </body>
        </html>
    );
}