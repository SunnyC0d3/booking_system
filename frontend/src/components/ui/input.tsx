import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Eye, EyeOff, AlertCircle, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/cn';

const inputVariants = cva(
    'flex w-full rounded-lg border bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-all duration-200',
    {
        variants: {
            variant: {
                default: 'border-input',
                error: 'border-error focus-visible:ring-error',
                success: 'border-success focus-visible:ring-success',
                warning: 'border-warning focus-visible:ring-warning',
            },
            size: {
                default: 'h-10',
                sm: 'h-9 px-2 text-xs',
                lg: 'h-11 px-4 text-base',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export interface InputProps
    extends React.InputHTMLAttributes<HTMLInputElement>,
        VariantProps<typeof inputVariants> {
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    error?: string;
    success?: string;
    helperText?: string;
    label?: string;
    required?: boolean;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
    (
        {
            className,
            variant,
            size,
            type,
            leftIcon,
            rightIcon,
            error,
            success,
            helperText,
            label,
            required,
            ...props
        },
        ref
    ) => {
        const [showPassword, setShowPassword] = React.useState(false);
        const [isFocused, setIsFocused] = React.useState(false);

        const inputType = type === 'password' && showPassword ? 'text' : type;

        // Determine variant based on state
        const currentVariant = error ? 'error' : success ? 'success' : variant;

        const inputId = React.useId();

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
                            inputVariants({ variant: currentVariant, size, className }),
                            leftIcon && 'pl-10',
                            (rightIcon || type === 'password' || error || success) && 'pr-10'
                        )}
                        ref={ref}
                        onFocus={() => setIsFocused(true)}
                        onBlur={() => setIsFocused(false)}
                        {...props}
                    />

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

export { Input, inputVariants };