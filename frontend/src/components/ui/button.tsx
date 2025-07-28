import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

const buttonVariants = cva(
    // Base styles
    'inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-medium ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default:
                    'bg-primary text-primary-foreground hover:bg-primary/90 shadow-soft hover:shadow-soft-lg',
                destructive:
                    'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-soft hover:shadow-soft-lg',
                outline:
                    'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
                secondary:
                    'bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
                success:
                    'bg-success text-success-foreground hover:bg-success/90 shadow-soft hover:shadow-soft-lg',
                warning:
                    'bg-warning text-warning-foreground hover:bg-warning/90 shadow-soft hover:shadow-soft-lg',
                gradient:
                    'bg-gradient-to-r from-primary to-lavender-500 text-white hover:from-primary/90 hover:to-lavender-500/90 shadow-soft hover:shadow-glow',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3 text-xs',
                lg: 'h-11 rounded-lg px-8',
                xl: 'h-12 rounded-lg px-10 text-base',
                icon: 'h-10 w-10',
                'icon-sm': 'h-9 w-9',
                'icon-lg': 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
    loading?: boolean;
    loadingText?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            className,
            variant,
            size,
            asChild = false,
            loading = false,
            loadingText,
            leftIcon,
            rightIcon,
            children,
            disabled,
            ...props
        },
        ref
    ) => {
        const Comp = asChild ? Slot : 'button';

        const isDisabled = disabled || loading;

        return (
            <Comp
                className={cn(
                    buttonVariants({ variant, size, className }),
                    loading && 'relative'
                )}
                ref={ref}
                disabled={isDisabled}
                {...props}
            >
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
            </Comp>
        );
    }
);

Button.displayName = 'Button';

export { Button, buttonVariants };