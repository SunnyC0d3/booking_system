import * as React from 'react';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard,
    Users,
    Package,
    ShoppingCart,
    BarChart3,
    Settings,
    Tags,
    Truck,
    Star,
    CreditCard,
    FileText,
    Bell,
    LogOut,
    Menu,
    X,
    Search,
    User,
    ChevronDown,
    Shield,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    Input,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { NotificationTrigger } from '@/components/notifications/NotificationPanel';
import { Breadcrumbs } from '@/components/layout/Breadcrumbs';
import { cn } from '@/lib/cn';

// Admin navigation structure
const adminNavigation = [
    {
        name: 'Dashboard',
        href: '/admin',
        icon: LayoutDashboard,
        description: 'Overview and analytics',
    },
    {
        name: 'Users',
        href: '/admin/users',
        icon: Users,
        description: 'Manage customers and staff',
        badge: '1,234',
    },
    {
        name: 'Products',
        href: '/admin/products',
        icon: Package,
        description: 'Product catalog management',
        children: [
            { name: 'All Products', href: '/admin/products' },
            { name: 'Categories', href: '/admin/products/categories' },
            { name: 'Attributes', href: '/admin/products/attributes' },
            { name: 'Tags', href: '/admin/products/tags' },
            { name: 'Inventory', href: '/admin/products/inventory' },
        ],
    },
    {
        name: 'Orders',
        href: '/admin/orders',
        icon: ShoppingCart,
        description: 'Order processing and fulfillment',
        badge: '47',
        badgeColor: 'bg-orange-500',
        children: [
            { name: 'All Orders', href: '/admin/orders' },
            { name: 'Pending', href: '/admin/orders?status=pending' },
            { name: 'Processing', href: '/admin/orders?status=processing' },
            { name: 'Shipped', href: '/admin/orders?status=shipped' },
            { name: 'Returns', href: '/admin/orders/returns' },
        ],
    },
    {
        name: 'Analytics',
        href: '/admin/analytics',
        icon: BarChart3,
        description: 'Reports and insights',
        children: [
            { name: 'Sales Reports', href: '/admin/analytics/sales' },
            { name: 'Customer Analytics', href: '/admin/analytics/customers' },
            { name: 'Product Performance', href: '/admin/analytics/products' },
            { name: 'Marketing Metrics', href: '/admin/analytics/marketing' },
        ],
    },
    {
        name: 'Reviews',
        href: '/admin/reviews',
        icon: Star,
        description: 'Review management',
        badge: '12',
        badgeColor: 'bg-blue-500',
    },
    {
        name: 'Shipping',
        href: '/admin/shipping',
        icon: Truck,
        description: 'Shipping methods and zones',
        children: [
            { name: 'Shipping Methods', href: '/admin/shipping/methods' },
            { name: 'Shipping Zones', href: '/admin/shipping/zones' },
            { name: 'Shipping Rates', href: '/admin/shipping/rates' },
        ],
    },
    {
        name: 'Payments',
        href: '/admin/payments',
        icon: CreditCard,
        description: 'Payment processing',
        children: [
            { name: 'Payment Methods', href: '/admin/payments/methods' },
            { name: 'Transactions', href: '/admin/payments/transactions' },
            { name: 'Refunds', href: '/admin/payments/refunds' },
        ],
    },
    {
        name: 'Content',
        href: '/admin/content',
        icon: FileText,
        description: 'CMS and content management',
        children: [
            { name: 'Pages', href: '/admin/content/pages' },
            { name: 'Blog Posts', href: '/admin/content/blog' },
            { name: 'Media Library', href: '/admin/content/media' },
        ],
    },
    {
        name: 'Settings',
        href: '/admin/settings',
        icon: Settings,
        description: 'System configuration',
        children: [
            { name: 'General', href: '/admin/settings/general' },
            { name: 'Email', href: '/admin/settings/email' },
            { name: 'API Keys', href: '/admin/settings/api' },
            { name: 'Security', href: '/admin/settings/security' },
        ],
    },
];

interface AdminLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
    showBreadcrumbs?: boolean;
    actions?: React.ReactNode;
    className?: string;
}

