'use client';

import { useState, useEffect } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import {
    Alert,
    AlertDescription,
    Button,
    Badge,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
    Skeleton
} from '@/components/ui';
import { EmailVerification } from '@/components/auth/EmailVerification';
import Link from 'next/link';
import {
    User,
    ShoppingBag,
    Download,
    CreditCard,
    Settings,
    AlertCircle,
    TrendingUp,
    Package,
    Heart,
    Clock,
    ArrowRight,
    Eye,
    Star
} from 'lucide-react';

interface DashboardStats {
    total_orders: number;
    total_spent: number;
    digital_downloads: number;
    active_subscriptions: number;
    wishlist_items: number;
    loyalty_points: number;
}

interface RecentOrder {
    id: number;
    order_number: string;
    status: string;
    total: number;
    created_at: string;
    items_count: number;
}

interface RecentDownload {
    id: number;
    product_name: string;
    downloaded_at: string;
    file_size: string;
    download_count: number;
    max_downloads: number;
}

const DashboardSkeleton = () => (
    <div className="space-y-6">
        <div className="flex items-center justify-between">
            <div>
                <Skeleton className="h-8 w-64 mb-2" />
                <Skeleton className="h-4 w-96" />
            </div>
            <div className="flex space-x-2">
                <Skeleton className="h-6 w-16" />
                <Skeleton className="h-6 w-16" />
            </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-8 w-16" />
                            </div>
                            <Skeleton className="h-12 w-12 rounded-full" />
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>

        <div className="space-y-6">
            <Skeleton className="h-10 w-full" />
            <Card>
                <CardContent className="p-6">
                    <div className="space-y-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="flex items-center space-x-4">
                                <Skeleton className="h-10 w-10 rounded-full" />
                                <div className="flex-1">
                                    <Skeleton className="h-4 w-full mb-2" />
                                    <Skeleton className="h-3 w-3/4" />
                                </div>
                                <Skeleton className="h-6 w-16" />
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
);

