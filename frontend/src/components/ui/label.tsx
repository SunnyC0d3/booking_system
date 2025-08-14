'use client';

import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const labelVariants = cva(
    'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
    {
        variants: {
            variant: {
                default: 'text-foreground',
                muted: 'text-muted-foreground',
                error: 'text-destructive',
                success: 'text-green-600 dark:text-green-400',
                warning: 'text-yellow-600 dark:text-yellow-400',
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

const descriptionVariants = cva('text-xs', {
    variants: {
        variant: {
            default: 'text-muted-foreground',
            muted: 'text-muted-foreground/80',
            error: 'text-destructive',
            success: 'text-green-600 dark:text-green-400',
            warning: 'text-yellow-600 dark:text-yellow-400',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

const ErrorIcon = React.memo(() => (
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
));

ErrorIcon.displayName = 'ErrorIcon';

const RequiredIndicator = React.memo(() => (
    <span className="text-destructive ml-1" aria-label="required field">
        *
    </span>
));

RequiredIndicator.displayName = 'RequiredIndicator';

const OptionalIndicator = React.memo(() => (
    <span className="text-muted-foreground ml-1 font-normal text-xs">
        (optional)
    </span>
));

OptionalIndicator.displayName = 'OptionalIndicator';

export interface LabelProps
    extends React.LabelHTMLAttributes<HTMLLabelElement>,
        VariantProps<typeof labelVariants> {
    required?: boolean;
    optional?: boolean;
    description?: string;
    error?: string;
    descriptionId?: string;
    errorId?: string;
}

const Label = React.forwardRef<HTMLLabelElement, LabelProps>(({
                                                                  className,
                                                                  variant,
                                                                  size,
                                                                  weight,
                                                                  required,
                                                                  optional,
                                                                  description,
                                                                  error,
                                                                  children,
                                                                  descriptionId,
                                                                  errorId,
                                                                  ...props
                                                              }, ref) => {
    const effectiveVariant = error ? 'error' : variant;

    const generatedDescriptionId = React.useId();
    const generatedErrorId = React.useId();

    const finalDescriptionId = descriptionId || generatedDescriptionId;
    const finalErrorId = errorId || generatedErrorId;

    const ariaDescribedBy = React.useMemo(() => {
        const ids = [];
        if (description && !error) ids.push(finalDescriptionId);
        if (error) ids.push(finalErrorId);
        return ids.length > 0 ? ids.join(' ') : undefined;
    }, [description, error, finalDescriptionId, finalErrorId]);

    return (
        <div className="space-y-1">
            <label
                ref={ref}
                className={cn(
                    labelVariants({
                        variant: effectiveVariant,
                        size,
                        weight
                    }),
                    className
                )}
                aria-describedby={ariaDescribedBy}
                {...props}
            >
                {children}
                {required && <RequiredIndicator />}
                {optional && !required && <OptionalIndicator />}
            </label>

            {description && !error && (
                <p
                    id={finalDescriptionId}
                    className={cn(
                        descriptionVariants({
                            variant: variant === 'muted' ? 'muted' : 'default'
                        })
                    )}
                >
                    {description}
                </p>
            )}

            {error && (
                <p
                    id={finalErrorId}
                    className={cn(
                        descriptionVariants({ variant: 'error' }),
                        'flex items-center gap-1'
                    )}
                    role="alert"
                    aria-live="polite"
                >
                    <ErrorIcon />
                    {error}
                </p>
            )}
        </div>
    );
});

Label.displayName = 'Label';

export { Label, labelVariants, descriptionVariants };