export const AdminLayout: React.FC<AdminLayoutProps> = ({
                                                            children,
                                                            title,
                                                            description,
                                                            showBreadcrumbs = true,
                                                            actions,
                                                            className,
                                                        }) => {
    const { user, logout } = useAuth();
    const router = useRouter();
    const pathname = usePathname();

    // State
    const [isSidebarOpen, setIsSidebarOpen] = React.useState(false);
    const [expandedItems, setExpandedItems] = React.useState<string[]>([]);
    const [searchQuery, setSearchQuery] = React.useState('');

    // Auto-expand current section
    React.useEffect(() => {
        const currentSection = adminNavigation.find(item =>
            pathname.startsWith(item.href) && item.children
        );
        if (currentSection && !expandedItems.includes(currentSection.name)) {
            setExpandedItems(prev => [...prev, currentSection.name]);
        }
    }, [pathname]);

    const toggleExpanded = (itemName: string) => {
        setExpandedItems(prev =>
            prev.includes(itemName)
                ? prev.filter(name => name !== itemName)
                : [...prev, itemName]
        );
    };

    const handleLogout = async () => {
        await logout();
        router.push('/auth/login');
    };

    const isActive = (href: string) => {
        if (href === '/admin') {
            return pathname === '/admin';
        }
        return pathname.startsWith(href);
    };

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Mobile sidebar backdrop */}
            <AnimatePresence>
                {isSidebarOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-black/50 z-40 lg:hidden"
                        onClick={() => setIsSidebarOpen(false)}
                    />
                )}
            </AnimatePresence>

            {/* Sidebar */}
            <motion.aside
                initial={false}
                animate={{ x: isSidebarOpen ? 0 : '-100%' }}
                className={cn(
                    'fixed top-0 left-0 z-50 h-full w-72 bg-white border-r border-gray-200 lg:translate-x-0 lg:static lg:z-0',
                    'transform transition-transform duration-300 ease-in-out lg:transform-none'
                )}
            >
                <div className="flex flex-col h-full">
                    {/* Logo */}
                    <div className="flex items-center justify-between px-6 py-4 border-b">
                        <Link href="/admin" className="flex items-center gap-3">
                            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                <Shield className="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h1 className="font-bold text-lg text-gray-900">Admin Panel</h1>
                                <p className="text-xs text-gray-500">Creative Business</p>
                            </div>
                        </Link>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setIsSidebarOpen(false)}
                            className="lg:hidden"
                        >
                            <X className="h-5 w-5" />
                        </Button>
                    </div>

                    {/* Quick Search */}
                    <div className="px-6 py-4 border-b">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                placeholder="Quick search..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10 bg-gray-50 border-gray-200"
                            />
                        </div>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 overflow-y-auto py-4">
                        <div className="px-3 space-y-1">
                            {adminNavigation.map((item) => (
                                <div key={item.name}>
                                    {/* Main Item */}
                                    <div
                                        className={cn(
                                            'group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                                            isActive(item.href)
                                                ? 'bg-primary text-white'
                                                : 'text-gray-700 hover:bg-gray-100'
                                        )}
                                    >
                                        <Link href={item.href} className="flex items-center flex-1">
                                            <item.icon className={cn(
                                                'mr-3 h-5 w-5 flex-shrink-0',
                                                isActive(item.href) ? 'text-white' : 'text-gray-400'
                                            )} />
                                            <span className="truncate">{item.name}</span>
                                        </Link>

                                        <div className="flex items-center gap-2">
                                            {item.badge && (
                                                <Badge
                                                    className={cn(
                                                        'text-xs',
                                                        item.badgeColor || 'bg-primary',
                                                        isActive(item.href) && 'bg-white text-primary'
                                                    )}
                                                >
                                                    {item.badge}
                                                </Badge>
                                            )}
                                            {item.children && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => toggleExpanded(item.name)}
                                                    className={cn(
                                                        'h-6 w-6 p-0',
                                                        isActive(item.href)
                                                            ? 'text-white hover:bg-white/20'
                                                            : 'text-gray-400 hover:bg-gray-200'
                                                    )}
                                                >
                                                    <ChevronDown className={cn(
                                                        'h-4 w-4 transition-transform',
                                                        expandedItems.includes(item.name) && 'rotate-180'
                                                    )} />
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Submenu */}
                                    <AnimatePresence>
                                        {item.children && expandedItems.includes(item.name) && (
                                            <motion.div
                                                initial={{ height: 0, opacity: 0 }}
                                                animate={{ height: 'auto', opacity: 1 }}
                                                exit={{ height: 0, opacity: 0 }}
                                                transition={{ duration: 0.2 }}
                                                className="overflow-hidden"
                                            >
                                                <div className="py-2 space-y-1">
                                                    {item.children.map((child) => (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            className={cn(
                                                                'block px-12 py-2 text-sm rounded-lg transition-colors',
                                                                isActive(child.href)
                                                                    ? 'bg-primary/10 text-primary font-medium'
                                                                    : 'text-gray-600 hover:bg-gray-50'
                                                            )}
                                                        >
                                                            {child.name}
                                                        </Link>
                                                    ))}
                                                </div>
                                            </motion.div>
                                        )}
                                    </AnimatePresence>
                                </div>
                            ))}
                        </div>
                    </nav>

                    {/* User Info */}
                    <div className="border-t px-6 py-4">
                        <div className="flex items-center gap-3">
                            <Avatar className="w-10 h-10">
                                <AvatarImage src={user?.avatar} />
                                <AvatarFallback>
                                    <User className="h-5 w-5" />
                                </AvatarFallback>
                            </Avatar>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate">
                                    {user?.name}
                                </p>
                                <p className="text-xs text-gray-500 truncate">
                                    {user?.role?.name || 'Admin'}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={handleLogout}
                                className="flex-shrink-0"
                            >
                                <LogOut className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </motion.aside>

            {/* Main Content */}
            <main className="lg:ml-72">
                {/* Top Header */}
                <header className="sticky top-0 z-30 bg-white border-b border-gray-200">
                    <div className="flex items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            {/* Mobile menu button */}
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => setIsSidebarOpen(true)}
                                className="lg:hidden"
                            >
                                <Menu className="h-5 w-5" />
                            </Button>

                            {/* Page Title */}
                            <div>
                                {title && (
                                    <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                                )}
                                {description && (
                                    <p className="text-sm text-gray-500 mt-1">{description}</p>
                                )}
                            </div>
                        </div>

                        {/* Header Actions */}
                        <div className="flex items-center gap-4">
                            {actions}
                            <NotificationTrigger />
                            <Button variant="outline" asChild>
                                <Link href="/" target="_blank">
                                    <ExternalLink className="mr-2 h-4 w-4" />
                                    View Site
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Breadcrumbs */}
                    {showBreadcrumbs && (
                        <div className="px-6 pb-4">
                            <Breadcrumbs className="text-sm" />
                        </div>
                    )}
                </header>

                {/* Page Content */}
                <div className={cn('p-6', className)}>
                    {children}
                </div>
            </main>
        </div>
    );
};

