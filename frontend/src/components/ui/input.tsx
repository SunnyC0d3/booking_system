import * as React from 'react';
import { cn } from '@/lib/cn';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    variant?: 'default' | 'sm' | 'lg';
    error?: string;
    label?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    helperText?: string;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({
         className,
         variant = 'default',
         type = 'text',
         error,
         label,
         leftIcon,
         rightIcon,
         helperText,
         disabled,
         ...props
     }, ref) => {

        const inputSizes = {
            sm: 'h-9 px-3 text-sm',
            default: 'h-10 px-3',
            lg: 'h-12 px-4 text-lg'
        };

        const baseInputClasses = cn(
            // Base styles
            'flex w-full rounded-md border border-input bg-background text-foreground transition-all duration-200',
            'placeholder:text-muted-foreground',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
            'disabled:cursor-not-allowed disabled:opacity-50',

            // Size variants
            inputSizes[variant],

            // Error states
            error && 'border-destructive focus-visible:ring-destructive',

            // Icon padding adjustments
            leftIcon && 'pl-10',
            rightIcon && 'pr-10',

            className
        );

        const iconContainerClasses = cn(
            'absolute top-1/2 -translate-y-1/2 flex items-center justify-center text-muted-foreground transition-colors',
            variant === 'sm' ? 'h-4 w-4' : variant === 'lg' ? 'h-6 w-6' : 'h-5 w-5'
        );

        const leftIconClasses = cn(iconContainerClasses, 'left-3');
        const rightIconClasses = cn(iconContainerClasses, 'right-3');

        return (
            <div className="w-full space-y-2">
                {/* Label */}
                {label && (
                    <label className={cn(
                        'block text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
                        error ? 'text-destructive' : 'text-foreground'
                    )}>
                        {label}
                        {props.required && <span className="text-destructive ml-1">*</span>}
                    </label>
                )}

                {/* Input Container */}
                <div className="relative">
                    {/* Left Icon */}
                    {leftIcon && (
                        <div className={leftIconClasses}>
                            {leftIcon}
                        </div>
                    )}

                    {/* Input */}
                    <input
                        type={type}
                        className={baseInputClasses}
                        ref={ref}
                        disabled={disabled}
                        {...props}
                    />

                    {/* Right Icon */}
                    {rightIcon && (
                        <div className={rightIconClasses}>
                            {rightIcon}
                        </div>
                    )}
                </div>

                {/* Helper Text */}
                {helperText && !error && (
                    <p className="text-xs text-muted-foreground">
                        {helperText}
                    </p>
                )}

                {/* Error Message */}
                {error && (
                    <p className="text-xs text-destructive flex items-center gap-1">
                        <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';

export { Input, type InputProps };

// Additional Input variants for specific use cases

// Password Input with built-in toggle
export const PasswordInput = React.forwardRef<HTMLInputElement, Omit<InputProps, 'type' | 'rightIcon'>>(
    ({ ...props }, ref) => {
        const [showPassword, setShowPassword] = React.useState(false);

        return (
            <Input
                ref={ref}
                type={showPassword ? 'text' : 'password'}
                rightIcon={
                    <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="text-muted-foreground hover:text-foreground transition-colors p-1 -m-1"
                        tabIndex={-1}
                    >
                        {showPassword ? (
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        ) : (
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        )}
                    </button>
                }
                {...props}
            />
        );
    }
);

PasswordInput.displayName = 'PasswordInput';

// Search Input with search icon
export const SearchInput = React.forwardRef<HTMLInputElement, Omit<InputProps, 'leftIcon'>>(
    ({ placeholder = "Search...", ...props }, ref) => {
        return (
            <Input
                ref={ref}
                type="search"
                placeholder={placeholder}
                leftIcon={
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                }
                {...props}
            />
        );
    }
);

SearchInput.displayName = 'SearchInput';