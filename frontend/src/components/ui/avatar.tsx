'use client'

import * as React from 'react';
import { cn } from '@/lib/cn';

interface AvatarProps extends React.HTMLAttributes<HTMLDivElement> {
    size?: 'sm' | 'md' | 'lg' | 'xl';
}

export const Avatar = React.forwardRef<HTMLDivElement, AvatarProps>(
    ({ className, size = 'md', ...props }, ref) => {
        const sizeClasses = {
            sm: 'h-8 w-8',
            md: 'h-10 w-10',
            lg: 'h-12 w-12',
            xl: 'h-16 w-16',
        };

        return (
            <div
                ref={ref}
                className={cn(
                    "relative flex shrink-0 overflow-hidden rounded-full",
                    sizeClasses[size],
                    className
                )}
                {...props}
            />
        );
    }
);

Avatar.displayName = 'Avatar';

interface AvatarImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    fallback?: string;
}

export const AvatarImage = React.forwardRef<HTMLImageElement, AvatarImageProps>(
    ({ className, alt, fallback, onError, onLoad, ...props }, ref) => {
        const [, setImageLoaded] = React.useState(false);
        const [imageError, setImageError] = React.useState(false);

        const handleLoad = (e: React.SyntheticEvent<HTMLImageElement>) => {
            setImageLoaded(true);
            setImageError(false);
            onLoad?.(e);
        };

        const handleError = (e: React.SyntheticEvent<HTMLImageElement>) => {
            setImageError(true);
            setImageLoaded(false);
            onError?.(e);
        };

        if (imageError && fallback) {
            return (
                <AvatarFallback>
                    {fallback}
                </AvatarFallback>
            );
        }

        return (
            <img
                ref={ref}
                className={cn("aspect-square h-full w-full object-cover", className)}
                alt={alt}
                onLoad={handleLoad}
                onError={handleError}
                {...props}
            />
        );
    }
);

AvatarImage.displayName = 'AvatarImage';

interface AvatarFallbackProps extends React.HTMLAttributes<HTMLDivElement> {}

export const AvatarFallback = React.forwardRef<HTMLDivElement, AvatarFallbackProps>(
    ({ className, children, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                "flex h-full w-full items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground",
                className
            )}
            {...props}
        >
            {children}
        </div>
    )
);

AvatarFallback.displayName = 'AvatarFallback';