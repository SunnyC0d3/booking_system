'use client';

import * as React from 'react';
import * as LabelPrimitive from '@radix-ui/react-label';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const labelVariants = cva(
    'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
    {
        variants: {
            variant: {
                default: 'text-gray-900',
                muted: 'text-gray-600',
                error: 'text-red-600',
                success: 'text-green-600',
                warning: 'text-yellow-600',
            },
            size: {
                default: 'text-sm',
                sm: 'text-xs',
                lg: 'text-base',
            },
            weight: {
                normal: 'font-normal',
                medium: 'font-medium',
                semibold: 'font-semibold',
                bold: 'font-bold',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
            weight: 'medium',
        },
    }
);

export interface LabelProps
    extends React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root>,
        VariantProps<typeof labelVariants> {
    required?: boolean;
    optional?: boolean;
    description?: string;
    error?: string;
}

const Label = React.forwardRef<
    React.ElementRef<typeof LabelPrimitive.Root>,
    LabelProps
>(({
       className,
       variant,
       size,
       weight,
       required,
       optional,
       description,
       error,
       children,
       ...props
   }, ref) => {
    const labelVariant = error ? 'error' : variant;

    return (
        <div className="space-y-1">
            <LabelPrimitive.Root
                ref={ref}
                className={cn(labelVariants({ variant: labelVariant, size, weight }), className)}
                {...props}
            >
                {children}
                {required && (
                    <span className="text-red-500 ml-1" aria-label="required">
            *
          </span>
                )}
                {optional && !required && (
                    <span className="text-gray-400 ml-1 font-normal text-xs">
            (optional)
          </span>
                )}
            </LabelPrimitive.Root>

            {description && !error && (
                <p className={cn(
                    'text-xs',
                    variant === 'muted' ? 'text-gray-500' : 'text-gray-600'
                )}>
                    {description}
                </p>
            )}

            {error && (
                <p className="text-xs text-red-600 flex items-center gap-1">
                    <svg
                        className="h-3 w-3 flex-shrink-0"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5l-4 7A1 1 0 006 16h8a1 1 0 00.867-1.5l-4-7A1 1 0 0010 7z"
                            clipRule="evenodd"
                        />
                    </svg>
                    {error}
                </p>
            )}
        </div>
    );
});

Label.displayName = LabelPrimitive.Root.displayName;

export { Label, labelVariants };