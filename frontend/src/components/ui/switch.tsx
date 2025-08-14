'use client';

import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const switchVariants = cva(
    'peer inline-flex shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
                success: 'data-[state=checked]:bg-green-500 data-[state=unchecked]:bg-input',
                warning: 'data-[state=checked]:bg-yellow-500 data-[state=unchecked]:bg-input',
                destructive: 'data-[state=checked]:bg-destructive data-[state=unchecked]:bg-input',
            },
            size: {
                sm: 'h-4 w-7',
                default: 'h-5 w-9',
                lg: 'h-6 w-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

const switchThumbVariants = cva(
    'pointer-events-none block rounded-full bg-background shadow-lg ring-0 transition-transform',
    {
        variants: {
            size: {
                sm: 'h-3 w-3 data-[state=checked]:translate-x-3 data-[state=unchecked]:translate-x-0',
                default: 'h-4 w-4 data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0',
                lg: 'h-5 w-5 data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0',
            },
        },
        defaultVariants: {
            size: 'default',
        },
    }
);

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
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

const descriptionVariants = cva('text-xs mt-1', {
    variants: {
        variant: {
            default: 'text-muted-foreground',
            error: 'text-destructive',
            success: 'text-green-600 dark:text-green-400',
            warning: 'text-yellow-600 dark:text-yellow-400',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

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

const CheckIcon = React.memo(() => (
    <svg
        className="h-3 w-3 text-white"
        fill="currentColor"
        viewBox="0 0 20 20"
        aria-hidden="true"
    >
        <path
            fillRule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clipRule="evenodd"
        />
    </svg>
));

CheckIcon.displayName = 'CheckIcon';

const XIcon = React.memo(() => (
    <svg
        className="h-3 w-3 text-muted-foreground"
        fill="currentColor"
        viewBox="0 0 20 20"
        aria-hidden="true"
    >
        <path
            fillRule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clipRule="evenodd"
        />
    </svg>
));

XIcon.displayName = 'XIcon';

export interface SwitchProps
    extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'value'>,
        VariantProps<typeof switchVariants> {
    checked?: boolean;
    defaultChecked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
    label?: string;
    description?: string;
    required?: boolean;
    optional?: boolean;
    showIcons?: boolean;
    labelPosition?: 'left' | 'right';
    error?: string;
    switchId?: string;
    labelId?: string;
    descriptionId?: string;
}

const Switch = React.forwardRef<HTMLButtonElement, SwitchProps>(({
                                                                     className,
                                                                     variant,
                                                                     size,
                                                                     checked: controlledChecked,
                                                                     defaultChecked = false,
                                                                     onCheckedChange,
                                                                     label,
                                                                     description,
                                                                     required,
                                                                     optional,
                                                                     showIcons = false,
                                                                     labelPosition = 'right',
                                                                     error,
                                                                     disabled,
                                                                     switchId,
                                                                     labelId,
                                                                     descriptionId,
                                                                     onClick,
                                                                     ...props
                                                                 }, ref) => {
    const [internalChecked, setInternalChecked] = React.useState(defaultChecked);

    const isControlled = controlledChecked !== undefined;
    const checked = isControlled ? controlledChecked : internalChecked;

    const generatedSwitchId = React.useId();
    const generatedLabelId = React.useId();
    const generatedDescriptionId = React.useId();

    const finalSwitchId = switchId || generatedSwitchId;
    const finalLabelId = labelId || generatedLabelId;
    const finalDescriptionId = descriptionId || generatedDescriptionId;

    const effectiveVariant = error ? 'destructive' : variant;

    const handleClick = React.useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        if (disabled) return;

        const newChecked = !checked;

        if (!isControlled) {
            setInternalChecked(newChecked);
        }

        onCheckedChange?.(newChecked);
        onClick?.(e);
    }, [checked, disabled, isControlled, onCheckedChange, onClick]);

    const handleKeyDown = React.useCallback((e: React.KeyboardEvent<HTMLButtonElement>) => {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            handleClick(e as any);
        }
    }, [handleClick]);

    const ariaDescribedBy = React.useMemo(() => {
        const ids = [];
        if (description) ids.push(finalDescriptionId);
        return ids.length > 0 ? ids.join(' ') : undefined;
    }, [description, finalDescriptionId]);

    const switchElement = (
        <button
            ref={ref}
            type="button"
            role="switch"
            id={finalSwitchId}
            aria-checked={checked}
            aria-labelledby={label ? finalLabelId : undefined}
            aria-describedby={ariaDescribedBy}
            aria-required={required}
            data-state={checked ? 'checked' : 'unchecked'}
            disabled={disabled}
            className={cn(switchVariants({ variant: effectiveVariant, size }), className)}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
            {...props}
        >
            <span
                data-state={checked ? 'checked' : 'unchecked'}
                className={cn(
                    switchThumbVariants({ size }),
                    'relative flex items-center justify-center'
                )}
            >
                {showIcons && size !== 'sm' && (
                    <span className="absolute inset-0 flex items-center justify-center">
                        {checked ? <CheckIcon /> : <XIcon />}
                    </span>
                )}
            </span>
        </button>
    );

    const labelElement = label && (
        <label
            id={finalLabelId}
            htmlFor={finalSwitchId}
            className={cn(
                labelVariants({ variant: error ? 'error' : 'default' }),
                'cursor-pointer'
            )}
        >
            {label}
            {required && <RequiredIndicator />}
            {optional && !required && <OptionalIndicator />}
        </label>
    );

    const descriptionElement = description && (
        <p
            id={finalDescriptionId}
            className={cn(descriptionVariants({ variant: error ? 'error' : 'default' }))}
        >
            {description}
        </p>
    );

    if (!label && !description) {
        return switchElement;
    }

    return (
        <div className="flex flex-col space-y-2">
            <div className={cn(
                'flex items-center space-x-3',
                labelPosition === 'left' && 'flex-row-reverse space-x-reverse'
            )}>
                {switchElement}
                {labelElement}
            </div>
            {descriptionElement}
            {error && (
                <p className="text-xs text-destructive flex items-center gap-1">
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

Switch.displayName = 'Switch';

export { Switch, switchVariants };