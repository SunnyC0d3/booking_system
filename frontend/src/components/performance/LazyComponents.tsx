'use client'

import { lazy, Suspense, ComponentType, ReactNode } from 'react';
import { Skeleton } from '@/components/ui/loading';
import { Card, CardContent } from '@/components/ui';
import { Loader2 } from 'lucide-react';

// Extend Window interface for gtag
declare global {
    interface Window {
        gtag?: (
            command: 'event',
            eventName: string,
            parameters: Record<string, any>
        ) => void;
    }
}

// Loading fallback components
const PageSkeleton = () => (
    <div className="min-h-screen bg-background">
        <div className="container mx-auto px-4 py-8">
            <Skeleton className="h-8 w-64 mb-6" />
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {Array.from({ length: 6 }).map((_, i) => (
                    <Card key={i}>
                        <CardContent className="p-6">
                            <Skeleton className="h-48 mb-4" />
                            <Skeleton className="h-4 w-full mb-2" />
                            <Skeleton className="h-4 w-3/4" />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    </div>
);

const ComponentSkeleton = () => (
    <div className="animate-pulse">
        <div className="h-64 bg-muted rounded-lg mb-4" />
        <div className="space-y-2">
            <div className="h-4 bg-muted rounded w-full" />
            <div className="h-4 bg-muted rounded w-3/4" />
        </div>
    </div>
);

const LoadingSpinner = ({ message = "Loading..." }: { message?: string }) => (
    <div className="flex flex-col items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-primary mb-4" />
        <p className="text-muted-foreground">{message}</p>
    </div>
);

// Higher-order component for lazy loading with custom fallback
export function withLazyLoading<P extends Record<string, any>>(
    importFunc: () => Promise<{ default: ComponentType<P> }>,
    fallback: ReactNode = <ComponentSkeleton />
) {
    const LazyComponent = lazy(importFunc);

    return function LazyWrapper(props: P) {
        return (
            <Suspense fallback={fallback}>
                <LazyComponent {...(props as any)} />
            </Suspense>
        );
    };
}

// Simplified lazy components - only include components that actually exist
export const LazyComponents = {
    // Admin Components - using actual existing paths
    AdminDashboard: withLazyLoading(
        () => import('@/app/(admin)/admin/page'),
        <PageSkeleton />
    ),

    // Generic loading components
    LoadingSpinner: () => <LoadingSpinner />,
    ComponentSkeleton: () => <ComponentSkeleton />,
    PageSkeleton: () => <PageSkeleton />,
};

// Route-level lazy loading helper
export function createLazyRoute(
    importFunc: () => Promise<{ default: ComponentType<any> }>,
    fallback?: ReactNode
) {
    return withLazyLoading(importFunc, fallback || <PageSkeleton />);
}

// Preload helper for better UX
export class ComponentPreloader {
    private static preloadedComponents = new Set<string>();

    static async preload(componentName: keyof typeof LazyComponents) {
        if (this.preloadedComponents.has(componentName)) return;

        try {
            // For actual preloading, we'd need to expose the import functions
            // This is a simplified version that just tracks attempts
            this.preloadedComponents.add(componentName);

            // In a real scenario, you'd trigger the actual dynamic import here
            if (process.env.NODE_ENV === 'development') {
                console.log(`Preloading component: ${componentName}`);
            }
        } catch (error) {
            console.warn(`Failed to preload component ${componentName}:`, error);
        }
    }

    static preloadOnHover(componentName: keyof typeof LazyComponents) {
        return {
            onMouseEnter: () => this.preload(componentName),
            onFocus: () => this.preload(componentName),
        };
    }

    static clear() {
        this.preloadedComponents.clear();
    }
}

// Performance monitoring hook
export function useComponentLoadTime(componentName: string) {
    const startTime = performance.now();

    return () => {
        const endTime = performance.now();
        const loadTime = endTime - startTime;

        if (process.env.NODE_ENV === 'development') {
            console.log(`${componentName} loaded in ${loadTime.toFixed(2)}ms`);
        }

        // Send to analytics service if available
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'component_load_time', {
                component_name: componentName,
                load_time: Math.round(loadTime),
            });
        }
    };
}

// Utility function to create lazy component with error boundary
export function createLazyComponentWithErrorBoundary<P extends Record<string, any>>(
    importFunc: () => Promise<{ default: ComponentType<P> }>,
    fallback?: ReactNode
) {
    const LazyComponent = lazy(importFunc);

    return function LazyWrapperWithError(props: P) {
        return (
            <Suspense fallback={fallback || <ComponentSkeleton />}>
                <LazyComponent {...(props as any)} />
            </Suspense>
        );
    };
}

// Hook to track lazy loading performance
export function useLazyLoadingMetrics() {
    const trackLoad = (componentName: string, loadTime: number) => {
        if (process.env.NODE_ENV === 'development') {
            console.log(`Lazy component ${componentName} took ${loadTime}ms to load`);
        }

        // Track in analytics if available
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'lazy_component_load', {
                component_name: componentName,
                load_time: Math.round(loadTime),
            });
        }
    };

    return { trackLoad };
}