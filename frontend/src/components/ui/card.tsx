import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const cardVariants = cva(
    'rounded-xl border bg-card text-card-foreground shadow-soft transition-all duration-200',
    {
        variants: {
            variant: {
                default: 'border-border',
                elevated: 'shadow-soft-lg border-border/50',
                outline: 'border-2 border-border shadow-none',
                ghost: 'border-transparent shadow-none bg-transparent',
                gradient: 'bg-gradient-creative border-border/50',
            },
            size: {
                sm: 'p-4',
                default: 'p-6',
                lg: 'p-8',
            },
            hover: {
                none: '',
                lift: 'hover:shadow-soft-lg hover:-translate-y-1 cursor-pointer',
                glow: 'hover:shadow-glow cursor-pointer',
                scale: 'hover:scale-[1.02] cursor-pointer',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
            hover: 'none',
        },
    }
);

export interface CardProps
    extends React.HTMLAttributes<HTMLDivElement>,
        VariantProps<typeof cardVariants> {}

const Card = React.forwardRef<HTMLDivElement, CardProps>(
    ({ className, variant, size, hover, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(cardVariants({ variant, size, hover, className }))}
            {...props}
        />
    )
);
Card.displayName = 'Card';

const CardHeader = React.forwardRef<
    HTMLDivElement,
    React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn('flex flex-col space-y-1.5 pb-6', className)}
        {...props}
    />
));
CardHeader.displayName = 'CardHeader';

const CardTitle = React.forwardRef<
    HTMLParagraphElement,
    React.HTMLAttributes<HTMLHeadingElement>
>(({ className, children, ...props }, ref) => (
    <h3
        ref={ref}
        className={cn(
            'text-2xl font-semibold leading-none tracking-tight',
            className
        )}
        {...props}
    >
        {children}
    </h3>
));
CardTitle.displayName = 'CardTitle';

const CardDescription = React.forwardRef<
    HTMLParagraphElement,
    React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
    <p
        ref={ref}
        className={cn('text-sm text-muted-foreground', className)}
        {...props}
    />
));
CardDescription.displayName = 'CardDescription';

const CardContent = React.forwardRef<
    HTMLDivElement,
    React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
    <div ref={ref} className={cn('pb-6', className)} {...props} />
));
CardContent.displayName = 'CardContent';

const CardFooter = React.forwardRef<
    HTMLDivElement,
    React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn('flex items-center pt-6 border-t', className)}
        {...props}
    />
));
CardFooter.displayName = 'CardFooter';

// Product Card specific component
export interface ProductCardProps extends React.HTMLAttributes<HTMLDivElement> {
    image?: string;
    title: string;
    price: string;
    originalPrice?: string;
    badge?: string;
    onAddToCart?: () => void;
    onQuickView?: () => void;
}

const ProductCard = React.forwardRef<HTMLDivElement, ProductCardProps>(
    (
        {
            className,
            image,
            title,
            price,
            originalPrice,
            badge,
            onAddToCart,
            onQuickView,
            children,
            ...props
        },
        ref
    ) => (
        <Card
            ref={ref}
            variant="default"
            hover="lift"
            className={cn('group overflow-hidden', className)}
            {...props}
        >
            <div className="relative aspect-square overflow-hidden rounded-lg">
                {image ? (
                    <img
                        src={image}
                        alt={title}
                        className="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="w-full h-full bg-muted flex items-center justify-center">
                        <span className="text-muted-foreground">No Image</span>
                    </div>
                )}

                {badge && (
                    <div className="absolute top-2 left-2">
            <span className="badge badge-default text-xs px-2 py-1">
              {badge}
            </span>
                    </div>
                )}

                {/* Quick action buttons */}
                <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center gap-2">
                    {onQuickView && (
                        <button
                            onClick={onQuickView}
                            className="btn btn-secondary btn-sm"
                        >
                            Quick View
                        </button>
                    )}
                </div>
            </div>

            <CardContent className="pt-4 pb-2">
                <CardTitle className="text-lg font-medium line-clamp-2 group-hover:text-primary transition-colors">
                    {title}
                </CardTitle>

                <div className="flex items-center gap-2 mt-2">
                    <span className="text-lg font-semibold text-primary">{price}</span>
                    {originalPrice && (
                        <span className="text-sm text-muted-foreground line-through">
              {originalPrice}
            </span>
                    )}
                </div>

                {children}
            </CardContent>

            {onAddToCart && (
                <CardFooter className="pt-2">
                    <button
                        onClick={onAddToCart}
                        className="btn btn-primary w-full"
                    >
                        Add to Cart
                    </button>
                </CardFooter>
            )}
        </Card>
    )
);
ProductCard.displayName = 'ProductCard';

export {
    Card,
    CardHeader,
    CardFooter,
    CardTitle,
    CardDescription,
    CardContent,
    ProductCard,
    cardVariants,
};