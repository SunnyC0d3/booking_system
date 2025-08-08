'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, RefreshCw, Home, Bug } from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';
import { cn } from '@/lib/cn';
import { ErrorBoundaryProps, ErrorFallbackProps, ErrorBoundaryState } from '@/types/error';

// Default Error Fallback Component
const DefaultErrorFallback: React.FC<ErrorFallbackProps> = ({
                                                                error,
                                                                errorInfo,
                                                                retry,
                                                                resetErrorBoundary
                                                            }) => {
    const [showDetails, setShowDetails] = React.useState(false);

    return (
        <div className="min-h-[400px] flex items-center justify-center p-6">
            <Card className="max-w-lg w-full bg-card/50 backdrop-blur-sm border-destructive/20">
                <CardContent className="p-6 text-center">
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: 0.5 }}
                    >
                        <div className="w-16 h-16 bg-destructive/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <AlertTriangle className="h-8 w-8 text-destructive" />
                        </div>

                        <h3 className="text-lg font-semibold text-foreground mb-2">
                            Component Error
                        </h3>

                        <p className="text-muted-foreground mb-6 text-sm">
                            This component encountered an error and couldn't render properly.
                        </p>

                        <div className="flex flex-col gap-3">
                            <Button
                                onClick={retry}
                                size="sm"
                                leftIcon={<RefreshCw className="h-4 w-4" />}
                            >
                                Try Again
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => window.location.href = '/'}
                                leftIcon={<Home className="h-4 w-4" />}
                            >
                                Go Home
                            </Button>

                            <Button
                                variant="link"
                                size="sm"
                                onClick={() => setShowDetails(!showDetails)}
                                leftIcon={<Bug className="h-4 w-4" />}
                                className="text-xs"
                            >
                                {showDetails ? 'Hide' : 'Show'} Details
                            </Button>
                        </div>

                        {showDetails && (
                            <motion.div
                                initial={{ opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: 'auto' }}
                                transition={{ duration: 0.3 }}
                                className="mt-4 pt-4 border-t border-border/30"
                            >
                                <div className="text-left text-xs font-mono bg-muted/30 rounded p-3">
                                    <div className="text-destructive mb-2">{error.message}</div>
                                    {errorInfo?.componentStack && (
                                        <div className="text-muted-foreground">
                                            Component: {errorInfo.componentStack.split('\n')[1]?.trim()}
                                        </div>
                                    )}
                                </div>
                            </motion.div>
                        )}
                    </motion.div>
                </CardContent>
            </Card>
        </div>
    );
};

export class ErrorBoundary extends React.Component<ErrorBoundaryProps, ErrorBoundaryState> {
    private resetTimeoutId: number | null = null;

    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
        };
    }

    static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryState> {
        return {
            hasError: true,
            error,
        };
    }

    override componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        this.setState({
            error,
            errorInfo,
        });

        // Call custom error handler
        this.props.onError?.(error, errorInfo);

        // Log error details
        console.error('ErrorBoundary caught an error:', {
            error: error.message,
            stack: error.stack,
            componentStack: errorInfo.componentStack,
            timestamp: new Date().toISOString(),
        });

        // Send to error tracking service
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'exception', {
                description: `Component Error: ${error.message}`,
                fatal: false,
            });
        }
    }

    override componentDidUpdate(prevProps: ErrorBoundaryProps) {
        const { resetKeys } = this.props;
        const { hasError } = this.state;

        // Reset error state if resetKeys changed
        if (hasError && prevProps.resetKeys !== resetKeys) {
            if (resetKeys?.some((key, idx) => prevProps.resetKeys?.[idx] !== key)) {
                this.resetErrorBoundary();
            }
        }
    }

    resetErrorBoundary = () => {
        if (this.resetTimeoutId) {
            clearTimeout(this.resetTimeoutId);
        }

        this.setState({
            hasError: false,
            error: null,
            errorInfo: null,
        });
    };

    retry = () => {
        // Add a small delay to prevent immediate re-error
        this.resetTimeoutId = window.setTimeout(() => {
            this.resetErrorBoundary();
        }, 100);
    };

    override render() {
        if (this.state.hasError) {
            const FallbackComponent = this.props.fallback || DefaultErrorFallback;

            return (
                <FallbackComponent
                    error={this.state.error!}
                    errorInfo={this.state.errorInfo}
                    retry={this.retry}
                    resetErrorBoundary={this.resetErrorBoundary}
                />
            );
        }

        return this.props.children;
    }
}

// Hook for error boundary context
export const useErrorHandler = () => {
    return React.useCallback((error: Error, errorInfo?: React.ErrorInfo) => {
        console.error('Manual error caught:', error);

        // You could throw the error to trigger error boundary
        // or handle it differently based on your needs
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'exception', {
                description: error.message,
                fatal: false,
            });
        }
    }, []);
};

// Higher Order Component wrapper
export function withErrorBoundary<P extends object>(
    Component: React.ComponentType<P>,
    errorBoundaryProps?: Omit<ErrorBoundaryProps, 'children'>
) {
    const WrappedComponent = (props: P) => (
        <ErrorBoundary {...errorBoundaryProps}>
            <Component {...props} />
        </ErrorBoundary>
    );

    WrappedComponent.displayName = `withErrorBoundary(${Component.displayName || Component.name})`;

    return WrappedComponent;
}