'use client'

import * as React from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

// Base button classes
const baseClasses = 'inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-medium ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';

// Variant classes
const variantClasses = {
    default: 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-soft hover:shadow-soft-lg',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-soft hover:shadow-soft-lg',
    outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
    secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border',
    ghost: 'hover:bg-accent hover:text-accent-foreground',
    link: 'text-primary underline-offset-4 hover:underline',
    success: 'bg-success text-success-foreground hover:bg-success/90 shadow-soft hover:shadow-soft-lg',
    warning: 'bg-warning text-warning-foreground hover:bg-warning/90 shadow-soft hover:shadow-soft-lg',
    gradient: 'bg-gradient-to-r from-primary to-lavender-500 text-white hover:from-primary/90 hover:to-lavender-500/90 shadow-soft hover:shadow-glow',
} as const;

// Size classes
const sizeClasses = {
    default: 'h-10 px-4 py-2',
    sm: 'h-9 rounded-md px-3 text-xs',
    lg: 'h-11 rounded-lg px-8',
    xl: 'h-12 rounded-lg px-10 text-base',
    icon: 'h-10 w-10',
    'icon-sm': 'h-9 w-9',
    'icon-lg': 'h-11 w-11',
} as const;

// Helper function to get button classes
const getButtonClasses = (
    variant: keyof typeof variantClasses = 'default',
    size: keyof typeof sizeClasses = 'default',
    className?: string
) => {
    return cn(
        baseClasses,
        variantClasses[variant],
        sizeClasses[size],
        className
    );
};

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof variantClasses;
    size?: keyof typeof sizeClasses;
    asChild?: boolean;
    loading?: boolean;
    loadingText?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    (props, ref) => {
        const {
            className,
            variant = 'default',
            size = 'default',
            asChild = false,
            loading = false,
            loadingText,
            leftIcon,
            rightIcon,
            children,
            disabled,
            ...restProps
        } = props;

        // 🚨 NO MORE SLOT! Handle asChild by cloning child element
        if (asChild && React.isValidElement(children)) {
            return React.cloneElement(children, {
                ...restProps,
                className: cn(
                    getButtonClasses(variant, size, className),
                    children.props.className
                ),
                ref,
            } as any);
        }

        // Regular button case
        const isDisabled = disabled || loading;

        return (
            <button
                ref={ref}
                className={cn(
                    getButtonClasses(variant, size, className),
                    loading && 'relative'
                )}
                disabled={isDisabled}
                {...restProps}
            >
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <Loader2 className="h-4 w-4 animate-spin" />
                    </div>
                )}

                <div className={cn('flex items-center gap-2', loading && 'invisible')}>
                    {leftIcon && !loading && leftIcon}
                    {loading && loadingText ? loadingText : children}
                    {rightIcon && !loading && rightIcon}
                </div>
            </button>
        );
    }
);

Button.displayName = 'Button';

export { Button };
export type { ButtonProps };