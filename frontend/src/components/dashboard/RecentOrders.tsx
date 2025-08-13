'use client'

import * as React from 'react';
import Link from 'next/link';
import { ArrowRight } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { cn } from '@/lib/cn';

interface Order {
    id: string;
    product: string;
    quantity: number;
    status: string;
    date: string;
    total: string;
    statusColor: string;
}

interface RecentOrdersProps {
    userId: string;
    limit?: number;
}

function getRecentOrders(userId: string, limit: number = 3): Promise<Order[]> {
    return Promise.resolve([
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
    ].slice(0, limit));
}

export default function RecentOrders({ userId, limit = 3 }: RecentOrdersProps) {
    const [orders, setOrders] = React.useState<Order[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);

    React.useEffect(() => {
        async function fetchOrders() {
            try {
                const data = await getRecentOrders(userId, limit);
                setOrders(data);
            } catch (error) {
                console.error('Failed to fetch recent orders:', error);
            } finally {
                setIsLoading(false);
            }
        }

        fetchOrders();
    }, [userId, limit]);

    if (isLoading) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <div className="h-6 w-32 bg-muted animate-pulse rounded" />
                        <div className="h-8 w-20 bg-muted animate-pulse rounded" />
                    </div>
                    <div className="space-y-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                                <div className="space-y-2">
                                    <div className="h-4 w-40 bg-muted animate-pulse rounded" />
                                    <div className="h-3 w-24 bg-muted animate-pulse rounded" />
                                </div>
                                <div className="text-right space-y-2">
                                    <div className="h-5 w-16 bg-muted animate-pulse rounded" />
                                    <div className="h-4 w-12 bg-muted animate-pulse rounded" />
                                    <div className="h-3 w-16 bg-muted animate-pulse rounded" />
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (orders.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Recent Orders</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-center py-6">
                        <p className="text-muted-foreground">No orders yet</p>
                        <Link href="/products">
                            <Button className="mt-4">
                                Browse Products
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
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
                    {orders.map((order) => (
                        <Link
                            key={order.id}
                            href={`/orders/${order.id}`}
                            className="block"
                        >
                            <div className="flex items-center justify-between p-4 bg-muted/50 rounded-lg hover:bg-muted/70 transition-colors">
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
                                        {new Date(order.date).toLocaleDateString('en-GB')}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}