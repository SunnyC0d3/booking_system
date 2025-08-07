import * as React from 'react';
import { cn } from '@/lib/cn';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    variant?: 'default' | 'sm' | 'lg';
    error?: string;
    label?: string;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ className, variant = 'default', type = 'text', error, label, ...props }, ref) => {
        const baseClasses = 'input'; // Uses your existing global CSS classes
        const variantClasses = variant !== 'default' ? `input-${variant}` : '';
        const errorClasses = error ? 'border-destructive focus-visible:ring-destructive' : '';

        return (
            <div className="w-full">
                {label && (
                    <label className="block text-sm font-medium text-foreground mb-2">
                        {label}
                    </label>
                )}
                <input
                    type={type}
                    className={cn(
                        baseClasses,
                        variantClasses,
                        errorClasses,
                        className
                    )}
                    ref={ref}
                    {...props}
                />
                {error && (
                    <p className="mt-2 text-sm text-destructive">{error}</p>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';
export { Input, type InputProps };