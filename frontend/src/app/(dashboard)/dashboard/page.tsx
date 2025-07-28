import * as React from 'react';
import { Metadata } from 'next';
import {
    Package,
    ShoppingCart,
    Heart,
    User,
    TrendingUp,
    Clock,
    Star,
    Plus,
} from 'lucide-react';
import { DashboardLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { RouteGuard } from '@/components/auth/RouteGuard';

export const metadata: Metadata = {
    title: 'Dashboard | Creative Business',
    description: 'Manage your orders, track shipments, and access your creative projects.',
};

// Sample data
const stats = [
    {
        title: 'Active Orders',
        value: '3',
        change: '+2 from last month',
        icon: Package,
        color: 'text-blue-600',
        bg: 'bg-blue-100',
    },
    {
        title: 'Completed Orders',
        value: '12',
        change: '+4 from last month',
        icon: ShoppingCart,
        color: 'text-green-600',
        bg: 'bg-green-100',
    },
    {
        title: 'Wishlist Items',
        value: '8',
        change: '+1 from last week',
        icon: Heart,
        color: 'text-pink-600',
        bg: 'bg-pink-100',
    },
    {
        title: 'Account Score',
        value: '4.9',
        change: 'Excellent rating',
        icon: Star,
        color: 'text-yellow-600',
        bg: 'bg-yellow-100',
    },
];

const recentOrders = [
    {
        id: 'ORD-001',
        product: 'Wedding Invitation Set',
        quantity: 50,
        status: 'In Production',
        date: '2024-07-25',
        total: '$125.00',
        statusColor: 'text-blue-600 bg-blue-100',
    },
    {
        id: 'ORD-002',
        product: 'Custom Business Labels',
        quantity: 200,
        status: 'Shipped',
        date: '2024-07-22',
        total: '$89.50',
        statusColor: 'text-green-600 bg-green-100',
    },
    {
        id: 'ORD-003',
        product: 'Birthday Gift Tags',
        quantity: 25,
        status: 'Delivered',
        date: '2024-07-20',
        total: '$32.75',
        statusColor: 'text-gray-600 bg-gray-100',
    },
];

const quickActions = [
    {
        title: 'Browse New Products',
        description: 'Discover our latest designs and collections',
        href: '/products',
        icon: Package,
        color: 'bg-primary',
    },
    {
        title: 'Track Orders',
        description: 'Check the status of your current orders',
        href: '/orders',
        icon: Clock,
        color: 'bg-blue-500',
    },
    {
        title: 'Reorder Favorites',
        description: 'Quickly reorder your most loved items',
        href: '/reorder',
        icon: Heart,
        color: 'bg-pink-500',
    },
    {
        title: 'Custom Design',
        description: 'Start a new custom design project',
        href: '/services/custom-design',
        icon: Plus,
        color: 'bg-green-500',
    },
];

function DashboardContent() {
    return (
        <DashboardLayout
            title="Welcome back!"
            description="Here's what's happening with your creative projects."
        >
            <div className="space-y-8">
                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {stats.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <Card key={stat.title} className="card-hover">
                                <CardContent className="p-6">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {stat.title}
                                            </p>
                                            <p className="text-3xl font-bold text-foreground">
                                                {stat.value}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {stat.change}
                                            </p>
                                        </div>
                                        <div className={`w-12 h-12 rounded-lg ${stat.bg} flex items-center justify-center`}>
                                            <Icon className={`h-6 w-6 ${stat.color}`} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Recent Orders */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Recent Orders</CardTitle>
                                <Button variant="outline" size="sm">
                                    View All
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentOrders.map((order) => (
                                        <div
                                            key={order.id}
                                            className="flex items-center justify-between p-4 rounded-lg border"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="font-medium text-foreground">
                                                        {order.product}
                                                    </h4>
                                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${order.statusColor}`}>
                            {order.status}
                          </span>
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    Order #{order.id} • {order.quantity} items • {order.date}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="font-semibold text-foreground">
                                                    {order.total}
                                                </div>
                                                <Button variant="ghost" size="sm" className="mt-1">
                                                    View Details
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;
                                        return (
                                            <a
                                                key={action.title}
                                                href={action.href}
                                                className="flex items-center gap-3 p-3 rounded-lg hover:bg-muted transition-colors group"
                                            >
                                                <div className={`w-10 h-10 rounded-lg ${action.color} flex items-center justify-center`}>
                                                    <Icon className="h-5 w-5 text-white" />
                                                </div>
                                                <div className="flex-1">
                                                    <h4 className="font-medium text-foreground group-hover:text-primary transition-colors">
                                                        {action.title}
                                                    </h4>
                                                    <p className="text-sm text-muted-foreground">
                                                        {action.description}
                                                    </p>
                                                </div>
                                            </a>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Account Summary */}
                        <Card className="mt-6">
                            <CardHeader>
                                <CardTitle>Account Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Member Since</span>
                                    <span className="text-sm font-medium">Jan 2024</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Total Orders</span>
                                    <span className="text-sm font-medium">15</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Total Spent</span>
                                    <span className="text-sm font-medium">$487.25</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Loyalty Points</span>
                                    <span className="text-sm font-medium">1,250 pts</span>
                                </div>
                                <Button variant="outline" className="w-full mt-4">
                                    View Profile
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent Activity */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center gap-4">
                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">
                                        Order #ORD-003 has been delivered
                                    </p>
                                    <p className="text-xs text-muted-foreground">2 hours ago</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">
                                        Order #ORD-001 is now in production
                                    </p>
                                    <p className="text-xs text-muted-foreground">1 day ago</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">
                                        Added 3 new items to wishlist
                                    </p>
                                    <p className="text-xs text-muted-foreground">3 days ago</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}

export default function DashboardPage() {
    return (
        <RouteGuard requireAuth>
            <DashboardContent />
        </RouteGuard>
    );
}