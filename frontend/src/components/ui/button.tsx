import * as React from 'react';
import { cn } from '@/lib/cn';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'xl';
    rightIcon?: React.ReactNode;
    leftIcon?: React.ReactNode;
    loading?: boolean;
    fullWidth?: boolean;
}

const LoadingSpinner = React.memo(() => (
    <div className="animate-spin h-4 w-4 border-2 border-current border-t-transparent rounded-full flex-shrink-0" />
));

LoadingSpinner.displayName = 'LoadingSpinner';

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({
         className,
         variant = 'default',
         size = 'default',
         rightIcon,
         leftIcon,
         loading = false,
         fullWidth = false,
         children,
         disabled,
         ...props
     }, ref) => {

        const baseClasses = React.useMemo(() => [
            'btn',
            fullWidth && 'w-full',
        ].filter(Boolean), [fullWidth]);

        const variantClasses = `btn-${variant}`;
        const sizeClasses = size !== 'default' ? `btn-${size}` : '';
        const loadingClasses = loading ? 'btn-loading' : '';

        const hasLeftContent = leftIcon || loading;
        const hasRightContent = rightIcon && !loading;
        const hasTextContent = React.Children.count(children) > 0;

        const buttonContent = React.useMemo(() => {
            return (
                <>
                    {hasLeftContent && (
                        <span className={cn(
                            "flex items-center justify-center flex-shrink-0",
                            hasTextContent && "mr-2"
                        )}>
                            {loading ? <LoadingSpinner /> : leftIcon}
                        </span>
                    )}

                    {hasTextContent && (
                        <span className={cn(
                            "flex-1 text-center",
                            !hasLeftContent && !hasRightContent && "flex items-center justify-center"
                        )}>
                            {children}
                        </span>
                    )}

                    {hasRightContent && (
                        <span className={cn(
                            "flex items-center justify-center flex-shrink-0",
                            hasTextContent && "ml-2"
                        )}>
                            {rightIcon}
                        </span>
                    )}
                </>
            );
        }, [hasLeftContent, hasRightContent, hasTextContent, loading, leftIcon, rightIcon, children]);

        return (
            <button
                className={cn(
                    ...baseClasses,
                    variantClasses,
                    sizeClasses,
                    loadingClasses,
                    'inline-flex items-center justify-center relative',
                    'focus:outline-none focus:ring-2 focus:ring-offset-2',
                    'transition-all duration-200 ease-in-out',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                    hasLeftContent || hasRightContent ? 'flex-row' : 'flex-col',
                    className
                )}
                ref={ref}
                disabled={loading || disabled}
                aria-disabled={loading || disabled}
                {...props}
            >
                {buttonContent}
            </button>
        );
    }
);

Button.displayName = 'Button';

export { Button, type ButtonProps };