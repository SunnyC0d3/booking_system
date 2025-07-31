'use client'

import * as React from 'react';
import { Metadata } from 'next';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Users,
    Package,
    ShoppingCart,
    DollarSign,
    TrendingUp,
    TrendingDown,
    Eye,
    Clock,
    Star,
    AlertTriangle,
    CheckCircle,
    ArrowRight,
    Calendar,
    BarChart3,
    PieChart,
    Activity,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Progress,
    Badge,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui';
import { AdminLayout, QuickStats } from '@/components/layout/AdminLayout';
import { RouteGuard } from '@/components/auth/RouteGuard';

export const metadata: Metadata = {
    title: 'Admin Dashboard | Creative Business',
    description: 'Administrative dashboard for managing your creative business.',
};

// Mock data - replace with real API calls
const dashboardStats = [
    {
        title: 'Total Revenue',
        value: '£24,580',
        change: '+12% from last month',
        trend: 'up' as const,
        icon: DollarSign,
        color: 'bg-green-500',
    },
    {
        title: 'Total Orders',
        value: '1,234',
        change: '+8% from last month',
        trend: 'up' as const,
        icon: ShoppingCart,
        color: 'bg-blue-500',
    },
    {
        title: 'Total Customers',
        value: '892',
        change: '+15% from last month',
        trend: 'up' as const,
        icon: Users,
        color: 'bg-purple-500',
    },
    {
        title: 'Products',
        value: '567',
        change: '+5 new this week',
        trend: 'up' as const,
        icon: Package,
        color: 'bg-orange-500',
    },
];

const recentOrders = [
    {
        id: 'ORD-001',
        customer: 'Sarah Johnson',
        product: 'Wedding Invitation Set',
        amount: '£125.00',
        status: 'processing',
        date: '2025-01-28',
    },
    {
        id: 'ORD-002',
        customer: 'Michael Chen',
        product: 'Custom Business Labels',
        amount: '£89.50',
        status: 'shipped',
        date: '2025-01-28',
    },
    {
        id: 'ORD-003',
        customer: 'Emily Rodriguez',
        product: 'Birthday Gift Tags',
        amount: '£32.75',
        status: 'completed',
        date: '2025-01-27',
    },
    {
        id: 'ORD-004',
        customer: 'David Wilson',
        product: 'Thank You Stickers',
        amount: '£45.20',
        status: 'pending',
        date: '2025-01-27',
    },
    {
        id: 'ORD-005',
        customer: 'Lisa Thompson',
        product: 'Corporate Greeting Cards',
        amount: '£156.80',
        status: 'processing',
        date: '2025-01-27',
    },
];

const topProducts = [
    {
        name: 'Wedding Invitation Set',
        sales: 89,
        revenue: '£4,450',
        trend: 'up',
        change: '+12%',
    },
    {
        name: 'Custom Business Labels',
        sales: 76,
        revenue: '£3,420',
        trend: 'up',
        change: '+8%',
    },
    {
        name: 'Birthday Gift Tags',
        sales: 65,
        revenue: '£2,145',
        trend: 'down',
        change: '-3%',
    },
    {
        name: 'Thank You Stickers',
        sales: 54,
        revenue: '£1,890',
        trend: 'up',
        change: '+15%',
    },
    {
        name: 'Holiday Greeting Cards',
        sales: 43,
        revenue: '£1,720',
        trend: 'neutral',
        change: '0%',
    },
];

