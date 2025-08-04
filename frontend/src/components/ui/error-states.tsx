'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import {
    AlertTriangle,
    RefreshCw,
    Home,
    Search,
    ShoppingCart,
    Wifi,
    Server,
    Lock,
    FileX,
    Users,
    Package,
    CreditCard,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/cn';

// Error types
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

interface ErrorStateProps {
    type: ErrorType;
    title?: string;
    description?: string;
    action?: {
        label: string;
        onClick: () => void;
    };
    secondaryAction?: {
        label: string;
        onClick: () => void;
    };
    className?: string;
    size?: 'sm' | 'md' | 'lg';
    showIcon?: boolean;
}

// Error configurations
const errorConfigs: Record<ErrorType, {
    icon: React.ComponentType<{ className?: string }>;
    defaultTitle: string;
    defaultDescription: string;
    color: string;
}> = {
    network: {
        icon: Wifi,
        defaultTitle: 'Connection Problem',
        defaultDescription: 'Please check your internet connection and try again.',
        color: 'text-orange-500',
    },
    server: {
        icon: Server,
        defaultTitle: 'Server Error',
        defaultDescription: 'Something went wrong on our end. Please try again later.',
        color: 'text-red-500',
    },
    'not-found': {
        icon: FileX,
        defaultTitle: 'Page Not Found',
        defaultDescription: 'The page you\'re looking for doesn\'t exist or has been moved.',
        color: 'text-blue-500',
    },
    unauthorized: {
        icon: Lock,
        defaultTitle: 'Authentication Required',
        defaultDescription: 'Please sign in to access this page.',
        color: 'text-yellow-500',
    },
    forbidden: {
        icon: Lock,
        defaultTitle: 'Access Denied',
        defaultDescription: 'You don\'t have permission to access this resource.',
        color: 'text-red-500',
    },
    validation: {
        icon: AlertTriangle,
        defaultTitle: 'Invalid Input',
        defaultDescription: 'Please check your input and try again.',
        color: 'text-amber-500',
    },
    'empty-state': {
        icon: Package,
        defaultTitle: 'No Items Found',
        defaultDescription: 'There are no items to display at the moment.',
        color: 'text-gray-500',
    },
    maintenance: {
        icon: Server,
        defaultTitle: 'Under Maintenance',
        defaultDescription: 'We\'re performing scheduled maintenance. Please check back soon.',
        color: 'text-blue-500',
    },
    'rate-limit': {
        icon: AlertTriangle,
        defaultTitle: 'Too Many Requests',
        defaultDescription: 'You\'ve made too many requests. Please wait before trying again.',
        color: 'text-orange-500',
    },
    payment: {
        icon: CreditCard,
        defaultTitle: 'Payment Failed',
        defaultDescription: 'Your payment could not be processed. Please try again.',
        color: 'text-red-500',
    },
    upload: {
        icon: FileX,
        defaultTitle: 'Upload Failed',
        defaultDescription: 'The file could not be uploaded. Please try again.',
        color: 'text-red-500',
    },
};

export const ErrorState: React.FC<ErrorStateProps> = ({
                                                          type,
                                                          title,
                                                          description,
                                                          action,
                                                          secondaryAction,
                                                          className,
                                                          size = 'md',
                                                          showIcon = true,
                                                      }) => {
    const config = errorConfigs[type];
    const Icon = config.icon;

    const sizeClasses = {
        sm: {
            container: 'p-4',
            icon: 'w-8 h-8 mb-2',
            title: 'text-lg',
            description: 'text-sm',
        },
        md: {
            container: 'p-8',
            icon: 'w-12 h-12 mb-4',
            title: 'text-xl',
            description: 'text-base',
        },
        lg: {
            container: 'p-12',
            icon: 'w-16 h-16 mb-6',
            title: 'text-2xl',
            description: 'text-lg',
        },
    };

    const classes = sizeClasses[size];

    return (
        <motion.div
            className={cn(
                'flex flex-col items-center justify-center text-center',
                classes.container,
                className
            )}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
        >
            {showIcon && (
                <Icon className={cn(classes.icon, config.color)} />
            )}

            <h3 className={cn('font-semibold text-foreground mb-2', classes.title)}>
                {title || config.defaultTitle}
            </h3>

            <p className={cn('text-muted-foreground mb-6 max-w-md', classes.description)}>
                {description || config.defaultDescription}
            </p>

            <div className="flex flex-col sm:flex-row gap-3">
                {action && (
                    <Button onClick={action.onClick} size={size}>
                        {action.label}
                    </Button>
                )}

                {secondaryAction && (
                    <Button
                        onClick={secondaryAction.onClick}
                        variant="outline"
                        size={size}
                    >
                        {secondaryAction.label}
                    </Button>
                )}
            </div>
        </motion.div>
    );
};

