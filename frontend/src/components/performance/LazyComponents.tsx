import { lazy, Suspense, ComponentType, ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2 } from 'lucide-react';

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
export function withLazyLoading<P extends object>(
    importFunc: () => Promise<{ default: ComponentType<P> }>,
    fallback: ReactNode = <ComponentSkeleton />
) {
    const LazyComponent = lazy(importFunc);

    return function LazyWrapper(props: P) {
        return (
            <Suspense fallback={fallback}>
                <LazyComponent {...props} />
            </Suspense>
        );
    };
}

// Pre-configured lazy components for common UI patterns
export const LazyComponents = {
    // Admin Components
    AdminDashboard: withLazyLoading(
        () => import('@/app/(admin)/admin/page'),
        <PageSkeleton />
    ),
    UserManagement: withLazyLoading(
        () => import('@/app/(admin)/admin/users/page'),
        <PageSkeleton />
    ),
    ProductManagement: withLazyLoading(
        () => import('@/app/(admin)/admin/products/page'),
        <PageSkeleton />
    ),

    // E-commerce Components
    ProductListing: withLazyLoading(
        () => import('@/components/products/ProductGrid'),
        <LoadingSpinner message="Loading products..." />
    ),
    ShoppingCart: withLazyLoading(
        () => import('@/components/cart/ShoppingCart'),
        <LoadingSpinner message="Loading cart..." />
    ),
    Checkout: withLazyLoading(
        () => import('@/components/checkout/CheckoutForm'),
        <LoadingSpinner message="Loading checkout..." />
    ),

    // User Components
    ProfileSettings: withLazyLoading(
        () => import('@/components/user/ProfileSettings'),
        <ComponentSkeleton />
    ),
    OrderHistory: withLazyLoading(
        () => import('@/components/user/OrderHistory'),
        <ComponentSkeleton />
    ),

    // Complex Components
    ProductEditor: withLazyLoading(
        () => import('@/components/admin/ProductEditor'),
        <LoadingSpinner message="Loading editor..." />
    ),
    AnalyticsDashboard: withLazyLoading(
        () => import('@/components/analytics/Dashboard'),
        <LoadingSpinner message="Loading analytics..." />
    ),
    FileUploader: withLazyLoading(
        () => import('@/components/upload/FileUploader'),
        <LoadingSpinner message="Initializing uploader..." />
    ),
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

    static preload(componentName: keyof typeof LazyComponents) {
        if (this.preloadedComponents.has(componentName)) return;

        // Trigger the dynamic import to preload the component
        const component = LazyComponents[componentName];
        if (component) {
            this.preloadedComponents.add(componentName);
        }
    }

    static preloadOnHover(componentName: keyof typeof LazyComponents) {
        return {
            onMouseEnter: () => this.preload(componentName),
            onFocus: () => this.preload(componentName),
        };
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

        // You can send this to analytics service
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'component_load_time', {
                component_name: componentName,
                load_time: Math.round(loadTime),
            });
        }
    };
}