export default function DashboardContent() {
    const {
        user,
        requireAuth,
        isEmailVerified,
        needsEmailVerification,
        getAuthHeaders,
        isLoading: authLoading
    } = useAuthUtils();

    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [recentOrders, setRecentOrders] = useState<RecentOrder[]>([]);
    const [recentDownloads, setRecentDownloads] = useState<RecentDownload[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('overview');
    const [dataError, setDataError] = useState<string | null>(null);

    useEffect(() => {
        if (!requireAuth()) return;
    }, [requireAuth]);

    useEffect(() => {
        if (user && isEmailVerified) {
            fetchDashboardData();
        } else if (user) {
            setIsLoading(false);
        }
    }, [user, isEmailVerified]);

    const fetchDashboardData = async () => {
        setIsLoading(true);
        setDataError(null);

        try {
            const headers = getAuthHeaders();

            try {
                const statsResponse = await fetch('/api/user/dashboard/stats', { headers });
                if (statsResponse.ok) {
                    const statsData = await statsResponse.json();
                    setStats(statsData);
                } else if (statsResponse.status === 404) {
                    console.warn('Dashboard stats endpoint not found');
                }
            } catch (error) {
                console.warn('Failed to fetch stats:', error);
            }

            try {
                const ordersResponse = await fetch('/api/user/orders?limit=5', { headers });
                if (ordersResponse.ok) {
                    const ordersData = await ordersResponse.json();
                    setRecentOrders(ordersData.orders || []);
                } else if (ordersResponse.status === 404) {
                    console.warn('Orders endpoint not found');
                }
            } catch (error) {
                console.warn('Failed to fetch orders:', error);
            }

            try {
                const downloadsResponse = await fetch('/api/user/digital-library/recent?limit=5', { headers });
                if (downloadsResponse.ok) {
                    const downloadsData = await downloadsResponse.json();
                    setRecentDownloads(downloadsData.downloads || []);
                } else if (downloadsResponse.status === 404) {
                    console.warn('Downloads endpoint not found');
                }
            } catch (error) {
                console.warn('Failed to fetch downloads:', error);
            }

        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            setDataError('Failed to load some dashboard data. Some features may be limited.');
        } finally {
            setIsLoading(false);
        }
    };

    const getOrderStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'completed':
                return 'bg-green-100 text-green-800';
            case 'processing':
                return 'bg-blue-100 text-blue-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'cancelled':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };

    if (authLoading || (isLoading && user)) {
        return <DashboardSkeleton />;
    }

    if (needsEmailVerification) {
        return (
            <div className="space-y-6">
                <Alert className="border-yellow-200 bg-yellow-50">
                    <AlertCircle className="h-4 w-4 text-yellow-600"/>
                    <AlertDescription className="text-yellow-800">
                        <strong>Email verification required</strong> - Please verify your email address to access all
                        dashboard features.
                    </AlertDescription>
                </Alert>

                <EmailVerification showCard={false} autoVerify={false}/>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {dataError && (
                <Alert className="border-orange-200 bg-orange-50">
                    <AlertCircle className="h-4 w-4 text-orange-600"/>
                    <AlertDescription className="text-orange-800">
                        {dataError}
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        Welcome back, {user?.name?.split(' ')[0] || 'User'}!
                    </h1>
                    <p className="text-gray-600">
                        Here's what's happening with your account today.
                    </p>
                </div>

                <div className="flex items-center space-x-2">
                    <Badge variant="outline" className="text-xs">
                        {user?.role?.name || 'Member'}
                    </Badge>
                    {isEmailVerified && (
                        <Badge variant="default" className="text-xs bg-green-100 text-green-800">
                            Verified
                        </Badge>
                    )}
                </div>
            </div>

            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Total Orders</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.total_orders}</p>
                                </div>
                                <div className="p-3 bg-blue-100 rounded-full">
                                    <ShoppingBag className="h-6 w-6 text-blue-600"/>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Total Spent</p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {formatCurrency(stats.total_spent)}
                                    </p>
                                </div>
                                <div className="p-3 bg-green-100 rounded-full">
                                    <CreditCard className="h-6 w-6 text-green-600"/>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Digital Downloads</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.digital_downloads}</p>
                                </div>
                                <div className="p-3 bg-purple-100 rounded-full">
                                    <Download className="h-6 w-6 text-purple-600"/>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Wishlist Items</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.wishlist_items}</p>
                                </div>
                                <div className="p-3 bg-red-100 rounded-full">
                                    <Heart className="h-6 w-6 text-red-600"/>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="orders">Recent Orders</TabsTrigger>
                    <TabsTrigger value="downloads">Downloads</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Link href="/products">
                                    <Button variant="outline" className="w-full justify-start">
                                        <Package className="w-4 h-4 mr-3"/>
                                        Browse Products
                                        <ArrowRight className="w-4 h-4 ml-auto"/>
                                    </Button>
                                </Link>

                                <Link href="/orders">
                                    <Button variant="outline" className="w-full justify-start">
                                        <ShoppingBag className="w-4 h-4 mr-3"/>
                                        View All Orders
                                        <ArrowRight className="w-4 h-4 ml-auto"/>
                                    </Button>
                                </Link>

                                <Link href="/account/digital-library">
                                    <Button variant="outline" className="w-full justify-start">
                                        <Download className="w-4 h-4 mr-3"/>
                                        Digital Library
                                        <ArrowRight className="w-4 h-4 ml-auto"/>
                                    </Button>
                                </Link>

                                <Link href="/profile">
                                    <Button variant="outline" className="w-full justify-start">
                                        <Settings className="w-4 h-4 mr-3"/>
                                        Account Settings
                                        <ArrowRight className="w-4 h-4 ml-auto"/>
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Account Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Email Verification</span>
                                    <Badge variant={isEmailVerified ? 'default' : 'secondary'}>
                                        {isEmailVerified ? 'Verified' : 'Pending'}
                                    </Badge>
                                </div>

                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Account Type</span>
                                    <Badge variant="outline">
                                        {user?.role?.name || 'Member'}
                                    </Badge>
                                </div>

                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">Member Since</span>
                                    <span className="text-sm text-gray-900">
                                        {user?.created_at
                                            ? new Date(user.created_at).toLocaleDateString()
                                            : 'Unknown'
                                        }
                                    </span>
                                </div>

                                {stats?.loyalty_points && stats.loyalty_points > 0 && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-600">Loyalty Points</span>
                                        <Badge variant="outline" className="bg-yellow-50 text-yellow-700">
                                            <Star className="w-3 h-3 mr-1"/>
                                            {stats.loyalty_points}
                                        </Badge>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="orders">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Orders</CardTitle>
                            <Link href="/orders">
                                <Button variant="outline" size="sm">
                                    View All
                                    <ArrowRight className="w-4 h-4 ml-2"/>
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentOrders.length > 0 ? (
                                <div className="space-y-4">
                                    {recentOrders.map((order) => (
                                        <div
                                            key={order.id}
                                            className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex items-center space-x-4">
                                                <div className="p-2 bg-blue-100 rounded-full">
                                                    <ShoppingBag className="h-4 w-4 text-blue-600"/>
                                                </div>
                                                <div>
                                                    <p className="font-medium">Order #{order.order_number}</p>
                                                    <p className="text-sm text-gray-600">
                                                        {order.items_count} item{order.items_count !== 1 ? 's' : ''} • {' '}
                                                        {new Date(order.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <p className="font-medium">{formatCurrency(order.total)}</p>
                                                <Badge
                                                    variant="outline"
                                                    className={getOrderStatusColor(order.status)}
                                                >
                                                    {order.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <ShoppingBag className="h-12 w-12 text-gray-400 mx-auto mb-4"/>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">No orders yet</h3>
                                    <p className="text-gray-600 mb-4">
                                        When you make your first purchase, it will appear here.
                                    </p>
                                    <Link href="/products">
                                        <Button>Start Shopping</Button>
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="downloads">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Downloads</CardTitle>
                            <Link href="/account/digital-library">
                                <Button variant="outline" size="sm">
                                    View Library
                                    <ArrowRight className="w-4 h-4 ml-2"/>
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentDownloads.length > 0 ? (
                                <div className="space-y-4">
                                    {recentDownloads.map((download) => (
                                        <div
                                            key={download.id}
                                            className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex items-center space-x-4">
                                                <div className="p-2 bg-purple-100 rounded-full">
                                                    <Download className="h-4 w-4 text-purple-600"/>
                                                </div>
                                                <div>
                                                    <p className="font-medium">{download.product_name}</p>
                                                    <p className="text-sm text-gray-600">
                                                        {download.file_size} • Downloaded {' '}
                                                        {new Date(download.downloaded_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <p className="text-sm text-gray-600">
                                                    {download.download_count}/{download.max_downloads} downloads
                                                </p>
                                                <div className="w-20 bg-gray-200 rounded-full h-2 mt-1">
                                                    <div
                                                        className="bg-purple-600 h-2 rounded-full transition-all"
                                                        style={{
                                                            width: `${Math.min((download.download_count / download.max_downloads) * 100, 100)}%`
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <Download className="h-12 w-12 text-gray-400 mx-auto mb-4"/>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">No downloads yet</h3>
                                    <p className="text-gray-600 mb-4">
                                        Digital products you purchase will be available for download here.
                                    </p>
                                    <Link href="/products?type=digital">
                                        <Button>Browse Digital Products</Button>
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center space-x-2">
                            <Clock className="h-5 w-5"/>
                            <span>Recent Activity</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {recentOrders.slice(0, 3).map((order) => (
                                <div key={`activity-${order.id}`} className="flex items-center space-x-3">
                                    <div className="w-2 h-2 bg-blue-600 rounded-full flex-shrink-0"></div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm truncate">
                                            Order #{order.order_number} was {order.status.toLowerCase()}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {new Date(order.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                            ))}

                            {recentDownloads.slice(0, 2).map((download) => (
                                <div key={`activity-download-${download.id}`} className="flex items-center space-x-3">
                                    <div className="w-2 h-2 bg-purple-600 rounded-full flex-shrink-0"></div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm truncate">
                                            Downloaded {download.product_name}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {new Date(download.downloaded_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                            ))}

                            {recentOrders.length === 0 && recentDownloads.length === 0 && (
                                <p className="text-center text-gray-500 py-4">
                                    No recent activity
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Help & Support</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-gray-600">
                            Need help with your account or have questions about your orders?
                        </p>

                        <div className="space-y-2">
                            <Link href="/help">
                                <Button variant="outline" className="w-full justify-start">
                                    <Eye className="w-4 h-4 mr-3"/>
                                    Browse Help Center
                                </Button>
                            </Link>

                            <Link href="/contact">
                                <Button variant="outline" className="w-full justify-start">
                                    <User className="w-4 h-4 mr-3"/>
                                    Contact Support
                                </Button>
                            </Link>
                        </div>

                        <div className="bg-blue-50 p-3 rounded-lg">
                            <p className="text-xs text-blue-700">
                                <strong>Pro tip:</strong> Check your email for order confirmations and download links.
                                They contain important information about your purchases.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {stats && stats.total_orders === 0 && (
                <Card className="bg-gradient-to-r from-blue-50 to-purple-50 border-blue-200">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                    Welcome to your dashboard! 🎉
                                </h3>
                                <p className="text-gray-600 mb-4">
                                    Ready to explore our products? Get started with your first purchase and unlock
                                    exclusive digital content.
                                </p>
                                <Link href="/products">
                                    <Button>
                                        Start Shopping
                                        <ArrowRight className="w-4 h-4 ml-2"/>
                                    </Button>
                                </Link>
                            </div>

                            <div className="hidden lg:block">
                                <div className="p-4 bg-white/50 rounded-full">
                                    <TrendingUp className="h-8 w-8 text-blue-600"/>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}