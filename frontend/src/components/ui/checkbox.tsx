'use client'

import * as React from 'react';
import { Check, Minus } from 'lucide-react';
import { cn } from '@/lib/cn';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
    checked?: boolean;
    defaultChecked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
    indeterminate?: boolean;
    label?: string;
    description?: string;
    error?: string;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
    ({
         className,
         checked: controlledChecked,
         defaultChecked = false,
         onCheckedChange,
         onChange,
         indeterminate = false,
         disabled,
         label,
         description,
         error,
         id,
         ...props
     }, ref) => {
        const [internalChecked, setInternalChecked] = React.useState(defaultChecked);

        // Use controlled or uncontrolled state
        const checked = controlledChecked !== undefined ? controlledChecked : internalChecked;

        const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
            const newChecked = event.target.checked;

            if (controlledChecked === undefined) {
                setInternalChecked(newChecked);
            }

            onCheckedChange?.(newChecked);
            onChange?.(event);
        };

        // Combined ref callback that handles both internal ref and forwarded ref
        const setRefs = React.useCallback((node: HTMLInputElement | null) => {
            // Handle forwarded ref
            if (typeof ref === 'function') {
                ref(node);
            } else if (ref) {
                ref.current = node;
            }
        }, [ref]);

        // Set indeterminate state using the forwarded ref or a separate effect
        React.useEffect(() => {
            const input = typeof ref === 'object' && ref?.current ? ref.current :
                document.getElementById(inputId) as HTMLInputElement;

            if (input) {
                input.indeterminate = indeterminate;
            }
        }, [indeterminate, ref]);

        const inputId = id || React.useId();

        return (
            <div className="flex items-start space-x-2">
                <div className="relative flex items-center">
                    <input
                        ref={setRefs}
                        type="checkbox"
                        id={inputId}
                        checked={checked}
                        onChange={handleChange}
                        disabled={disabled}
                        className="sr-only peer"
                        {...props}
                    />

                    <div
                        className={cn(
                            "h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background transition-colors",
                            "peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2",
                            "peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
                            "peer-checked:bg-primary peer-checked:text-primary-foreground",
                            indeterminate && "bg-primary text-primary-foreground",
                            error && "border-destructive",
                            className
                        )}
                    >
                        <div className="flex items-center justify-center text-current">
                            {indeterminate ? (
                                <Minus className="h-3 w-3" />
                            ) : checked ? (
                                <Check className="h-3 w-3" />
                            ) : null}
                        </div>
                    </div>
                </div>

                {(label || description) && (
                    <div className="grid gap-1.5 leading-none">
                        {label && (
                            <label
                                htmlFor={inputId}
                                className={cn(
                                    "text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
                                    error && "text-destructive"
                                )}
                            >
                                {label}
                            </label>
                        )}
                        {description && (
                            <p className={cn(
                                "text-xs text-muted-foreground",
                                error && "text-destructive"
                            )}>
                                {description}
                            </p>
                        )}
                        {error && (
                            <p className="text-xs text-destructive">{error}</p>
                        )}
                    </div>
                )}
            </div>
        );
    }
);

Checkbox.displayName = 'Checkbox';