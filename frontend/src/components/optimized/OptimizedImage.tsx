'use client';

import * as React from 'react';
import Image from 'next/image';
import { cn } from '@/lib/cn';
import { Skeleton } from '@/components/ui/skeleton';
import { AlertCircle, ImageIcon } from 'lucide-react';

interface OptimizedImageProps {
    src: string;
    alt: string;
    width?: number;
    height?: number;
    className?: string;
    fill?: boolean;
    priority?: boolean;
    quality?: number;
    placeholder?: 'blur' | 'empty';
    blurDataURL?: string;
    sizes?: string;
    loading?: 'lazy' | 'eager';
    onLoad?: () => void;
    onError?: () => void;
    fallbackSrc?: string;
    showPlaceholder?: boolean;
    aspectRatio?: 'square' | '4:3' | '16:9' | '3:2' | number;
    objectFit?: 'contain' | 'cover' | 'fill' | 'none' | 'scale-down';
    unoptimized?: boolean;
}

// Generate blur placeholder
function generateBlurDataURL(width: number = 10, height: number = 10): string {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    if (ctx) {
        const gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#f1f5f9');
        gradient.addColorStop(1, '#e2e8f0');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
    }

    return canvas.toDataURL();
}

// Convert aspect ratio to CSS value
function getAspectRatioValue(aspectRatio: OptimizedImageProps['aspectRatio']): string {
    if (typeof aspectRatio === 'number') {
        return aspectRatio.toString();
    }

    switch (aspectRatio) {
        case 'square': return '1';
        case '4:3': return '4/3';
        case '16:9': return '16/9';
        case '3:2': return '3/2';
        default: return 'auto';
    }
}

// Image loading states
type LoadingState = 'loading' | 'loaded' | 'error';

export const OptimizedImage: React.FC<OptimizedImageProps> = ({
                                                                  src,
                                                                  alt,
                                                                  width,
                                                                  height,
                                                                  className,
                                                                  fill = false,
                                                                  priority = false,
                                                                  quality = 85,
                                                                  placeholder = 'blur',
                                                                  blurDataURL,
                                                                  sizes,
                                                                  loading = 'lazy',
                                                                  onLoad,
                                                                  onError,
                                                                  fallbackSrc,
                                                                  showPlaceholder = true,
                                                                  aspectRatio,
                                                                  objectFit = 'cover',
                                                                  unoptimized = false,
                                                              }) => {
    const [loadingState, setLoadingState] = React.useState<LoadingState>('loading');
    const [currentSrc, setCurrentSrc] = React.useState(src);
    const [isIntersecting, setIsIntersecting] = React.useState(false);
    const imgRef = React.useRef<HTMLDivElement>(null);

    // Intersection Observer for lazy loading
    React.useEffect(() => {
        if (priority || loading === 'eager') {
            setIsIntersecting(true);
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsIntersecting(true);
                    observer.disconnect();
                }
            },
            {
                rootMargin: '50px', // Load 50px before entering viewport
                threshold: 0.01,
            }
        );

        if (imgRef.current) {
            observer.observe(imgRef.current);
        }

        return () => observer.disconnect();
    }, [priority, loading]);

    // Handle image load
    const handleLoad = React.useCallback(() => {
        setLoadingState('loaded');
        onLoad?.();
    }, [onLoad]);

    // Handle image error with fallback
    const handleError = React.useCallback(() => {
        if (fallbackSrc && currentSrc !== fallbackSrc) {
            setCurrentSrc(fallbackSrc);
            return;
        }

        setLoadingState('error');
        onError?.();
    }, [fallbackSrc, currentSrc, onError]);

    // Generate blur data URL if not provided
    const getBlurDataURL = React.useMemo(() => {
        if (blurDataURL) return blurDataURL;
        if (placeholder === 'blur' && typeof window !== 'undefined') {
            return generateBlurDataURL(width || 10, height || 10);
        }
        return undefined;
    }, [blurDataURL, placeholder, width, height]);

    // Container styles
    const containerStyles = React.useMemo(() => {
        const styles: React.CSSProperties = {};

        if (aspectRatio && !fill) {
            styles.aspectRatio = getAspectRatioValue(aspectRatio);
        }

        return styles;
    }, [aspectRatio, fill]);

    // Responsive sizes for different breakpoints
    const responsiveSizes = sizes || (
        fill
            ? '100vw'
            : '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw'
    );

    // Loading placeholder
    const LoadingPlaceholder = () => (
        <div className={cn(
            "flex items-center justify-center bg-muted",
            fill ? "absolute inset-0" : "w-full h-full",
            className
        )}>
            {showPlaceholder && (
                <>
                    <ImageIcon className="w-8 h-8 text-muted-foreground/50" />
                    <Skeleton className="absolute inset-0" />
                </>
            )}
        </div>
    );

    // Error placeholder
    const ErrorPlaceholder = () => (
        <div className={cn(
            "flex flex-col items-center justify-center bg-muted text-muted-foreground",
            fill ? "absolute inset-0" : "w-full h-full",
            className
        )}>
            <AlertCircle className="w-8 h-8 mb-2" />
            <span className="text-sm">Failed to load image</span>
        </div>
    );

    // Don't render anything until intersection or priority
    if (!isIntersecting && !priority) {
        return (
            <div
                ref={imgRef}
                className={cn(
                    fill ? "relative" : "relative overflow-hidden",
                    className
                )}
                style={containerStyles}
            >
                <LoadingPlaceholder />
            </div>
        );
    }

    return (
        <div
            ref={imgRef}
            className={cn(
                fill ? "relative" : "relative overflow-hidden",
                className
            )}
            style={containerStyles}
        >
            {loadingState === 'loading' && <LoadingPlaceholder />}
            {loadingState === 'error' && <ErrorPlaceholder />}

            <Image
                src={currentSrc}
                alt={alt}
                width={fill ? undefined : width}
                height={fill ? undefined : height}
                fill={fill}
                priority={priority}
                quality={quality}
                placeholder={placeholder}
                blurDataURL={getBlurDataURL}
                sizes={responsiveSizes}
                unoptimized={unoptimized}
                onLoad={handleLoad}
                onError={handleError}
                className={cn(
                    "transition-opacity duration-300",
                    loadingState === 'loaded' ? 'opacity-100' : 'opacity-0',
                    fill && `object-${objectFit}`
                )}
                style={{
                    objectFit: fill ? objectFit : undefined,
                }}
            />
        </div>
    );
};

