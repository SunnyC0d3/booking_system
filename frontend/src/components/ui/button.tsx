'use client'

import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import Link from 'next/link';
import { cn } from '@/lib/cn';

const buttonVariants = cva(
    "inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
    {
        variants: {
            variant: {
                default: "bg-primary text-primary-foreground hover:bg-primary/90",
                destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
                outline: "border border-input bg-background hover:bg-accent hover:text-accent-foreground",
                secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
                ghost: "hover:bg-accent hover:text-accent-foreground",
                link: "text-primary underline-offset-4 hover:underline",
            },
            size: {
                default: "h-10 px-4 py-2",
                sm: "h-9 rounded-md px-3",
                lg: "h-11 rounded-md px-8",
                icon: "h-10 w-10",
            },
        },
        defaultVariants: {
            variant: "default",
            size: "default",
        },
    }
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    loading?: boolean;
    loadingText?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    // Link props - will render as Next.js Link instead of button
    href?: string;
    target?: string;
    rel?: string;
    replace?: boolean;
    scroll?: boolean;
    prefetch?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({
         className,
         variant,
         size,
         loading = false,
         loadingText,
         leftIcon,
         rightIcon,
         children,
         disabled,
         href,
         target,
         rel,
         replace,
         scroll,
         prefetch,
         ...props
     }, ref) => {
        const isDisabled = disabled || loading;
        const baseClassName = cn(buttonVariants({ variant, size, className }), loading && 'relative');

        // Content to render inside button/link
        const content = (
            <>
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <Loader2 className="h-4 w-4 animate-spin" />
                    </div>
                )}
                <div className={cn('flex items-center gap-2', loading && 'invisible')}>
                    {leftIcon && !loading && leftIcon}
                    {loading && loadingText ? loadingText : children}
                    {rightIcon && !loading && rightIcon}
                </div>
            </>
        );

        // If href is provided, render as link
        if (href) {
            const isExternal = href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:');

            if (isExternal) {
                return (
                    <a
                        href={href}
                        target={target}
                        rel={rel}
                        className={cn(baseClassName, 'no-underline')}
                    >
                        {content}
                    </a>
                );
            } else {
                // Create link props object with proper types
                const linkProps: React.ComponentProps<typeof Link> = {
                    href,
                    className: cn(baseClassName, 'no-underline'),
                    children: content,
                };

                // Only add optional props if they're not undefined
                if (replace !== undefined) linkProps.replace = replace;
                if (scroll !== undefined) linkProps.scroll = scroll;
                if (prefetch !== undefined) linkProps.prefetch = prefetch;

                return <Link {...linkProps} />;
            }
        }

        // Default button rendering
        return (
            <button
                className={baseClassName}
                ref={ref}
                disabled={isDisabled}
                {...props}
            >
                {content}
            </button>
        );
    }
);

Button.displayName = "Button";

export { Button, buttonVariants };