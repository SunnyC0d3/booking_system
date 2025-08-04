'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';
import { ChevronDown, Check, ChevronUp } from 'lucide-react';

// Base Select Option Interface
export interface SelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

// Main Select Props
export interface SelectProps {
    options: SelectOption[];
    value?: string;
    defaultValue?: string;
    placeholder?: string;
    onChange?: (value: string) => void;
    onValueChange?: (value: string) => void; // Alternative name for compatibility
    disabled?: boolean;
    className?: string;
    name?: string;
    required?: boolean;
}

// Individual Component Props
export interface SelectTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    children: React.ReactNode;
    placeholder?: string;
}

export interface SelectContentProps {
    children: React.ReactNode;
    className?: string;
    position?: 'item-aligned' | 'popper';
}

export interface SelectItemProps {
    value: string;
    children: React.ReactNode;
    disabled?: boolean;
    className?: string;
}

export interface SelectValueProps {
    placeholder?: string;
    className?: string;
}

export interface SelectLabelProps {
    children: React.ReactNode;
    className?: string;
}

export interface SelectSeparatorProps {
    className?: string;
}

export interface SelectGroupProps {
    children: React.ReactNode;
    className?: string;
}

// Context for Select State
interface SelectContextType {
    value: string;
    onValueChange: (value: string) => void;
    isOpen: boolean;
    setIsOpen: (open: boolean) => void;
    placeholder?: string;
    disabled?: boolean;
}

const SelectContext = React.createContext<SelectContextType | null>(null);

const useSelectContext = () => {
    const context = React.useContext(SelectContext);
    if (!context) {
        throw new Error('Select components must be used within a Select');
    }
    return context;
};

// Main Select Root Component
export const Select: React.FC<{
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    disabled?: boolean;
    children: React.ReactNode;
}> = ({
          value: controlledValue,
          defaultValue = '',
          onValueChange,
          disabled = false,
          children
      }) => {
    const [internalValue, setInternalValue] = React.useState(defaultValue);
    const [isOpen, setIsOpen] = React.useState(false);

    const value = controlledValue !== undefined ? controlledValue : internalValue;

    const handleValueChange = React.useCallback((newValue: string) => {
        if (controlledValue === undefined) {
            setInternalValue(newValue);
        }
        onValueChange?.(newValue);
        setIsOpen(false);
    }, [controlledValue, onValueChange]);

    const contextValue = React.useMemo(() => ({
        value,
        onValueChange: handleValueChange,
        isOpen,
        setIsOpen,
        disabled,
    }), [value, handleValueChange, isOpen, disabled]);

    return (
        <SelectContext.Provider value={contextValue}>
            <div className="relative">
                {children}
            </div>
        </SelectContext.Provider>
    );
};

// Select Trigger
export const SelectTrigger = React.forwardRef<HTMLButtonElement, SelectTriggerProps>(
    ({ className, children, ...props }, ref) => {
        const { isOpen, setIsOpen, disabled } = useSelectContext();

        return (
            <button
                ref={ref}
                type="button"
                className={cn(
                    "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
                    className
                )}
                onClick={() => !disabled && setIsOpen(!isOpen)}
                disabled={disabled}
                aria-expanded={isOpen}
                aria-haspopup="listbox"
                {...props}
            >
                {children}
                <ChevronDown className="h-4 w-4 opacity-50" />
            </button>
        );
    }
);
SelectTrigger.displayName = 'SelectTrigger';

// Select Value Display
export const SelectValue: React.FC<SelectValueProps> = ({
                                                            placeholder = "Select an option...",
                                                            className
                                                        }) => {
    const { value } = useSelectContext();

    // This will be populated by SelectItem components
    const [displayValue, setDisplayValue] = React.useState<string>('');

    React.useEffect(() => {
        // Find the display value from the DOM or context
        // This is a simplified version - in practice, you might want to pass options through context
        setDisplayValue(value || '');
    }, [value]);

    return (
        <span className={cn("block truncate", className)}>
      {displayValue || placeholder}
    </span>
    );
};
SelectValue.displayName = 'SelectValue';