// Quick stats component for admin pages
interface QuickStatsProps {
    stats: {
        title: string;
        value: string | number;
        change?: string;
        trend?: 'up' | 'down' | 'neutral';
        icon: React.ComponentType<{ className?: string }>;
        color?: string;
    }[];
    className?: string;
}

export const QuickStats: React.FC<QuickStatsProps> = ({ stats, className }) => {
    return (
        <div className={cn('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6', className)}>
            {stats.map((stat, index) => (
                <motion.div
                    key={index}
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, delay: index * 0.1 }}
                >
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 mb-1">
                                        {stat.title}
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {stat.value}
                                    </p>
                                    {stat.change && (
                                        <p className={cn(
                                            'text-sm mt-1',
                                            stat.trend === 'up' && 'text-green-600',
                                            stat.trend === 'down' && 'text-red-600',
                                            stat.trend === 'neutral' && 'text-gray-500'
                                        )}>
                                            {stat.change}
                                        </p>
                                    )}
                                </div>
                                <div className={cn(
                                    'p-3 rounded-full',
                                    stat.color || 'bg-primary/10'
                                )}>
                                    <stat.icon className={cn(
                                        'h-6 w-6',
                                        stat.color ? 'text-white' : 'text-primary'
                                    )} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </motion.div>
            ))}
        </div>
    );
};

export default AdminLayout;