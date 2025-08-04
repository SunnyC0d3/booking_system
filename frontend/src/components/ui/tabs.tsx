'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';

// Tabs Context
interface TabsContextType {
    value: string;
    onValueChange: (value: string) => void;
    orientation?: 'horizontal' | 'vertical';
}

const TabsContext = React.createContext<TabsContextType | null>(null);

const useTabs = () => {
    const context = React.useContext(TabsContext);
    if (!context) {
        throw new Error('Tabs components must be used within a Tabs');
    }
    return context;
};

// Tabs Root
interface TabsProps extends React.HTMLAttributes<HTMLDivElement> {
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    orientation?: 'horizontal' | 'vertical';
}

export const Tabs = React.forwardRef<HTMLDivElement, TabsProps>(
    ({
         value: controlledValue,
         defaultValue = '',
         onValueChange,
         orientation = 'horizontal',
         className,
         children,
         ...props
     }, ref) => {
        const [internalValue, setInternalValue] = React.useState(defaultValue);

        const value = controlledValue !== undefined ? controlledValue : internalValue;

        const handleValueChange = React.useCallback((newValue: string) => {
            if (controlledValue === undefined) {
                setInternalValue(newValue);
            }
            onValueChange?.(newValue);
        }, [controlledValue, onValueChange]);

        const contextValue = React.useMemo(() => ({
            value,
            onValueChange: handleValueChange,
            orientation,
        }), [value, handleValueChange, orientation]);

        return (
            <TabsContext.Provider value={contextValue}>
                <div
                    ref={ref}
                    className={cn(
                        "w-full",
                        orientation === 'vertical' && "flex gap-4",
                        className
                    )}
                    {...props}
                >
                    {children}
                </div>
            </TabsContext.Provider>
        );
    }
);

Tabs.displayName = 'Tabs';

// Tabs List
interface TabsListProps extends React.HTMLAttributes<HTMLDivElement> {}

export const TabsList = React.forwardRef<HTMLDivElement, TabsListProps>(
    ({ className, ...props }, ref) => {
        const { orientation } = useTabs();

        return (
            <div
                ref={ref}
                className={cn(
                    "inline-flex items-center justify-center rounded-md bg-muted p-1 text-muted-foreground",
                    orientation === 'vertical' ? "flex-col h-fit" : "h-10",
                    className
                )}
                role="tablist"
                aria-orientation={orientation}
                {...props}
            />
        );
    }
);

TabsList.displayName = 'TabsList';

// Tabs Trigger
interface TabsTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    value: string;
}

export const TabsTrigger = React.forwardRef<HTMLButtonElement, TabsTriggerProps>(
    ({ className, value: tabValue, onClick, ...props }, ref) => {
        const { value: selectedValue, onValueChange, orientation } = useTabs();
        const isSelected = selectedValue === tabValue;

        const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
            onValueChange(tabValue);
            onClick?.(e);
        };

        return (
            <button
                ref={ref}
                className={cn(
                    "inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all",
                    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                    "disabled:pointer-events-none disabled:opacity-50",
                    isSelected
                        ? "bg-background text-foreground shadow-sm"
                        : "hover:bg-background/50",
                    orientation === 'vertical' && "w-full justify-start",
                    className
                )}
                role="tab"
                aria-selected={isSelected}
                aria-controls={`tabpanel-${tabValue}`}
                id={`tab-${tabValue}`}
                onClick={handleClick}
                {...props}
            />
        );
    }
);

TabsTrigger.displayName = 'TabsTrigger';

// Tabs Content
interface TabsContentProps extends React.HTMLAttributes<HTMLDivElement> {
    value: string;
    forceMount?: boolean;
}

export const TabsContent = React.forwardRef<HTMLDivElement, TabsContentProps>(
    ({ className, value: tabValue, forceMount = false, ...props }, ref) => {
        const { value: selectedValue } = useTabs();
        const isSelected = selectedValue === tabValue;

        if (!isSelected && !forceMount) {
            return null;
        }

        return (
            <div
                ref={ref}
                className={cn(
                    "mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                    !isSelected && forceMount && "hidden",
                    className
                )}
                role="tabpanel"
                aria-labelledby={`tab-${tabValue}`}
                id={`tabpanel-${tabValue}`}
                tabIndex={0}
                {...props}
            />
        );
    }
);

TabsContent.displayName = 'TabsContent';