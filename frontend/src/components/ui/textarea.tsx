'use client';

import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const textareaVariants = cva(
    'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 resize-none',
    {
        variants: {
            variant: {
                default: 'border-input',
                error: 'border-destructive focus-visible:ring-destructive',
                success: 'border-green-500 focus-visible:ring-green-500',
                warning: 'border-yellow-500 focus-visible:ring-yellow-500',
            },
            size: {
                sm: 'min-h-[60px] text-xs px-2 py-1',
                default: 'min-h-[80px] text-sm px-3 py-2',
                lg: 'min-h-[120px] text-base px-4 py-3',
            },
            resize: {
                none: 'resize-none',
                vertical: 'resize-y',
                horizontal: 'resize-x',
                both: 'resize',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
            resize: 'vertical',
        },
    }
);

const labelVariants = cva(
    'block text-sm font-medium leading-none mb-2',
    {
        variants: {
            variant: {
                default: 'text-foreground',
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

const helperTextVariants = cva(
    'text-xs mt-2',
    {
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
    }
);

const CharacterCounter = React.memo(({
                                         current,
                                         max,
                                         variant = 'default'
                                     }: {
    current: number;
    max?: number;
    variant?: 'default' | 'warning' | 'error';
}) => {
    if (!max) return null;

    const isNearLimit = current > max * 0.8;
    const isOverLimit = current > max;

    const counterVariant = isOverLimit ? 'error' : isNearLimit ? 'warning' : variant;

    return (
        <span className={cn(
            'text-xs tabular-nums',
            counterVariant === 'error' && 'text-destructive',
            counterVariant === 'warning' && 'text-yellow-600 dark:text-yellow-400',
            counterVariant === 'default' && 'text-muted-foreground'
        )}>
            {current}{max && `/${max}`}
        </span>
    );
});

CharacterCounter.displayName = 'CharacterCounter';

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

export interface TextareaProps
    extends Omit<React.TextareaHTMLAttributes<HTMLTextAreaElement>, 'resize'>,
        VariantProps<typeof textareaVariants> {
    label?: string;
    error?: string;
    helperText?: string;
    required?: boolean;
    optional?: boolean;
    maxLength?: number;
    showCharacterCount?: boolean;
    autoResize?: boolean;
    minRows?: number;
    maxRows?: number;
    labelId?: string;
    errorId?: string;
    helperTextId?: string;
}

const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(({
                                                                           className,
                                                                           variant,
                                                                           size,
                                                                           resize,
                                                                           label,
                                                                           error,
                                                                           helperText,
                                                                           required,
                                                                           optional,
                                                                           maxLength,
                                                                           showCharacterCount = false,
                                                                           autoResize = false,
                                                                           minRows = 3,
                                                                           maxRows,
                                                                           value,
                                                                           onChange,
                                                                           labelId,
                                                                           errorId,
                                                                           helperTextId,
                                                                           ...props
                                                                       }, ref) => {
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);
    const [currentLength, setCurrentLength] = React.useState(0);

    React.useImperativeHandle(ref, () => textareaRef.current!);

    const generatedLabelId = React.useId();
    const generatedErrorId = React.useId();
    const generatedHelperTextId = React.useId();

    const finalLabelId = labelId || generatedLabelId;
    const finalErrorId = errorId || generatedErrorId;
    const finalHelperTextId = helperTextId || generatedHelperTextId;

    const effectiveVariant = error ? 'error' : variant;

    const adjustHeight = React.useCallback(() => {
        const textarea = textareaRef.current;
        if (!textarea || !autoResize) return;

        textarea.style.height = 'auto';
        const scrollHeight = textarea.scrollHeight;

        const lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight) || 20;
        const minHeight = minRows * lineHeight;
        const maxHeight = maxRows ? maxRows * lineHeight : Infinity;

        const newHeight = Math.min(Math.max(scrollHeight, minHeight), maxHeight);
        textarea.style.height = `${newHeight}px`;
    }, [autoResize, minRows, maxRows]);

    const handleChange = React.useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
        const newValue = e.target.value;

        setCurrentLength(newValue.length);

        if (autoResize) {
            adjustHeight();
        }

        onChange?.(e);
    }, [onChange, autoResize, adjustHeight]);

    React.useEffect(() => {
        if (typeof value === 'string') {
            setCurrentLength(value.length);
        }
    }, [value]);

    React.useEffect(() => {
        if (autoResize) {
            adjustHeight();
        }
    }, [adjustHeight, value]);

    const ariaDescribedBy = React.useMemo(() => {
        const ids = [];
        if (helperText && !error) ids.push(finalHelperTextId);
        if (error) ids.push(finalErrorId);
        return ids.length > 0 ? ids.join(' ') : undefined;
    }, [helperText, error, finalHelperTextId, finalErrorId]);

    return (
        <div className="w-full space-y-2">
            {label && (
                <label
                    id={finalLabelId}
                    htmlFor={props.id}
                    className={cn(labelVariants({ variant: effectiveVariant }))}
                >
                    {label}
                    {required && <RequiredIndicator />}
                    {optional && !required && <OptionalIndicator />}
                </label>
            )}

            <div className="relative">
                <textarea
                    ref={textareaRef}
                    className={cn(
                        textareaVariants({ variant: effectiveVariant, size, resize }),
                        autoResize && 'resize-none overflow-hidden',
                        className
                    )}
                    value={value}
                    onChange={handleChange}
                    maxLength={maxLength}
                    aria-labelledby={label ? finalLabelId : undefined}
                    aria-describedby={ariaDescribedBy}
                    aria-invalid={!!error}
                    rows={autoResize ? minRows : props.rows}
                    {...props}
                />

                {(showCharacterCount || maxLength) && (
                    <div className="absolute bottom-2 right-2 pointer-events-none">
                        <CharacterCounter
                            current={currentLength}
                            max={maxLength}
                            variant={effectiveVariant}
                        />
                    </div>
                )}
            </div>

            {helperText && !error && (
                <p
                    id={finalHelperTextId}
                    className={cn(helperTextVariants({ variant: 'default' }))}
                >
                    {helperText}
                </p>
            )}

            {error && (
                <p
                    id={finalErrorId}
                    className={cn(
                        helperTextVariants({ variant: 'error' }),
                        'flex items-center gap-1'
                    )}
                    role="alert"
                    aria-live="polite"
                >
                    <ErrorIcon />
                    {error}
                </p>
            )}

            {(showCharacterCount || maxLength) && size !== 'sm' && (
                <div className="flex justify-end">
                    <CharacterCounter
                        current={currentLength}
                        max={maxLength}
                        variant={effectiveVariant}
                    />
                </div>
            )}
        </div>
    );
});

Textarea.displayName = 'Textarea';

export { Textarea, textareaVariants };