// Select Content (Dropdown)
export const SelectContent = React.forwardRef<HTMLDivElement, SelectContentProps>(
    ({ children, className, position = 'item-aligned', ...props }, ref) => {
        const { isOpen, setIsOpen } = useSelectContext();
        const contentRef = React.useRef<HTMLDivElement>(null);

        React.useEffect(() => {
            const handleClickOutside = (event: MouseEvent) => {
                if (contentRef.current && !contentRef.current.contains(event.target as Node)) {
                    setIsOpen(false);
                }
            };

            const handleEscape = (event: KeyboardEvent) => {
                if (event.key === 'Escape') {
                    setIsOpen(false);
                }
            };

            if (isOpen) {
                document.addEventListener('mousedown', handleClickOutside);
                document.addEventListener('keydown', handleEscape);
            }

            return () => {
                document.removeEventListener('mousedown', handleClickOutside);
                document.removeEventListener('keydown', handleEscape);
            };
        }, [isOpen, setIsOpen]);

        if (!isOpen) return null;

        return (
            <div
                ref={contentRef}
                className={cn(
                    "absolute z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95 slide-in-from-top-2",
                    "w-full mt-1",
                    className
                )}
                role="listbox"
                {...props}
            >
                <div className="max-h-60 overflow-auto p-1">
                    {children}
                </div>
            </div>
        );
    }
);
SelectContent.displayName = 'SelectContent';

// Select Item
export const SelectItem = React.forwardRef<HTMLDivElement, SelectItemProps>(
    ({ value: itemValue, children, disabled = false, className, ...props }, ref) => {
        const { value: selectedValue, onValueChange } = useSelectContext();
        const isSelected = selectedValue === itemValue;

        const handleClick = () => {
            if (!disabled) {
                onValueChange(itemValue);
            }
        };

        return (
            <div
                ref={ref}
                className={cn(
                    "relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground",
                    disabled && "pointer-events-none opacity-50",
                    isSelected && "bg-accent text-accent-foreground",
                    className
                )}
                onClick={handleClick}
                role="option"
                aria-selected={isSelected}
                {...props}
            >
        <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
          {isSelected && <Check className="h-4 w-4" />}
        </span>
                <span className="truncate">{children}</span>
            </div>
        );
    }
);
SelectItem.displayName = 'SelectItem';

// Select Label
export const SelectLabel: React.FC<SelectLabelProps> = ({ children, className }) => (
    <div className={cn("py-1.5 pl-8 pr-2 text-sm font-semibold", className)}>
        {children}
    </div>
);
SelectLabel.displayName = 'SelectLabel';

// Select Separator
export const SelectSeparator: React.FC<SelectSeparatorProps> = ({ className }) => (
    <div className={cn("-mx-1 my-1 h-px bg-muted", className)} />
);
SelectSeparator.displayName = 'SelectSeparator';

// Select Group
export const SelectGroup: React.FC<SelectGroupProps> = ({ children, className }) => (
    <div className={cn("", className)}>
        {children}
    </div>
);
SelectGroup.displayName = 'SelectGroup';

// Scroll Buttons (optional, for large lists)
export const SelectScrollUpButton: React.FC<{ className?: string }> = ({ className }) => (
    <div className={cn("flex cursor-default items-center justify-center py-1", className)}>
        <ChevronUp className="h-4 w-4" />
    </div>
);
SelectScrollUpButton.displayName = 'SelectScrollUpButton';

export const SelectScrollDownButton: React.FC<{ className?: string }> = ({ className }) => (
    <div className={cn("flex cursor-default items-center justify-center py-1", className)}>
        <ChevronDown className="h-4 w-4" />
    </div>
);
SelectScrollDownButton.displayName = 'SelectScrollDownButton';

// Simple Select Component (for easier use)
export const SimpleSelect: React.FC<SelectProps> = ({
                                                        options,
                                                        value,
                                                        defaultValue,
                                                        placeholder,
                                                        onChange,
                                                        onValueChange,
                                                        disabled,
                                                        className,
                                                        name,
                                                        required,
                                                        ...props
                                                    }) => {
    const handleValueChange = React.useCallback((newValue: string) => {
        onChange?.(newValue);
        onValueChange?.(newValue);
    }, [onChange, onValueChange]);

    const selectedOption = options.find(option => option.value === (value || defaultValue));

    return (
        <Select
            value={value}
            defaultValue={defaultValue}
            onValueChange={handleValueChange}
            disabled={disabled}
        >
            <SelectTrigger className={className} {...props}>
                <SelectValue
                    placeholder={selectedOption?.label || placeholder}
                />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem
                        key={option.value}
                        value={option.value}
                        disabled={option.disabled}
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
            {name && (
                <input
                    type="hidden"
                    name={name}
                    value={value || defaultValue || ''}
                    required={required}
                />
            )}
        </Select>
    );
};
SimpleSelect.displayName = 'SimpleSelect';