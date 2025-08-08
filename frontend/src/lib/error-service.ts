// lib/error-service.ts
import * as React from 'react';
import { ErrorContext, ErrorReport } from '@/types/error';

class ErrorService {
    private static instance: ErrorService;
    private errors: ErrorReport[] = [];
    private readonly maxErrors = 50;

    private constructor() {
        this.setupGlobalErrorHandlers();
    }

    static getInstance(): ErrorService {
        if (!ErrorService.instance) {
            ErrorService.instance = new ErrorService();
        }
        return ErrorService.instance;
    }

    private setupGlobalErrorHandlers() {
        if (typeof window === 'undefined') return;

        // Handle uncaught JavaScript errors
        window.addEventListener('error', (event) => {
            this.reportError({
                type: 'global',
                severity: 'high',
                error: event.error || new Error(event.message),
                context: {
                    component: 'window',
                    action: 'unhandledError',
                    metadata: {
                        filename: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                    }
                }
            });
        });

        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.reportError({
                type: 'async',
                severity: 'high',
                error: new Error(`Unhandled promise rejection: ${event.reason}`),
                context: {
                    component: 'promise',
                    action: 'unhandledRejection',
                    metadata: {
                        reason: event.reason,
                    }
                }
            });
        });

        // Handle network errors
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            try {
                const response = await originalFetch(...args);
                if (!response.ok && response.status >= 500) {
                    this.reportError({
                        type: 'network',
                        severity: response.status >= 500 ? 'high' : 'medium',
                        error: new Error(`Network error: ${response.status} ${response.statusText}`),
                        context: {
                            component: 'fetch',
                            action: 'networkRequest',
                            metadata: {
                                url: typeof args[0] === 'string' ? args[0] : args[0]?.url,
                                status: response.status,
                                statusText: response.statusText,
                            }
                        }
                    });
                }
                return response;
            } catch (error) {
                this.reportError({
                    type: 'network',
                    severity: 'high',
                    error: error as Error,
                    context: {
                        component: 'fetch',
                        action: 'networkRequest',
                        metadata: {
                            url: typeof args[0] === 'string' ? args[0] : args[0]?.url,
                        }
                    }
                });
                throw error;
            }
        };
    }

    private generateFingerprint(error: Error, context: ErrorContext): string {
        const components = [
            error.message,
            error.name,
            context.component,
            context.route,
        ].filter(Boolean);

        return btoa(components.join('|')).substring(0, 16);
    }

    private createErrorReport(
        type: ErrorReport['type'],
        severity: ErrorReport['severity'],
        error: Error,
        context: ErrorContext
    ): ErrorReport {
        const timestamp = new Date().toISOString();
        const id = `${timestamp.split('T')[0]}-${Math.random().toString(36).substr(2, 9)}`;

        return {
            id,
            type,
            severity,
            message: error.message,
            stack: error.stack,
            digest: (error as any).digest,
            timestamp,
            url: typeof window !== 'undefined' ? window.location.href : 'unknown',
            userAgent: typeof window !== 'undefined' ? window.navigator.userAgent : 'unknown',
            viewport: typeof window !== 'undefined' ? {
                width: window.innerWidth,
                height: window.innerHeight
            } : null,
            memory: typeof window !== 'undefined' && 'memory' in performance ? {
                usedJSHeapSize: (performance as any).memory?.usedJSHeapSize,
                totalJSHeapSize: (performance as any).memory?.totalJSHeapSize,
                jsHeapSizeLimit: (performance as any).memory?.jsHeapSizeLimit
            } : undefined,
            context,
            fingerprint: this.generateFingerprint(error, context)
        };
    }

    reportError(params: {
        type: ErrorReport['type'];
        severity: ErrorReport['severity'];
        error: Error;
        context: ErrorContext;
    }) {
        const errorReport = this.createErrorReport(
            params.type,
            params.severity,
            params.error,
            params.context
        );

        // Add to local storage
        this.addError(errorReport);

        // Send to external services
        this.sendToExternalServices(errorReport);

        // Console log in development
        if (process.env.NODE_ENV === 'development') {
            console.group(`🚨 ${params.type.toUpperCase()} ERROR - ${params.severity.toUpperCase()}`);
            console.error('Error:', params.error);
            console.info('Context:', params.context);
            console.info('Report:', errorReport);
            console.groupEnd();
        }

        return errorReport;
    }

    private addError(errorReport: ErrorReport) {
        this.errors.unshift(errorReport);

        // Keep only the most recent errors
        if (this.errors.length > this.maxErrors) {
            this.errors = this.errors.slice(0, this.maxErrors);
        }

        // Store in localStorage
        try {
            localStorage.setItem('app_error_reports', JSON.stringify(this.errors));
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    private sendToExternalServices(errorReport: ErrorReport) {
        // Send to Google Analytics
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'exception', {
                description: errorReport.message,
                fatal: errorReport.severity === 'critical',
                error_type: errorReport.type,
                severity: errorReport.severity,
                fingerprint: errorReport.fingerprint,
            });
        }

        // Send to your custom error endpoint
        if (typeof window !== 'undefined') {
            fetch('/api/errors', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(errorReport),
            }).catch(() => {
                // Silently fail - we don't want error reporting to cause more errors
            });
        }
    }

    getErrors(): ErrorReport[] {
        return [...this.errors];
    }

    getErrorsByType(type: ErrorReport['type']): ErrorReport[] {
        return this.errors.filter(error => error.type === type);
    }

    getErrorsBySeverity(severity: ErrorReport['severity']): ErrorReport[] {
        return this.errors.filter(error => error.severity === severity);
    }

    clearErrors() {
        this.errors = [];
        try {
            localStorage.removeItem('app_error_reports');
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    // Load errors from localStorage on initialization
    loadStoredErrors() {
        try {
            const stored = localStorage.getItem('app_error_reports');
            if (stored) {
                this.errors = JSON.parse(stored);
            }
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    // Get error statistics
    getErrorStats() {
        const stats = {
            total: this.errors.length,
            byType: {} as Record<string, number>,
            bySeverity: {} as Record<string, number>,
            recent: this.errors.filter(error =>
                Date.now() - new Date(error.timestamp).getTime() < 24 * 60 * 60 * 1000
            ).length,
        };

        this.errors.forEach(error => {
            stats.byType[error.type] = (stats.byType[error.type] || 0) + 1;
            stats.bySeverity[error.severity] = (stats.bySeverity[error.severity] || 0) + 1;
        });

        return stats;
    }

    // Export errors for support
    exportErrors(): string {
        return JSON.stringify(this.errors, null, 2);
    }
}

// Export singleton instance
export const errorService = ErrorService.getInstance();

// React hook for using error service
export const useErrorService = () => {
    React.useEffect(() => {
        errorService.loadStoredErrors();
    }, []);

    const reportError = React.useCallback((
        type: ErrorReport['type'],
        severity: ErrorReport['severity'],
        error: Error,
        context: ErrorContext = {}
    ) => {
        return errorService.reportError({ type, severity, error, context });
    }, []);

    return {
        reportError,
        getErrors: () => errorService.getErrors(),
        getErrorStats: () => errorService.getErrorStats(),
        clearErrors: () => errorService.clearErrors(),
        exportErrors: () => errorService.exportErrors(),
    };
};

// Helper functions for common error scenarios
export const reportNetworkError = (error: Error, url: string) => {
    return errorService.reportError({
        type: 'network',
        severity: 'high',
        error,
        context: {
            component: 'api',
            action: 'request',
            metadata: { url }
        }
    });
};

export const reportComponentError = (error: Error, componentName: string) => {
    return errorService.reportError({
        type: 'boundary',
        severity: 'medium',
        error,
        context: {
            component: componentName,
            action: 'render'
        }
    });
};

export const reportAsyncError = (error: Error, operation: string) => {
    return errorService.reportError({
        type: 'async',
        severity: 'medium',
        error,
        context: {
            action: operation
        }
    });
};