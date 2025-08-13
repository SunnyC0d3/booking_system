'use client'

import * as React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui';

interface ActivityItem {
    id: string;
    message: string;
    timestamp: string;
    type: 'order' | 'profile' | 'cart' | 'general';
    colorClass: string;
}

interface RecentActivityProps {
    userId: string;
    limit?: number;
}

function getRecentActivity(userId: string, limit: number = 4): Promise<ActivityItem[]> {
    return Promise.resolve([
        {
            id: '1',
            message: 'Order #ORD-003 has been delivered',
            timestamp: '2 hours ago',
            type: 'order',
            colorClass: 'bg-green-500',
        },
        {
            id: '2',
            message: 'Order #ORD-001 is now in production',
            timestamp: '1 day ago',
            type: 'order',
            colorClass: 'bg-blue-500',
        },
        {
            id: '3',
            message: 'Profile updated successfully',
            timestamp: '3 days ago',
            type: 'profile',
            colorClass: 'bg-yellow-500',
        },
        {
            id: '4',
            message: 'Added 3 items to cart',
            timestamp: '1 week ago',
            type: 'cart',
            colorClass: 'bg-pink-500',
        },
    ].slice(0, limit));
}

export default function RecentActivity({ userId, limit = 4 }: RecentActivityProps) {
    const [activities, setActivities] = React.useState<ActivityItem[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);

    React.useEffect(() => {
        async function fetchActivity() {
            try {
                const data = await getRecentActivity(userId, limit);
                setActivities(data);
            } catch (error) {
                console.error('Failed to fetch recent activity:', error);
            } finally {
                setIsLoading(false);
            }
        }

        fetchActivity();
    }, [userId, limit]);

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="flex items-center gap-4">
                                <div className="w-2 h-2 bg-muted animate-pulse rounded-full flex-shrink-0" />
                                <div className="flex-1 space-y-1">
                                    <div className="h-4 w-48 bg-muted animate-pulse rounded" />
                                    <div className="h-3 w-16 bg-muted animate-pulse rounded" />
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {activities.map((activity) => (
                        <div key={activity.id} className="flex items-center gap-4">
                            <div className={`w-2 h-2 ${activity.colorClass} rounded-full flex-shrink-0`}></div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-foreground leading-tight">
                                    {activity.message}
                                </p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {activity.timestamp}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}