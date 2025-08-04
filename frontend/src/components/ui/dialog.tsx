'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';
import { X } from 'lucide-react';
import { Button } from './button';

// Dialog Context
interface DialogContextType {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

const DialogContext = React.createContext<DialogContextType | null>(null);

const useDialog = () => {
    const context = React.useContext(DialogContext);
    if (!context) {
        throw new Error('Dialog components must be used within a Dialog');
    }
    return context;
};

// Dialog Root
interface DialogProps {
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
    children: React.ReactNode;
}

export const Dialog: React.FC<DialogProps> = ({
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

    React.useEffect(() => {
        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && open) {
                handleOpenChange(false);
            }
        };

        if (open) {
            document.addEventListener('keydown', handleEscape);
            // Prevent body scroll when dialog is open
            document.body.style.overflow = 'hidden';
        }

        return () => {
            document.removeEventListener('keydown', handleEscape);
            document.body.style.overflow = 'unset';
        };
    }, [open, handleOpenChange]);

    const contextValue = React.useMemo(() => ({
        open,
        onOpenChange: handleOpenChange,
    }), [open, handleOpenChange]);

    return (
        <DialogContext.Provider value={contextValue}>
            {children}
        </DialogContext.Provider>
    );
};

// Dialog Trigger - SIMPLIFIED, NO ASCHILD
interface DialogTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    children: React.ReactNode;
}

export const DialogTrigger: React.FC<DialogTriggerProps> = ({
                                                                children,
                                                                onClick,
                                                                ...props
                                                            }) => {
    const { onOpenChange } = useDialog();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onOpenChange(true);
        onClick?.(e);
    };

    // Simply render the children directly without logic
    return (
        <button onClick={handleClick} {...props}>
            {children}
        </button>
    );
};

// Dialog Portal (simplified - just renders children)
interface DialogPortalProps {
    children: React.ReactNode;
    container?: HTMLElement;
}

export const DialogPortal: React.FC<DialogPortalProps> = ({ children }) => {
    return <>{children}</>;
};

// Dialog Overlay
interface DialogOverlayProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DialogOverlay: React.FC<DialogOverlayProps> = ({
                                                                className,
                                                                onClick,
                                                                ...props
                                                            }) => {
    const { onOpenChange } = useDialog();

    const handleClick = (e: React.MouseEvent<HTMLDivElement>) => {
        onOpenChange(false);
        onClick?.(e);
    };

    return (
        <div
            className={cn(
                "fixed inset-0 z-50 bg-black/50 backdrop-blur-sm animate-in fade-in-0",
                className
            )}
            onClick={handleClick}
            {...props}
        />
    );
};

// Dialog Content
interface DialogContentProps extends React.HTMLAttributes<HTMLDivElement> {
    children: React.ReactNode;
    showClose?: boolean;
}

export const DialogContent: React.FC<DialogContentProps> = ({
                                                                className,
                                                                children,
                                                                showClose = true,
                                                                ...props
                                                            }) => {
    const { onOpenChange, open } = useDialog();

    if (!open) return null;

    return (
        <DialogPortal>
            <DialogOverlay />
            <div
                className={cn(
                    "fixed left-[50%] top-[50%] z-50 grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 border bg-background p-6 shadow-lg duration-200 sm:rounded-lg animate-in fade-in-0 zoom-in-95 slide-in-from-left-1/2 slide-in-from-top-[48%]",
                    className
                )}
                {...props}
            >
                {children}
                {showClose && (
                    <button
                        className="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none"
                        onClick={() => onOpenChange(false)}
                    >
                        <X className="h-4 w-4" />
                        <span className="sr-only">Close</span>
                    </button>
                )}
            </div>
        </DialogPortal>
    );
};

// Dialog Header
interface DialogHeaderProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DialogHeader: React.FC<DialogHeaderProps> = ({
                                                              className,
                                                              ...props
                                                          }) => (
    <div
        className={cn(
            "flex flex-col space-y-1.5 text-center sm:text-left",
            className
        )}
        {...props}
    />
);

// Dialog Footer
interface DialogFooterProps extends React.HTMLAttributes<HTMLDivElement> {}

export const DialogFooter: React.FC<DialogFooterProps> = ({
                                                              className,
                                                              ...props
                                                          }) => (
    <div
        className={cn(
            "flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2",
            className
        )}
        {...props}
    />
);

// Dialog Title
interface DialogTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {}

export const DialogTitle: React.FC<DialogTitleProps> = ({
                                                            className,
                                                            ...props
                                                        }) => (
    <h1
        className={cn(
            "text-lg font-semibold leading-none tracking-tight",
            className
        )}
        {...props}
    />
);

// Dialog Description
interface DialogDescriptionProps extends React.HTMLAttributes<HTMLParagraphElement> {}

export const DialogDescription: React.FC<DialogDescriptionProps> = ({
                                                                        className,
                                                                        ...props
                                                                    }) => (
    <p
        className={cn("text-sm text-muted-foreground", className)}
        {...props}
    />
);