'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';
import { Check, ChevronRight } from 'lucide-react';

// Dropdown Context
interface DropdownContextType {
    open: boolean;
    onClose: () => void;
    onOpenChange: (open: boolean) => void;
}

const DropdownContext = React.createContext<DropdownContextType | null>(null);

const useDropdown = () => {
    const context = React.useContext(DropdownContext);
    if (!context) {
        throw new Error('Dropdown components must be used within a DropdownMenu');
    }
    return context;
};

// Dropdown Root
interface DropdownMenuProps {
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
}

export const DropdownMenu: React.FC<DropdownMenuProps> = ({
                                                              open: controlledOpen,
                                                              defaultOpen = false,
                                                              onOpenChange,
                                                              children,
                                                          }) => {
    const [internalOpen, setInternalOpen] = React.useState(defaultOpen);

    const open = controlledOpen !== undefined ? controlledOpen : internalOpen;

    const handleOpenChange = React.useCallback((newOpen: boolean) => {
        if (controlledOpen === undefined) {
            setInternalOpen(newOpen);
        }
        onOpenChange?.(newOpen);
    }, [controlledOpen, onOpenChange]);

    const handleClose = React.useCallback(() => {
        handleOpenChange(false);
    }, [handleOpenChange]);

    React.useEffect(() => {
        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && open) {
                handleClose();
            }
        };

        const handleClickOutside = (e: MouseEvent) => {
            // Close dropdown when clicking outside
            if (open && !(e.target as Element)?.closest('[data-dropdown-content]')) {
                handleClose();
            }
        };

        if (open) {
            document.addEventListener('keydown', handleEscape);
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('keydown', handleEscape);
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [open, handleClose]);

    const contextValue = React.useMemo(() => ({
        open,
        onClose: handleClose,
        onOpenChange: handleOpenChange,
    }), [open, handleClose, handleOpenChange]);

    return (
        <DropdownContext.Provider value={contextValue}>
            <div className="relative">
                {children}
            </div>
        </DropdownContext.Provider>
    );
};

// Dropdown Trigger - SIMPLIFIED, NO ASCHILD
interface DropdownMenuTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    children: React.ReactNode;
}

export const DropdownMenuTrigger: React.FC<DropdownMenuTriggerProps> = ({
                                                                            children,
                                                                            onClick,
                                                                            ...props
                                                                        }) => {
    const { onOpenChange, open } = useDropdown();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onOpenChange(!open);
        onClick?.(e);
    };

    // Simply render the children directly without logic
    return (
        <button onClick={handleClick} {...props}>
            {children}
        </button>
    );
};

// Dropdown Portal (simplified)
interface DropdownMenuPortalProps {
    children: React.ReactNode;
}

export const DropdownMenuPortal: React.FC<DropdownMenuPortalProps> = ({ children }) => {
    return <>{children}</>;
};

// Dropdown Content
interface DropdownMenuContentProps extends React.HTMLAttributes<HTMLDivElement> {
    sideOffset?: number;
    align?: 'start' | 'center' | 'end';
    side?: 'top' | 'right' | 'bottom' | 'left';
}

export const DropdownMenuContent: React.FC<DropdownMenuContentProps> = ({
                                                                            className,
                                                                            sideOffset = 4,
                                                                            align = 'center',
                                                                            side = 'bottom',
                                                                            children,
                                                                            ...props
                                                                        }) => {
    const { open } = useDropdown();

    if (!open) return null;

    return (
        <DropdownMenuPortal>
            <div
                data-dropdown-content
                className={cn(
                    "absolute z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
                    // Position based on side prop
                    side === 'bottom' && 'top-full',
                    side === 'top' && 'bottom-full',
                    side === 'left' && 'right-full top-0',
                    side === 'right' && 'left-full top-0',
                    // Alignment
                    align === 'start' && 'left-0',
                    align === 'center' && 'left-1/2 -translate-x-1/2',
                    align === 'end' && 'right-0',
                    className
                )}
                style={{
                    marginTop: side === 'bottom' ? sideOffset : undefined,
                    marginBottom: side === 'top' ? sideOffset : undefined,
                    marginLeft: side === 'right' ? sideOffset : undefined,
                    marginRight: side === 'left' ? sideOffset : undefined
                }}
                {...props}
            >
                {children}
            </div>
        </DropdownMenuPortal>
    );
};

