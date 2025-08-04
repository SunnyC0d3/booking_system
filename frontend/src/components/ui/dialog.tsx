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

// Dialog Trigger
interface DialogTriggerProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    children: React.ReactNode;
    asChild?: boolean;
}

export const DialogTrigger: React.FC<DialogTriggerProps> = ({
                                                                children,
                                                                asChild,
                                                                onClick,
                                                                ...props
                                                            }) => {
    const { onOpenChange } = useDialog();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onOpenChange(true);
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
    const { open, onOpenChange } = useDialog();
    const contentRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (open && contentRef.current) {
            contentRef.current.focus();
        }
    }, [open]);

    if (!open) return null;

    return (
        <>
            <DialogOverlay />
            <div
                ref={contentRef}
                className={cn(
                    "fixed left-[50%] top-[50%] z-50 grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 border bg-background p-6 shadow-lg duration-200 animate-in fade-in-0 zoom-in-95 slide-in-from-left-1/2 slide-in-from-top-[48%] sm:rounded-lg",
                    className
                )}
                role="dialog"
                aria-modal="true"
                tabIndex={-1}
                {...props}
            >
                {children}
                {showClose && (
                    <DialogClose className="absolute right-4 top-4" />
                )}
            </div>
        </>
    );
};

// Dialog Close
interface DialogCloseProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    asChild?: boolean;
}

export const DialogClose: React.FC<DialogCloseProps> = ({
                                                            children,
                                                            asChild,
                                                            onClick,
                                                            className,
                                                            ...props
                                                        }) => {
    const { onOpenChange } = useDialog();

    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
        onOpenChange(false);
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
        <button
            onClick={handleClick}
            className={cn(
                "rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none",
                className
            )}
            {...props}
        >
            {children || (
                <>
                    <X className="h-4 w-4" />
                    <span className="sr-only">Close</span>
                </>
            )}
        </button>
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
    <h3
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