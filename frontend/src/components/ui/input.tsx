'use client'

import * as React from 'react';
import { Eye, EyeOff, AlertCircle, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/cn';

// Base input classes
const baseClasses = 'flex w-full rounded-lg border bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-all duration-200';

// Variant classes
const variantClasses = {
    default: 'border-input',
    error: 'border-error focus-visible:ring-error',
    success: 'border-success focus-visible:ring-success',
    warning: 'border-warning focus-visible:ring-warning',
} as const;

// Size classes
const sizeClasses = {
    default: 'h-10',
    sm: 'h-9 px-2 text-xs',
    lg: 'h-11 px-4 text-base',
} as const;

// Helper function to get input classes
const getInputClasses = (
    variant: keyof typeof variantClasses = 'default',
    inputSize: keyof typeof sizeClasses = 'default',
    className?: string
) => {
    return cn(
        baseClasses,
        variantClasses[variant],
        sizeClasses[inputSize],
        className
    );
};

export interface InputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'size'> {
    variant?: keyof typeof variantClasses;
    inputSize?: keyof typeof sizeClasses;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    error?: string;
    success?: string;
    helperText?: string;
    label?: string;
    required?: boolean;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
    (props, ref) => {
        const {
            className,
            variant = 'default',
            inputSize = 'default',
            type,
            leftIcon,
            rightIcon,
            error,
            success,
            helperText,
            label,
            required,
            ...restProps
        } = props;

        const [showPassword, setShowPassword] = React.useState(false);
        // Add mounted state to prevent hydration mismatch
        const [isMounted, setIsMounted] = React.useState(false);

        // Only run client-side logic after mount
        React.useEffect(() => {
            setIsMounted(true);
        }, []);

        const inputType = type === 'password' && showPassword ? 'text' : type;

        // Determine variant based on state
        const currentVariant = error ? 'error' : success ? 'success' : variant;

        const inputId = React.useId();

        // Calculate if we need right icons
        const hasRightContent = rightIcon || type === 'password' || error || success;

        return (
            <div className="w-full space-y-2">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="block text-sm font-medium text-foreground"
                    >
                        {label}
                        {required && <span className="ml-1 text-error">*</span>}
                    </label>
                )}

                <div className="relative">
                    {leftIcon && (
                        <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                            {leftIcon}
                        </div>
                    )}

                    <input
                        id={inputId}
                        type={inputType}
                        className={cn(
                            getInputClasses(currentVariant, inputSize, className),
                            leftIcon && 'pl-10',
                            hasRightContent && 'pr-10'
                        )}
                        ref={ref}
                        {...restProps}
                    />

                    {/* Only render right content after mount to prevent hydration issues */}
                    {hasRightContent && isMounted && (
                        <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            {error && (
                                <AlertCircle className="h-4 w-4 text-error" />
                            )}
                            {success && !error && (
                                <CheckCircle2 className="h-4 w-4 text-success" />
                            )}
                            {type === 'password' && (
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-muted-foreground hover:text-foreground transition-colors"
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            )}
                            {rightIcon && !error && !success && type !== 'password' && rightIcon}
                        </div>
                    )}
                </div>

                {(error || success || helperText) && (
                    <div className="space-y-1">
                        {error && (
                            <p className="text-sm text-error flex items-center gap-1">
                                <AlertCircle className="h-3 w-3" />
                                {error}
                            </p>
                        )}
                        {success && !error && (
                            <p className="text-sm text-success flex items-center gap-1">
                                <CheckCircle2 className="h-3 w-3" />
                                {success}
                            </p>
                        )}
                        {helperText && !error && !success && (
                            <p className="text-sm text-muted-foreground">{helperText}</p>
                        )}
                    </div>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';

export { Input };