// Specific error components
export const NetworkError: React.FC<Omit<ErrorStateProps, 'type'>> = (props) => (
    <ErrorState
        type="network"
        action={{
            label: 'Try Again',
            onClick: () => window.location.reload(),
        }}
        {...props}
    />
);

export const ServerError: React.FC<Omit<ErrorStateProps, 'type'>> = (props) => (
    <ErrorState
        type="server"
        action={{
            label: 'Refresh Page',
            onClick: () => window.location.reload(),
        }}
        secondaryAction={{
            label: 'Go Home',
            onClick: () => window.location.href = '/',
        }}
        {...props}
    />
);

export const NotFound: React.FC<Omit<ErrorStateProps, 'type'>> = (props) => (
    <ErrorState
        type="not-found"
        action={{
            label: 'Go Home',
            onClick: () => window.location.href = '/',
        }}
        secondaryAction={{
            label: 'Search',
            onClick: () => window.location.href = '/search',
        }}
        {...props}
    />
);

export const Unauthorized: React.FC<Omit<ErrorStateProps, 'type'>> = (props) => (
    <ErrorState
        type="unauthorized"
        action={{
            label: 'Sign In',
            onClick: () => window.location.href = '/login',
        }}
        {...props}
    />
);

// Empty state components
interface EmptyStateProps {
    icon?: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
    action?: {
        label: string;
        onClick: () => void;
    };
    className?: string;
}

export const EmptyCart: React.FC<Omit<EmptyStateProps, 'icon' | 'title' | 'description'>> = (props) => (
    <ErrorState
        type="empty-state"
        title="Your cart is empty"
        description="Add some items to your cart to get started with your order."
        action={{
            label: 'Continue Shopping',
            onClick: () => window.location.href = '/products',
        }}
        {...props}
    />
);

export const EmptySearch: React.FC<{ query?: string } & Omit<EmptyStateProps, 'icon' | 'title' | 'description'>> = ({ query, ...props }) => (
    <ErrorState
        type="empty-state"
        title="No results found"
        description={query ? `No results found for "${query}". Try different keywords.` : 'Try adjusting your search criteria.'}
        action={{
            label: 'Clear Filters',
            onClick: () => window.location.href = '/products',
        }}
        {...props}
    />
);

export const EmptyOrders: React.FC<Omit<EmptyStateProps, 'icon' | 'title' | 'description'>> = (props) => (
    <ErrorState
        type="empty-state"
        title="No orders yet"
        description="You haven't placed any orders. Start shopping to see your order history here."
        action={{
            label: 'Start Shopping',
            onClick: () => window.location.href = '/products',
        }}
        {...props}
    />
);

// Loading states with errors
export const LoadingError: React.FC<{
    onRetry: () => void;
    className?: string;
}> = ({ onRetry, className }) => (
    <Card className={cn('border-destructive/50', className)}>
        <CardContent className="p-6 text-center">
            <AlertTriangle className="w-12 h-12 text-destructive mx-auto mb-4" />
            <h3 className="font-semibold mb-2">Failed to load</h3>
            <p className="text-muted-foreground mb-4">
                Something went wrong while loading this content.
            </p>
            <Button onClick={onRetry} variant="outline" size="sm">
                <RefreshCw className="w-4 h-4 mr-2" />
                Try Again
            </Button>
        </CardContent>
    </Card>
);

