'use client'

import * as React from 'react';
import Link from 'next/link';
import { User } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { cn } from '@/lib/cn';

interface User {
    id: string;
    name: string;
    email: string;
    created_at: string;
    email_verified_at: string | null;
}

interface AccountStats {
    totalOrders: number;
    totalSpent: string;
}

interface AccountSummaryProps {
    userId: string;
}

function getUserData(userId: string): Promise<User> {
    return Promise.resolve({
        id: userId,
        name: 'John Doe',
        email: 'john@example.com',
        created_at: '2024-01-15T00:00:00Z',
        email_verified_at: '2024-01-15T10:30:00Z',
    });
}

function getAccountStats(userId: string): Promise<AccountStats> {
    return Promise.resolve({
        totalOrders: 15,
        totalSpent: '£487.25',
    });
}

export default function AccountSummary({ userId }: AccountSummaryProps) {
    const [user, setUser] = React.useState<User | null>(null);
    const [stats, setStats] = React.useState<AccountStats | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);

    React.useEffect(() => {
        async function fetchData() {
            try {
                const [userData, statsData] = await Promise.all([
                    getUserData(userId),
                    getAccountStats(userId),
                ]);
                setUser(userData);
                setStats(statsData);
            } catch (error) {
                console.error('Failed to fetch account data:', error);
            } finally {
                setIsLoading(false);
            }
        }

        fetchData();
    }, [userId]);

    if (isLoading) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="h-6 w-28 bg-muted animate-pulse rounded mb-4" />
                    <div className="space-y-4">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="flex items-center justify-between">
                                <div className="h-4 w-20 bg-muted animate-pulse rounded" />
                                <div className="h-4 w-16 bg-muted animate-pulse rounded" />
                            </div>
                        ))}
                        <div className="h-10 w-full bg-muted animate-pulse rounded mt-4" />
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!user || !stats) {
        return null;
    }

    const memberSince = user?.created_at
        ? new Date(user.created_at).toLocaleDateString('en-GB', {
            month: 'short',
            year: 'numeric'
        })
        : 'Jan 2024';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Account Summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Member Since</span>
                    <span className="text-sm font-medium">
            {memberSince}
          </span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Total Orders</span>
                    <span className="text-sm font-medium">{stats.totalOrders}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Total Spent</span>
                    <span className="text-sm font-medium">{stats.totalSpent}</span>
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Email Status</span>
                    <span className={cn(
                        "text-sm font-medium",
                        user?.email_verified_at ? "text-emerald-600" : "text-amber-600"
                    )}>
            {user?.email_verified_at ? 'Verified' : 'Unverified'}
          </span>
                </div>

                <div className="pt-2">
                    <Link href="/profile">
                        <Button variant="outline" className="w-full">
                            <User className="h-4 w-4 mr-2" />
                            Manage Profile
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    );
}