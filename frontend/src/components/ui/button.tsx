import * as React from 'react';
import { cn } from '@/lib/cn';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'xl';
    rightIcon?: React.ReactNode;
    leftIcon?: React.ReactNode;
    loading?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({
         className,
         variant = 'default',
         size = 'default',
         rightIcon,
         leftIcon,
         loading,
         children,
         ...props
     }, ref) => {
        const baseClasses = 'btn'; // Uses your existing global CSS classes
        const variantClasses = `btn-${variant}`;
        const sizeClasses = size !== 'default' ? `btn-${size}` : '';
        const loadingClasses = loading ? 'btn-loading' : '';

        return (
            <button
                className={cn(
                    baseClasses,
                    variantClasses,
                    sizeClasses,
                    loadingClasses,
                    className
                )}
                ref={ref}
                disabled={loading || props.disabled}
                {...props}
            >
                {leftIcon && !loading && <span className="mr-2">{leftIcon}</span>}
                {loading && (
                    <div className="animate-spin mr-2 h-4 w-4 border-2 border-current border-t-transparent rounded-full" />
                )}
                {children}
                {rightIcon && !loading && <span className="ml-2">{rightIcon}</span>}
            </button>
        );
    }
);

Button.displayName = 'Button';
export { Button, type ButtonProps };