// Accessibility components
interface ScreenReaderOnlyProps {
    children: React.ReactNode;
}

export const ScreenReaderOnly: React.FC<ScreenReaderOnlyProps> = ({ children }) => (
    <span className="sr-only">{children}</span>
);

interface SkipLinkProps {
    href: string;
    children: React.ReactNode;
}

export const SkipLink: React.FC<SkipLinkProps> = ({ href, children }) => (
    <a
        href={href}
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary focus:text-primary-foreground focus:rounded-md focus:text-sm focus:font-medium"
    >
        {children}
    </a>
);

// Focus trap component
interface FocusTrapProps {
    children: React.ReactNode;
    isActive: boolean;
}

export const FocusTrap: React.FC<FocusTrapProps> = ({ children, isActive }) => {
    const containerRef = React.useRef<HTMLDivElement>(null);
    const firstFocusableRef = React.useRef<HTMLElement | null>(null);
    const lastFocusableRef = React.useRef<HTMLElement | null>(null);

    React.useEffect(() => {
        if (!isActive || !containerRef.current) return;

        const focusableElements = containerRef.current.querySelectorAll(
            'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select, [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length > 0) {
            firstFocusableRef.current = focusableElements[0] as HTMLElement;
            lastFocusableRef.current = focusableElements[focusableElements.length - 1] as HTMLElement;
            firstFocusableRef.current?.focus();
        }

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstFocusableRef.current) {
                    e.preventDefault();
                    lastFocusableRef.current?.focus();
                }
            } else {
                if (document.activeElement === lastFocusableRef.current) {
                    e.preventDefault();
                    firstFocusableRef.current?.focus();
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isActive]);

    return <div ref={containerRef}>{children}</div>;
};

// Live region for announcements
interface LiveRegionProps {
    children: React.ReactNode;
    level?: 'polite' | 'assertive';
    atomic?: boolean;
}

export const LiveRegion: React.FC<LiveRegionProps> = ({
                                                          children,
                                                          level = 'polite',
                                                          atomic = true
                                                      }) => (
    <div
        aria-live={level}
        aria-atomic={atomic}
        className="sr-only"
    >
        {children}
    </div>
);

// High contrast mode support
export const useHighContrast = () => {
    const [isHighContrast, setIsHighContrast] = React.useState(false);

    React.useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-contrast: high)');
        setIsHighContrast(mediaQuery.matches);

        const handleChange = (e: MediaQueryListEvent) => {
            setIsHighContrast(e.matches);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return isHighContrast;
};

// Reduced motion support
export const useReducedMotion = () => {
    const [prefersReducedMotion, setPrefersReducedMotion] = React.useState(false);

    React.useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        setPrefersReducedMotion(mediaQuery.matches);

        const handleChange = (e: MediaQueryListEvent) => {
            setPrefersReducedMotion(e.matches);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return prefersReducedMotion;
};

// Keyboard navigation helper
export const useKeyboardNavigation = (onEscape?: () => void, onEnter?: () => void) => {
    React.useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            switch (e.key) {
                case 'Escape':
                    onEscape?.();
                    break;
                case 'Enter':
                    if (e.target === document.activeElement) {
                        onEnter?.();
                    }
                    break;
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onEscape, onEnter]);
};

// Error boundary component
interface ErrorBoundaryState {
    hasError: boolean;
    error?: Error;
}

export class ErrorBoundary extends React.Component<
    { children: React.ReactNode; fallback?: React.ComponentType<{ error: Error; retry: () => void }> },
    ErrorBoundaryState
> {
    constructor(props: any) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('Error boundary caught an error:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            const FallbackComponent = this.props.fallback;

            if (FallbackComponent && this.state.error) {
                return (
                    <FallbackComponent
                        error={this.state.error}
                        retry={() => this.setState({ hasError: false, error: undefined })}
                    />
                );
            }

            return (
                <ErrorState
                    type="server"
                    title="Something went wrong"
                    description="An unexpected error occurred. Please refresh the page."
                    action={{
                        label: 'Refresh Page',
                        onClick: () => window.location.reload(),
                    }}
                />
            );
        }

        return this.props.children;
    }
}