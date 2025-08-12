'use client'

import * as React from 'react';
import Link from 'next/link';
import {
    Package,
    ShoppingCart,
    Heart,
    User,
    Clock,
    Star,
    ArrowRight,
    MapPin,
} from 'lucide-react';
// import { DashboardLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { useAuth } from '@/stores/authStore';
import { useCartItemCount } from '@/stores/cartStore';
import { cn } from '@/lib/cn';

// Enhanced stats with real data integration
function DashboardOverview() {
    const { user } = useAuth();
    const cartItemCount = useCartItemCount();

    const stats = [
        {
            title: 'Active Orders',
            value: '3',
            change: '+2 from last month',
            icon: Package,
            color: 'text-blue-600',
            bg: 'bg-blue-100',
            href: '/orders?status=active',
        },
        {
            title: 'Completed Orders',
            value: '12',
            change: '+4 from last month',
            icon: ShoppingCart,
            color: 'text-green-600',
            bg: 'bg-green-100',
            href: '/orders?status=completed',
        },
        {
            title: 'Cart Items',
            value: cartItemCount.toString(),
            change: 'Ready for checkout',
            icon: Heart,
            color: 'text-pink-600',
            bg: 'bg-pink-100',
            href: '/cart',
        },
        {
            title: 'Account Score',
            value: '4.9',
            change: 'Excellent rating',
            icon: Star,
            color: 'text-yellow-600',
            bg: 'bg-yellow-100',
            href: '/profile',
        },
    ];

    const recentOrders = [
        {
            id: 'ORD-001',
            product: 'Wedding Invitation Set',
            quantity: 50,
            status: 'In Production',
            date: '2024-07-25',
            total: '£125.00',
            statusColor: 'text-blue-600 bg-blue-100',
        },
        {
            id: 'ORD-002',
            product: 'Custom Business Labels',
            quantity: 200,
            status: 'Shipped',
            date: '2024-07-22',
            total: '£89.50',
            statusColor: 'text-green-600 bg-green-100',
        },
        {
            id: 'ORD-003',
            product: 'Birthday Gift Tags',
            quantity: 25,
            status: 'Delivered',
            date: '2024-07-20',
            total: '£32.75',
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
            title: 'Manage Addresses',
            description: 'Update your shipping and billing addresses',
            href: '/addresses',
            icon: MapPin,
            color: 'bg-green-500',
        },
        {
            title: 'Account Settings',
            description: 'Update your profile and preferences',
            href: '/profile',
            icon: User,
            color: 'bg-purple-500',
        },
    ];

    return (
        // <DashboardLayout
        //     title={`Welcome back, ${user?.name?.split(' ')[0] || 'there'}!`}
        //     description="Here's what's happening with your creative projects."
        // >
        <>
            <div className="space-y-8">
                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {stats.map((stat, index) => (
                        <Link key={index} href={stat.href}>
                            <Card className="hover:shadow-md transition-shadow cursor-pointer">
                                <CardContent className="p-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {stat.title}
                                            </p>
                                            <p className="text-2xl font-bold text-foreground">
                                                {stat.value}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                {stat.change}
                                            </p>
                                        </div>
                                        <div className={cn('p-3 rounded-full', stat.bg)}>
                                            <stat.icon className={cn('h-6 w-6', stat.color)} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                <div className="grid lg:grid-cols-3 gap-8">
                    {/* Recent Orders */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Recent Orders</CardTitle>
                                <Link href="/orders">
                                    <Button variant="ghost" size="sm">
                                        View All
                                        <ArrowRight className="h-4 w-4 ml-2" />
                                    </Button>
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentOrders.map((order, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between p-4 bg-muted/50 rounded-lg"
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3">
                                                    <div>
                                                        <p className="font-medium text-sm">
                                                            {order.product}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {order.id} • Qty: {order.quantity}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className={cn(
                                                    'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium mb-1',
                                                    order.statusColor
                                                )}>
                                                    {order.status}
                                                </div>
                                                <p className="text-sm font-medium">
                                                    {order.total}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {new Date(order.date).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions & Account Summary */}
                    <div className="space-y-6">
                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {quickActions.map((action, index) => (
                                    <Link key={index} href={action.href}>
                                        <div className="flex items-center gap-3 p-3 rounded-lg hover:bg-muted/50 transition-colors cursor-pointer">
                                            <div className={cn('p-2 rounded-lg text-white', action.color)}>
                                                <action.icon className="h-4 w-4" />
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-sm">
                                                    {action.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {action.description}
                                                </p>
                                            </div>
                                            <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    </Link>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Account Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Account Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Member Since</span>
                                    <span className="text-sm font-medium">
                                        {user?.created_at
                                            ? new Date(user.created_at).toLocaleDateString('en-GB', {
                                                month: 'short',
                                                year: 'numeric'
                                            })
                                            : 'Jan 2024'
                                        }
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Total Orders</span>
                                    <span className="text-sm font-medium">15</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Total Spent</span>
                                    <span className="text-sm font-medium">£487.25</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Email Status</span>
                                    <span className={cn(
                                        "text-sm font-medium",
                                        user?.email_verified_at ? "text-success" : "text-warning"
                                    )}>
                                        {user?.email_verified_at ? 'Verified' : 'Unverified'}
                                    </span>
                                </div>
                                <Link href="/profile">
                                    <Button variant="outline" className="w-full mt-4">
                                        <User className="h-4 w-4 mr-2" />
                                        Manage Profile
                                    </Button>
                                </Link>
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
                                        Profile updated successfully
                                    </p>
                                    <p className="text-xs text-muted-foreground">3 days ago</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="w-2 h-2 bg-pink-500 rounded-full"></div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">
                                        Added {cartItemCount} items to cart
                                    </p>
                                    <p className="text-xs text-muted-foreground">1 week ago</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
            {/*</DashboardLayout>*/}
        </>
    );
}

export default function DashboardPage() {
    return (
        <RouteGuard requireAuth>
            <DashboardOverview />
        </RouteGuard>
    );
}