const activityFeed = [
    {
        id: 1,
        type: 'order',
        message: 'New order #ORD-001 received from Sarah Johnson',
        time: '2 minutes ago',
        icon: ShoppingCart,
        color: 'text-blue-600',
    },
    {
        id: 2,
        type: 'user',
        message: 'New customer registration: Michael Chen',
        time: '15 minutes ago',
        icon: Users,
        color: 'text-green-600',
    },
    {
        id: 3,
        type: 'product',
        message: 'Low stock alert: Wedding Invitation Set (5 remaining)',
        time: '1 hour ago',
        icon: AlertTriangle,
        color: 'text-orange-600',
    },
    {
        id: 4,
        type: 'review',
        message: 'New 5-star review for Custom Business Labels',
        time: '2 hours ago',
        icon: Star,
        color: 'text-yellow-600',
    },
    {
        id: 5,
        type: 'order',
        message: 'Order #ORD-098 has been shipped',
        time: '3 hours ago',
        icon: CheckCircle,
        color: 'text-green-600',
    },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'processing':
            return 'bg-blue-100 text-blue-800';
        case 'shipped':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getTrendIcon = (trend: string) => {
    switch (trend) {
        case 'up':
            return <TrendingUp className="h-4 w-4 text-green-600" />;
        case 'down':
            return <TrendingDown className="h-4 w-4 text-red-600" />;
        default:
            return <Activity className="h-4 w-4 text-gray-600" />;
    }
};

export default function AdminDashboardPage() {
    return (
        <RouteGuard requireAuth requiredRoles={['admin', 'super admin']}>
            <AdminLayout
                title="Dashboard"
                description="Welcome back! Here's what's happening with your business today."
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm">
                            <Calendar className="mr-2 h-4 w-4" />
                            Last 30 days
                        </Button>
                        <Button size="sm">
                            Export Report
                        </Button>
                    </div>
                }
            >
                <div className="space-y-8">
                    {/* Quick Stats */}
                    <QuickStats stats={dashboardStats} />

                    {/* Main Content Grid */}
                    <div className="grid lg:grid-cols-3 gap-8">
                        {/* Left Column - Charts & Analytics */}
                        <div className="lg:col-span-2 space-y-8">
                            {/* Revenue Chart */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <BarChart3 className="h-5 w-5 text-primary" />
                                            Revenue Overview
                                        </CardTitle>
                                        <Tabs defaultValue="week" className="w-auto">
                                            <TabsList className="grid grid-cols-3 w-[300px]">
                                                <TabsTrigger value="week">7 Days</TabsTrigger>
                                                <TabsTrigger value="month">30 Days</TabsTrigger>
                                                <TabsTrigger value="year">12 Months</TabsTrigger>
                                            </TabsList>
                                        </Tabs>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {/* Placeholder for chart - replace with actual chart library */}
                                    <div className="h-[300px] bg-gradient-to-br from-primary/5 to-primary/10 rounded-lg flex items-center justify-center">
                                        <div className="text-center">
                                            <BarChart3 className="h-16 w-16 text-primary/40 mx-auto mb-4" />
                                            <p className="text-lg font-medium text-gray-600">Revenue Chart</p>
                                            <p className="text-sm text-gray-500">Chart component integration pending</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Top Products */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Package className="h-5 w-5 text-primary" />
                                        Top Performing Products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {topProducts.map((product, index) => (
                                            <motion.div
                                                key={index}
                                                initial={{ opacity: 0, x: -20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.3, delay: index * 0.1 }}
                                                className="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                            >
                                                <div className="flex-1">
                                                    <h4 className="font-medium text-gray-900 mb-1">
                                                        {product.name}
                                                    </h4>
                                                    <div className="flex items-center gap-4 text-sm text-gray-600">
                                                        <span>{product.sales} sales</span>
                                                        <span>•</span>
                                                        <span className="font-medium">{product.revenue}</span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {getTrendIcon(product.trend)}
                                                    <span className={`text-sm font-medium ${
                                                        product.trend === 'up' ? 'text-green-600' :
                                                            product.trend === 'down' ? 'text-red-600' :
                                                                'text-gray-600'
                                                    }`}>
                                                        {product.change}
                                                    </span>
                                                </div>
                                            </motion.div>
                                        ))}
                                    </div>
                                    <div className="mt-6 text-center">
                                        <Button variant="outline" asChild>
                                            <Link href="/admin/analytics/products">
                                                View All Products
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right Column - Recent Activity */}
                        <div className="space-y-8">
                            {/* Recent Orders */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <ShoppingCart className="h-5 w-5 text-primary" />
                                            Recent Orders
                                        </CardTitle>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href="/admin/orders">
                                                View All
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {recentOrders.map((order, index) => (
                                        <motion.div
                                            key={order.id}
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.3, delay: index * 0.05 }}
                                            className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-medium text-sm text-gray-900">
                                                        {order.id}
                                                    </span>
                                                    <Badge className={getStatusColor(order.status)}>
                                                        {order.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-gray-600 truncate">
                                                    {order.customer} - {order.product}
                                                </p>
                                                <div className="flex items-center justify-between mt-2">
                                                    <span className="text-sm font-medium text-gray-900">
                                                        {order.amount}
                                                    </span>
                                                    <span className="text-xs text-gray-500">
                                                        {new Date(order.date).toLocaleDateString()}
                                                    </span>
                                                </div>
                                            </div>
                                        </motion.div>
                                    ))}
                                </CardContent>
                            </Card>

                            {/* Activity Feed */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-5 w-5 text-primary" />
                                        Recent Activity
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {activityFeed.map((activity, index) => (
                                            <motion.div
                                                key={activity.id}
                                                initial={{ opacity: 0, x: 20 }}
                                                animate={{ opacity: 1, x: 0 }}
                                                transition={{ duration: 0.3, delay: index * 0.05 }}
                                                className="flex items-start gap-3"
                                            >
                                                <div className="p-2 bg-gray-100 rounded-full flex-shrink-0">
                                                    <activity.icon className={`h-4 w-4 ${activity.color}`} />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm text-gray-900 leading-tight">
                                                        {activity.message}
                                                    </p>
                                                    <p className="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        {activity.time}
                                                    </p>
                                                </div>
                                            </motion.div>
                                        ))}
                                    </div>
                                    <div className="mt-6 text-center">
                                        <Button variant="outline" size="sm">
                                            View All Activity
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Quick Actions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Quick Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button className="w-full justify-start" asChild>
                                        <Link href="/admin/products/new">
                                            <Package className="mr-2 h-4 w-4" />
                                            Add New Product
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="w-full justify-start" asChild>
                                        <Link href="/admin/orders?status=pending">
                                            <ShoppingCart className="mr-2 h-4 w-4" />
                                            Process Orders
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="w-full justify-start" asChild>
                                        <Link href="/admin/users">
                                            <Users className="mr-2 h-4 w-4" />
                                            Manage Users
                                        </Link>
                                    </Button>
                                    <Button variant="outline" className="w-full justify-start" asChild>
                                        <Link href="/admin/analytics">
                                            <BarChart3 className="mr-2 h-4 w-4" />
                                            View Reports
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </AdminLayout>
        </RouteGuard>
    );
}