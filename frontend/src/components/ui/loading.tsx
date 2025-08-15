import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

const spinnerVariants = cva('animate-spin', {
    variants: {
        size: {
            sm: 'h-4 w-4',
            default: 'h-6 w-6',
            lg: 'h-8 w-8',
            xl: 'h-12 w-12',
        },
        variant: {
            default: 'text-primary',
            muted: 'text-muted-foreground',
            white: 'text-white',
        },
    },
    defaultVariants: {
        size: 'default',
        variant: 'default',
    },
});

export interface SpinnerProps
    extends React.HTMLAttributes<HTMLDivElement>,
        VariantProps<typeof spinnerVariants> {}

const Spinner = React.forwardRef<HTMLDivElement, SpinnerProps>(
    ({ className, size, variant, ...props }, ref) => (
        <div
            ref={ref}
            className={cn('flex items-center justify-center', className)}
            {...props}
        >
            <Loader2 className={cn(spinnerVariants({ size, variant }))} />
        </div>
    )
);
Spinner.displayName = 'Spinner';

export interface ShimmerProps extends React.HTMLAttributes<HTMLDivElement> {
    lines?: number;
    showAvatar?: boolean;
}

const Shimmer = React.forwardRef<HTMLDivElement, ShimmerProps>(
    ({ className, lines = 3, showAvatar = false, ...props }, ref) => (
        <div
            ref={ref}
            className={cn('animate-pulse space-y-3', className)}
            {...props}
        >
            {showAvatar && (
                <div className="flex items-center space-x-4">
                    <div className="rounded-full bg-muted h-12 w-12" />
                    <div className="space-y-2">
                        <div className="h-4 bg-muted rounded w-32" />
                        <div className="h-3 bg-muted rounded w-24" />
                    </div>
                </div>
            )}

            <div className="space-y-2">
                {Array.from({ length: lines }).map((_, i) => (
                    <div
                        key={i}
                        className={cn(
                            'h-4 bg-muted rounded',
                            i === lines - 1 ? 'w-2/3' : 'w-full'
                        )}
                    />
                ))}
            </div>
        </div>
    )
);
Shimmer.displayName = 'Shimmer';

export interface LoadingPageProps {
    message?: string;
    size?: 'sm' | 'default' | 'lg' | 'xl';
}

const LoadingPage: React.FC<LoadingPageProps> = ({
                                                     message = 'Loading...',
                                                     size = 'lg'
                                                 }) => (
    <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
        <Spinner size={size} />
        <p className="text-muted-foreground text-sm">{message}</p>
    </div>
);

export interface LoadingButtonProps extends React.HTMLAttributes<HTMLDivElement> {
    loading?: boolean;
    children: React.ReactNode;
}

const LoadingButton = React.forwardRef<HTMLDivElement, LoadingButtonProps>(
    ({ className, loading = false, children, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                'relative inline-flex items-center',
                loading && 'pointer-events-none',
                className
            )}
            {...props}
        >
            {loading && (
                <div className="absolute inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm rounded-lg">
                    <Spinner size="sm" />
                </div>
            )}
            <div className={cn(loading && 'opacity-50')}>{children}</div>
        </div>
    )
);
LoadingButton.displayName = 'LoadingButton';

export interface LoadingOverlayProps extends React.HTMLAttributes<HTMLDivElement> {
    loading: boolean;
    message?: string;
    blur?: boolean;
    children: React.ReactNode;
}

const LoadingOverlay = React.forwardRef<HTMLDivElement, LoadingOverlayProps>(
    ({ className, loading, message, blur = true, children, ...props }, ref) => (
        <div ref={ref} className={cn('relative', className)} {...props}>
            {children}
            {loading && (
                <div
                    className={cn(
                        'absolute inset-0 flex flex-col items-center justify-center space-y-4 bg-background/80 z-50',
                        blur && 'backdrop-blur-sm'
                    )}
                >
                    <Spinner size="lg" />
                    {message && (
                        <p className="text-sm text-muted-foreground">{message}</p>
                    )}
                </div>
            )}
        </div>
    )
);
LoadingOverlay.displayName = 'LoadingOverlay';

export {
    Spinner,
    Shimmer,
    LoadingPage,
    LoadingButton,
    LoadingOverlay,
    spinnerVariants,
};