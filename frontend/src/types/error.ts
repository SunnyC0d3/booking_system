// types/error.ts

export interface ErrorContext {
    userId?: string;
    sessionId?: string;
    route?: string;
    component?: string;
    action?: string;
    metadata?: Record<string, any>;
}

export interface ErrorReport {
    id: string;
    type: 'global' | 'boundary' | 'async' | 'network';
    severity: 'low' | 'medium' | 'high' | 'critical';
    message: string;
    stack?: string;
    digest?: string;
    timestamp: string;
    url: string;
    userAgent: string;
    viewport: {
        width: number;
        height: number;
    } | null;
    memory?: {
        usedJSHeapSize?: number;
        totalJSHeapSize?: number;
        jsHeapSizeLimit?: number;
    };
    context: ErrorContext;
    reproducible?: boolean;
    fingerprint: string;
}

// Error types for UI components
export type ErrorType =
    | 'network'
    | 'server'
    | 'not-found'
    | 'unauthorized'
    | 'forbidden'
    | 'validation'
    | 'empty-state'
    | 'maintenance'
    | 'rate-limit'
    | 'payment'
    | 'upload';

// Error boundary interfaces
export interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
    errorInfo: React.ErrorInfo | null;
}

export interface ErrorBoundaryProps {
    children: React.ReactNode;
    fallback?: React.ComponentType<ErrorFallbackProps>;
    onError?: (error: Error, errorInfo: React.ErrorInfo) => void;
    resetOnPropsChange?: boolean;
    resetKeys?: Array<string | number>;
}

export interface ErrorFallbackProps {
    error: Error;
    errorInfo: React.ErrorInfo | null;
    retry: () => void;
    resetErrorBoundary: () => void;
}

// Page error props
export interface ErrorPageProps {
    error: Error & { digest?: string };
    reset: () => void;
}