// Dropdown Item - SIMPLIFIED, NO ASCHILD
interface DropdownMenuItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    inset?: boolean;
}

export const DropdownMenuItem: React.FC<DropdownMenuItemProps> = ({
                                                                      className,
                                                                      inset,
                                                                      onClick,
                                                                      children,
                                                                      ...props
                                                                  }) => {
    const { onClose } = useDropdown();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(e);
        onClose();
    };

    return (
        <button
            className={cn(
                "relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground disabled:pointer-events-none disabled:opacity-50",
                inset && "pl-8",
                className
            )}
            onClick={handleClick}
            {...props}
        >
            {children}
        </button>
    );
};

// Dropdown Checkbox Item
interface DropdownMenuCheckboxItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    checked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
}

export const DropdownMenuCheckboxItem: React.FC<DropdownMenuCheckboxItemProps> = ({
                                                                                      className,
                                                                                      children,
                                                                                      checked = false,
                                                                                      onCheckedChange,
                                                                                      onClick,
                                                                                      ...props
                                                                                  }) => {
    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onCheckedChange?.(!checked);
        onClick?.(e);
    };

    return (
        <button
            className={cn(
                "relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground disabled:pointer-events-none disabled:opacity-50",
                className
            )}
            onClick={handleClick}
            {...props}
        >
            <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                {checked && <Check className="h-4 w-4" />}
            </span>
            {children}
        </button>
    );
};

// Dropdown Radio Item
interface DropdownMenuRadioItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    value: string;
    checked?: boolean;
}

export const DropdownMenuRadioItem: React.FC<DropdownMenuRadioItemProps> = ({
                                                                                className,
                                                                                children,
                                                                                checked = false,
                                                                                ...props
                                                                            }) => {
    return (
        <button
            className={cn(
                "relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground disabled:pointer-events-none disabled:opacity-50",
                className
            )}
            {...props}
        >
            <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                {checked && (
                    <div className="h-2 w-2 rounded-full bg-current" />
                )}
            </span>
            {children}
        </button>
    );
};

// Dropdown Label
interface DropdownMenuLabelProps extends React.HTMLAttributes<HTMLDivElement> {
    inset?: boolean;
}

export const DropdownMenuLabel: React.FC<DropdownMenuLabelProps> = ({
                                                                        className,
                                                                        inset,
                                                                        ...props
                                                                    }) => (
    <div
        className={cn(
            "px-2 py-1.5 text-sm font-semibold",
            inset && "pl-8",
            className
        )}
        {...props}
    />
);

// Dropdown Separator
interface DropdownMenuSeparatorProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DropdownMenuSeparator: React.FC<DropdownMenuSeparatorProps> = ({
                                                                                className,
                                                                                ...props
                                                                            }) => (
    <div
        className={cn("-mx-1 my-1 h-px bg-muted", className)}
        {...props}
    />
);

// Dropdown Shortcut
interface DropdownMenuShortcutProps extends React.HTMLAttributes<HTMLSpanElement> {}

export const DropdownMenuShortcut: React.FC<DropdownMenuShortcutProps> = ({
                                                                              className,
                                                                              ...props
                                                                          }) => {
    return (
        <span
            className={cn("ml-auto text-xs tracking-widest opacity-60", className)}
            {...props}
        />
    );
};

// Sub menu components (simplified)
export const DropdownMenuSub: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <div>{children}</div>
);

export const DropdownMenuSubTrigger: React.FC<DropdownMenuItemProps> = ({
                                                                            className,
                                                                            inset,
                                                                            children,
                                                                            ...props
                                                                        }) => (
    <button
        className={cn(
            "flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent",
            inset && "pl-8",
            className
        )}
        {...props}
    >
        {children}
        <ChevronRight className="ml-auto h-4 w-4" />
    </button>
);

export const DropdownMenuSubContent = DropdownMenuContent;

// Radio Group (simplified)
export const DropdownMenuRadioGroup: React.FC<{
    children: React.ReactNode;
    value?: string;
    onValueChange?: (value: string) => void;
}> = ({ children }) => (
    <div>{children}</div>
);