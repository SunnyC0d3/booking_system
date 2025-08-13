'use client'

import * as React from 'react';
import Link from 'next/link';
import { Package, ShoppingCart, Heart, Star } from 'lucide-react';
import { Card, CardContent } from '@/components/ui';
import { cn } from '@/lib/cn';

interface DashboardStat {
    title: string;
    value: string;
    change: string;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
    bg: string;
    href: string;
}

interface DashboardStatsProps {
    userId: string;
    cartItemCount: number;
}

function getDashboardStats(userId: string): Promise<Omit<DashboardStat, 'icon' | 'color' | 'bg' | 'href'>[]> {
    return Promise.resolve([
        {
            title: 'Active Orders',
            value: '3',
            change: '+2 from last month',
        },
        {
            title: 'Completed Orders',
            value: '12',
            change: '+4 from last month',
        },
        {
            title: 'Account Score',
            value: '4.9',
            change: 'Excellent rating',
        },
    ]);
}

export default function DashboardStats({ userId, cartItemCount }: DashboardStatsProps) {
    const [statsData, setStatsData] = React.useState<Omit<DashboardStat, 'icon' | 'color' | 'bg' | 'href'>[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);

    React.useEffect(() => {
        async function fetchStats() {
            try {
                const data = await getDashboardStats(userId);
                setStatsData(data);
            } catch (error) {
                console.error('Failed to fetch dashboard stats:', error);
            } finally {
                setIsLoading(false);
            }
        }

        fetchStats();
    }, [userId]);

    if (isLoading) {
        return (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i}>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div className="space-y-2">
                                    <div className="h-4 w-24 bg-muted animate-pulse rounded" />
                                    <div className="h-8 w-16 bg-muted animate-pulse rounded" />
                                    <div className="h-3 w-32 bg-muted animate-pulse rounded" />
                                </div>
                                <div className="h-12 w-12 bg-muted animate-pulse rounded-full" />
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    }

    const stats: DashboardStat[] = [
        {
            ...statsData[0],
            icon: Package,
            color: 'text-blue-600',
            bg: 'bg-blue-100',
            href: '/orders?status=active',
        },
        {
            ...statsData[1],
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
            ...statsData[2],
            icon: Star,
            color: 'text-yellow-600',
            bg: 'bg-yellow-100',
            href: '/profile',
        },
    ];

    return (
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
    );
}