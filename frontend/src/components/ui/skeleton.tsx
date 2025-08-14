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
        skeletonVariants({ variant: circle ? 'avatar' : variant, speed }),
        circle && 'rounded-full aspect-square',
        rounded && !circle && 'rounded-lg',
        showShimmer && 'relative overflow-hidden',
        className
    );

    const shimmerElement = showShimmer && (
        <div className="absolute inset-0 -translate-x-full animate-[shimmer_2s_infinite] bg-gradient-to-r from-transparent via-white/20 to-transparent" />
    );

    if (lines && lines > 1) {
        return (
            <div className="space-y-2">
                {Array.from({ length: lines }).map((_, index) => (
                    <div
                        key={index}
                        ref={index === 0 ? ref : undefined}
                        className={cn(
                            skeletonClasses,
                            'h-4',
                            index === lines - 1 && 'w-3/4'
                        )}
                        style={index === 0 ? skeletonStyle : undefined}
                        {...(index === 0 ? props : {})}
                    >
                        {shimmerElement}
                    </div>
                ))}
            </div>
        );
    }

    if (count > 1) {
        return (
            <div className="space-y-2">
                {Array.from({ length: count }).map((_, index) => (
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

const SkeletonInput = React.forwardRef<HTMLDivElement, Omit<SkeletonProps, 'variant'>>(
    ({ className, height = 40, ...props }, ref) => (
        <Skeleton
            ref={ref}
            variant="input"
            height={height}
            className={cn('w-full', className)}
            {...props}
        />
    )
);

SkeletonInput.displayName = 'SkeletonInput';

interface SkeletonCardWithContentProps {
    showAvatar?: boolean;
    showButton?: boolean;
    textLines?: number;
    className?: string;
}

const SkeletonCardWithContent: React.FC<SkeletonCardWithContentProps> = ({
                                                                             showAvatar = false,
                                                                             showButton = false,
                                                                             textLines = 3,
                                                                             className
                                                                         }) => (
    <div className={cn('space-y-4 p-4', className)}>
        {showAvatar && (
            <div className="flex items-center space-x-3">
                <SkeletonAvatar />
                <div className="space-y-2 flex-1">
                    <SkeletonText className="w-1/3" />
                    <SkeletonText className="w-1/4" />
                </div>
            </div>
        )}

        <SkeletonCard className="h-32" />

        <div className="space-y-2">
            <SkeletonText lines={textLines} />
        </div>

        {showButton && (
            <div className="flex space-x-2">
                <SkeletonButton />
                <SkeletonButton width={100} />
            </div>
        )}
    </div>
);

SkeletonCardWithContent.displayName = 'SkeletonCardWithContent';

interface SkeletonTableProps {
    rows?: number;
    columns?: number;
    showHeader?: boolean;
    className?: string;
}

const SkeletonTable: React.FC<SkeletonTableProps> = ({
                                                         rows = 5,
                                                         columns = 4,
                                                         showHeader = true,
                                                         className
                                                     }) => (
    <div className={cn('space-y-3', className)}>
        {showHeader && (
            <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
                {Array.from({ length: columns }).map((_, index) => (
                    <SkeletonText key={index} className="h-5 font-medium" />
                ))}
            </div>
        )}

        <div className="space-y-2">
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <div
                    key={rowIndex}
                    className="grid gap-4"
                    style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}
                >
                    {Array.from({ length: columns }).map((_, colIndex) => (
                        <SkeletonText key={colIndex} className="h-4" />
                    ))}
                </div>
            ))}
        </div>
    </div>
);

SkeletonTable.displayName = 'SkeletonTable';

interface SkeletonListProps {
    items?: number;
    showAvatar?: boolean;
    showBadge?: boolean;
    className?: string;
}

const SkeletonList: React.FC<SkeletonListProps> = ({
                                                       items = 5,
                                                       showAvatar = false,
                                                       showBadge = false,
                                                       className
                                                   }) => (
    <div className={cn('space-y-3', className)}>
        {Array.from({ length: items }).map((_, index) => (
            <div key={index} className="flex items-center space-x-3">
                {showAvatar && <SkeletonAvatar width={32} height={32} />}

                <div className="flex-1 space-y-2">
                    <SkeletonText className="w-3/4" />
                    <SkeletonText className="w-1/2 h-3" />
                </div>

                {showBadge && <Skeleton className="h-6 w-16 rounded-full" />}
            </div>
        ))}
    </div>
);

SkeletonList.displayName = 'SkeletonList';

export {
    Skeleton,
    SkeletonText,
    SkeletonAvatar,
    SkeletonButton,
    SkeletonCard,
    SkeletonInput,
    SkeletonCardWithContent,
    SkeletonTable,
    SkeletonList,
    skeletonVariants
};