// Pre-configured image components for common use cases
export const ProductImage: React.FC<Omit<OptimizedImageProps, 'aspectRatio' | 'objectFit'>> = (props) => (
    <OptimizedImage
        {...props}
        aspectRatio="square"
        objectFit="cover"
        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 25vw"
    />
);

export const HeroImage: React.FC<Omit<OptimizedImageProps, 'aspectRatio' | 'priority'>> = (props) => (
    <OptimizedImage
        {...props}
        aspectRatio="16:9"
        priority={true}
        quality={90}
        sizes="100vw"
    />
);

export const AvatarImage: React.FC<Omit<OptimizedImageProps, 'aspectRatio' | 'objectFit'>> = (props) => (
    <OptimizedImage
        {...props}
        aspectRatio="square"
        objectFit="cover"
        quality={75}
        sizes="(max-width: 768px) 50px, 100px"
    />
);

export const ThumbnailImage: React.FC<Omit<OptimizedImageProps, 'aspectRatio' | 'loading'>> = (props) => (
    <OptimizedImage
        {...props}
        aspectRatio="4:3"
        loading="lazy"
        quality={70}
        sizes="(max-width: 768px) 25vw, 15vw"
    />
);

// Image preloader utility
export class ImagePreloader {
    private static cache = new Set<string>();

    static preload(src: string): Promise<void> {
        if (this.cache.has(src)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const img = new window.Image();

            img.onload = () => {
                this.cache.add(src);
                resolve();
            };

            img.onerror = reject;
            img.src = src;
        });
    }

    static preloadMultiple(sources: string[]): Promise<void[]> {
        return Promise.all(sources.map(src => this.preload(src)));
    }

    static clear(): void {
        this.cache.clear();
    }
}

// Hook for progressive image loading
export function useProgressiveImage(
    lowQualitySrc: string,
    highQualitySrc: string
): { src: string; blur: boolean } {
    const [src, setSrc] = React.useState(lowQualitySrc);
    const [blur, setBlur] = React.useState(true);

    React.useEffect(() => {
        const img = new window.Image();

        img.onload = () => {
            setSrc(highQualitySrc);
            setTimeout(() => setBlur(false), 100); // Small delay for smooth transition
        };

        img.src = highQualitySrc;
    }, [highQualitySrc]);

    return { src, blur };
}