import * as React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { ChevronRight, Home } from 'lucide-react';
import { cn } from '@/lib/cn';

export interface BreadcrumbItem {
    label: string;
    href?: string;
    current?: boolean;
}

interface BreadcrumbsProps {
    items?: BreadcrumbItem[];
    className?: string;
    showHome?: boolean;
    separator?: React.ReactNode;
}

// Helper function to generate breadcrumbs from pathname
const generateBreadcrumbsFromPath = (pathname: string): BreadcrumbItem[] => {
    const segments = pathname.split('/').filter(Boolean);
    const breadcrumbs: BreadcrumbItem[] = [];

    // Map of paths to friendly names
    const pathNames: Record<string, string> = {
        'products': 'Products',
        'collections': 'Collections',
        'services': 'Services',
        'dashboard': 'Dashboard',
        'profile': 'Profile',
        'orders': 'Orders',
        'settings': 'Settings',
        'cart': 'Shopping Cart',
        'checkout': 'Checkout',
        'about': 'About Us',
        'contact': 'Contact',
        'help': 'Help Center',
        'blog': 'Blog',
        'search': 'Search Results',
        'admin': 'Admin',
        'users': 'Users',
        'analytics': 'Analytics',
        // Product categories
        'labels': 'Labels',
        'invitations': 'Invitations',
        'gift-tags': 'Gift Tags',
        'stickers': 'Stickers',
        'greeting-cards': 'Greeting Cards',
        'packaging': 'Packaging',
        // Collections
        'wedding': 'Wedding Collection',
        'birthday': 'Birthday Collection',
        'business': 'Business Collection',
        'holiday': 'Holiday Collection',
        // Services
        'custom-design': 'Custom Design',
        'flower-stands': 'Flower Stands',
        'bulk-orders': 'Bulk Orders',
        'rush-printing': 'Rush Printing',
    };

    segments.forEach((segment, index) => {
        const href = '/' + segments.slice(0, index + 1).join('/');
        const isLast = index === segments.length - 1;

        // Try to get friendly name, fallback to formatted segment
        let label = pathNames[segment];
        if (!label) {
            // Format segment: 'hello-world' -> 'Hello World'
            label = segment
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        breadcrumbs.push({
            label,
            href: isLast ? undefined : href,
            current: isLast,
        });
    });

    return breadcrumbs;
};

export const Breadcrumbs: React.FC<BreadcrumbsProps> = ({
                                                            items,
                                                            className,
                                                            showHome = true,
                                                            separator = <ChevronRight className="h-4 w-4" />,
                                                        }) => {
    const pathname = usePathname();

    // Use provided items or generate from pathname
    const breadcrumbItems = items || generateBreadcrumbsFromPath(pathname);

    // Don't show breadcrumbs on homepage
    if (pathname === '/' || breadcrumbItems.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Breadcrumb"
            className={cn('flex items-center space-x-1 text-sm', className)}
        >
            <ol className="flex items-center space-x-1">
                {/* Home Link */}
                {showHome && (
                    <>
                        <li>
                            <Link
                                href="/"
                                className="flex items-center text-muted-foreground hover:text-foreground transition-colors"
                            >
                                <Home className="h-4 w-4" />
                                <span className="sr-only">Home</span>
                            </Link>
                        </li>
                        {breadcrumbItems.length > 0 && (
                            <li className="flex items-center text-muted-foreground">
                                {separator}
                            </li>
                        )}
                    </>
                )}

                {/* Breadcrumb Items */}
                {breadcrumbItems.map((item, index) => {
                    const isLast = index === breadcrumbItems.length - 1;

                    return (
                        <React.Fragment key={item.href || item.label}>
                            <li>
                                {item.href && !item.current ? (
                                    <Link
                                        href={item.href}
                                        className="text-muted-foreground hover:text-foreground transition-colors font-medium"
                                    >
                                        {item.label}
                                    </Link>
                                ) : (
                                    <span
                                        className={cn(
                                            'font-medium',
                                            item.current
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        )}
                                        aria-current={item.current ? 'page' : undefined}
                                    >
                    {item.label}
                  </span>
                                )}
                            </li>

                            {!isLast && (
                                <li className="flex items-center text-muted-foreground">
                                    {separator}
                                </li>
                            )}
                        </React.Fragment>
                    );
                })}
            </ol>
        </nav>
    );
};

// Breadcrumb container component
interface BreadcrumbContainerProps {
    children?: React.ReactNode;
    className?: string;
}

export const BreadcrumbContainer: React.FC<BreadcrumbContainerProps> = ({
                                                                            children,
                                                                            className,
                                                                        }) => {
    return (
        <div className={cn('border-b bg-muted/30', className)}>
            <div className="container mx-auto px-4 py-3">
                {children || <Breadcrumbs />}
            </div>
        </div>
    );
};

// Hook for managing breadcrumbs
export const useBreadcrumbs = (items?: BreadcrumbItem[]) => {
    const pathname = usePathname();

    const breadcrumbs = React.useMemo(() => {
        return items || generateBreadcrumbsFromPath(pathname);
    }, [items, pathname]);

    const addBreadcrumb = (item: BreadcrumbItem) => {
        return [...breadcrumbs, item];
    };

    const updateBreadcrumb = (index: number, item: Partial<BreadcrumbItem>) => {
        const updated = [...breadcrumbs];
        updated[index] = { ...updated[index], ...item };
        return updated;
    };

    return {
        breadcrumbs,
        addBreadcrumb,
        updateBreadcrumb,
    };
};

export default Breadcrumbs;