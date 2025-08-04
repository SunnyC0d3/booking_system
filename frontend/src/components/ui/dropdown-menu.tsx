'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';
import { Check, ChevronRight, Circle } from 'lucide-react';

// Dropdown Context
interface DropdownContextType {
    open: boolean;
    setOpen: (open: boolean) => void;
    onClose: () => void;
}

const DropdownContext = React.createContext<DropdownContextType | null>(null);

const useDropdown = () => {
    const context = React.useContext(DropdownContext);
    if (!context) {
        throw new Error('Dropdown components must be used within a DropdownMenu');
    }
    return context;
};

// Dropdown Menu Root
interface DropdownMenuProps {
    children: React.ReactNode;
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export const DropdownMenu: React.FC<DropdownMenuProps> = ({
                                                              children,
                                                              open: controlledOpen,
                                                              defaultOpen = false,
                                                              onOpenChange,
                                                          }) => {
    const [internalOpen, setInternalOpen] = React.useState(defaultOpen);

    const open = controlledOpen !== undefined ? controlledOpen : internalOpen;

    const setOpen = React.useCallback((newOpen: boolean) => {
        if (controlledOpen === undefined) {
            setInternalOpen(newOpen);
        }
        onOpenChange?.(newOpen);
    }, [controlledOpen, onOpenChange]);

    const onClose = React.useCallback(() => {
        setOpen(false);
    }, [setOpen]);

    const contextValue = React.useMemo(() => ({
        open,
        setOpen,
        onClose,
    }), [open, setOpen, onClose]);

    return (
        <DropdownContext.Provider value={contextValue}>
            <div className="relative inline-block text-left">
                {children}
            </div>
        </DropdownContext.Provider>
    );
};

// Dropdown Trigger
interface DropdownMenuTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
}

export const DropdownMenuTrigger: React.FC<DropdownMenuTriggerProps> = ({
                                                                            children,
                                                                            asChild,
                                                                            onClick,
                                                                            ...props
                                                                        }) => {
    const { open, setOpen } = useDropdown();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        setOpen(!open);
        onClick?.(e);
    };

    if (asChild && React.isValidElement(children)) {
        return React.cloneElement(children, {
            onClick: (e: React.MouseEvent) => {
                handleClick(e as React.MouseEvent<HTMLButtonElement>);
                children.props.onClick?.(e);
            },
        });
    }

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
    const { open, onClose } = useDropdown();
    const contentRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (contentRef.current && !contentRef.current.contains(event.target as Node)) {
                onClose();
            }
        };

        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            document.addEventListener('keydown', handleEscape);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open, onClose]);

    if (!open) return null;

    const alignmentClasses = {
        start: 'left-0',
        center: 'left-1/2 transform -translate-x-1/2',
        end: 'right-0',
    };

    const sideClasses = {
        top: 'bottom-full mb-1',
        right: 'left-full top-0 ml-1',
        bottom: 'top-full mt-1',
        left: 'right-full top-0 mr-1',
    };

    return (
        <div
            ref={contentRef}
            className={cn(
                "absolute z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95",
                sideClasses[side],
                alignmentClasses[align],
                className
            )}
            style={{ marginTop: side === 'bottom' ? sideOffset : undefined }}
            {...props}
        >
            {children}
        </div>
    );
};

// Dropdown Item
interface DropdownMenuItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    inset?: boolean;
    asChild?: boolean;
}

export const DropdownMenuItem: React.FC<DropdownMenuItemProps> = ({
                                                                      className,
                                                                      inset,
                                                                      asChild,
                                                                      onClick,
                                                                      children,
                                                                      ...props
                                                                  }) => {
    const { onClose } = useDropdown();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(e);
        onClose();
    };

    if (asChild && React.isValidElement(children)) {
        return React.cloneElement(children, {
            onClick: (e: React.MouseEvent) => {
                handleClick(e as React.MouseEvent<HTMLButtonElement>);
                children.props.onClick?.(e);
            },
        });
    }

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
    onSelect?: (value: string) => void;
}

export const DropdownMenuRadioItem: React.FC<DropdownMenuRadioItemProps> = ({
                                                                                className,
                                                                                children,
                                                                                value,
                                                                                checked = false,
                                                                                onSelect,
                                                                                onClick,
                                                                                ...props
                                                                            }) => {
    const { onClose } = useDropdown();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onSelect?.(value);
        onClick?.(e);
        onClose();
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
        {checked && <Circle className="h-2 w-2 fill-current" />}
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

// Dropdown Group
interface DropdownMenuGroupProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DropdownMenuGroup: React.FC<DropdownMenuGroupProps> = ({
                                                                        children,
                                                                        ...props
                                                                    }) => (
    <div {...props}>
        {children}
    </div>
);

// Dropdown Sub (simplified)
interface DropdownMenuSubProps {
    children: React.ReactNode;
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export const DropdownMenuSub: React.FC<DropdownMenuSubProps> = ({ children }) => {
    return <>{children}</>;
};

// Dropdown Radio Group
interface DropdownMenuRadioGroupProps extends React.HTMLAttributes<HTMLDivElement> {
    value?: string;
    onValueChange?: (value: string) => void;
}

export const DropdownMenuRadioGroup: React.FC<DropdownMenuRadioGroupProps> = ({
                                                                                  children,
                                                                                  value,
                                                                                  onValueChange,
                                                                                  ...props
                                                                              }) => {
    return (
        <div {...props}>
            {React.Children.map(children, (child) => {
                if (React.isValidElement(child) && child.type === DropdownMenuRadioItem) {
                    return React.cloneElement(child, {
                        checked: child.props.value === value,
                        onSelect: onValueChange,
                    });
                }
                return child;
            })}
        </div>
    );
};

// Dropdown Sub Trigger
interface DropdownMenuSubTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    inset?: boolean;
}

export const DropdownMenuSubTrigger: React.FC<DropdownMenuSubTriggerProps> = ({
                                                                                  className,
                                                                                  inset,
                                                                                  children,
                                                                                  ...props
                                                                              }) => (
    <button
        className={cn(
            "flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent focus:bg-accent",
            inset && "pl-8",
            className
        )}
        {...props}
    >
        {children}
        <ChevronRight className="ml-auto h-4 w-4" />
    </button>
);

// Dropdown Sub Content
interface DropdownMenuSubContentProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DropdownMenuSubContent: React.FC<DropdownMenuSubContentProps> = ({
                                                                                  className,
                                                                                  ...props
                                                                              }) => (
    <div
        className={cn(
            "z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-lg animate-in fade-in-0 zoom-in-95",
            className
        )}
        {...props}
    />
);