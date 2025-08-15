'use client';

import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const skeletonVariants = cva(
    'animate-pulse rounded-md bg-muted',
    {
        variants: {
            variant: {
                default: 'bg-muted',
                card: 'bg-muted/80',
                text: 'bg-muted/60',
                avatar: 'bg-muted rounded-full',
                button: 'bg-muted/70 rounded-md',
                input: 'bg-muted/50 rounded-md',
            },
            speed: {
                slow: 'animate-pulse [animation-duration:2s]',
                default: 'animate-pulse [animation-duration:1.5s]',
                fast: 'animate-pulse [animation-duration:1s]',
            },
        },
        defaultVariants: {
            variant: 'default',
            speed: 'default',
        },
    }
);

export interface SkeletonProps
    extends React.HTMLAttributes<HTMLDivElement>,
        VariantProps<typeof skeletonVariants> {
    width?: string | number;
    height?: string | number;
    rounded?: boolean;
    circle?: boolean;
    count?: number;
    lines?: number;
    showShimmer?: boolean;
}

const Skeleton = React.forwardRef<HTMLDivElement, SkeletonProps>(({
                                                                      className,
                                                                      variant,
                                                                      speed,
                                                                      width,
                                                                      height,
                                                                      rounded = false,
                                                                      circle = false,
                                                                      count = 1,
                                                                      lines,
                                                                      showShimmer = true,
                                                                      style,
                                                                      ...props
                                                                  }, ref) => {
    const skeletonStyle = React.useMemo(() => ({
        width: typeof width === 'number' ? `${width}px` : width,
        height: typeof height === 'number' ? `${height}px` : height,
        ...style,
    }), [width, height, style]);

    const skeletonClasses = cn(
        skeletonVariants({
            variant: circle ? 'avatar' : variant,
            speed
        }),
        rounded && 'rounded-full',
        className
    );

    const shimmerElement = showShimmer ? (
        <div className="absolute inset-0 bg-gradient-to-r from-transparent via-muted-foreground/10 to-transparent animate-shimmer" />
    ) : null;

    if (count > 1 || lines) {
        const itemCount = lines || count;
        return (
            <div className="space-y-2">
                {Array.from({ length: itemCount }).map((_, index) => (
                    <div
                        key={index}
                        ref={index === 0 ? ref : undefined}
                        className={skeletonClasses}
                        style={index === 0 ? skeletonStyle : undefined}
                        {...(index === 0 ? props : {})}
                    >
                        {shimmerElement}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div
            ref={ref}
            className={skeletonClasses}
            style={skeletonStyle}
            {...props}
        >
            {shimmerElement}
        </div>
    );
});

Skeleton.displayName = 'Skeleton';

const SkeletonText = React.forwardRef<HTMLDivElement, Omit<SkeletonProps, 'variant'>>(
    ({ className, ...props }, ref) => (
        <Skeleton
            ref={ref}
            variant="text"
            className={cn('h-4 w-full', className)}
            {...props}
        />
    )
);
SkeletonText.displayName = 'SkeletonText';

const SkeletonAvatar = React.forwardRef<HTMLDivElement, Omit<SkeletonProps, 'variant' | 'circle'>>(
    ({ className, width = 40, height = 40, ...props }, ref) => (
        <Skeleton
            ref={ref}
            variant="avatar"
            circle
            width={width}
            height={height}
            className={className}
            {...props}
        />
    )
);
SkeletonAvatar.displayName = 'SkeletonAvatar';

const SkeletonButton = React.forwardRef<HTMLDivElement, Omit<SkeletonProps, 'variant'>>(
    ({ className, width = 80, height = 36, ...props }, ref) => (
        <Skeleton
            ref={ref}
            variant="button"
            width={width}
            height={height}
            className={className}
            {...props}
        />
    )
);
SkeletonButton.displayName = 'SkeletonButton';

const SkeletonCard = React.forwardRef<HTMLDivElement, Omit<SkeletonProps, 'variant'>>(
    ({ className, ...props }, ref) => (
        <Skeleton
            ref={ref}
            variant="card"
            className={cn('h-48 w-full rounded-lg', className)}
            {...props}
        />
    )
);
SkeletonCard.displayName = 'SkeletonCard';

export {
    Skeleton,
    SkeletonText,
    SkeletonAvatar,
    SkeletonButton,
    SkeletonCard,
    skeletonVariants
};