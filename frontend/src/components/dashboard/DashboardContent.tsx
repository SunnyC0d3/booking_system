'use client';

import * as React from 'react';
import { Suspense } from 'react';
import DashboardLayout from '@/components/layout/DashboardLayout';
import DashboardStats from '@/components/dashboard/DashboardStats';
import RecentOrders from '@/components/dashboard/RecentOrders';
import QuickActions from '@/components/dashboard/QuickActions';
import AccountSummary from '@/components/dashboard/AccountSummary';
import RecentActivity from '@/components/dashboard/RecentActivity';
import CartItemsClient from '@/components/dashboard/CartItemsClient';
import { useAuth } from '@/stores/authStore';

const DashboardStatsLoading = () => (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-24 bg-muted animate-pulse rounded-lg" />
        ))}
    </div>
);

const RecentOrdersLoading = () => (
    <div className="space-y-4">
        <div className="h-6 bg-muted animate-pulse rounded w-1/4" />
        {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="h-16 bg-muted animate-pulse rounded" />
        ))}
    </div>
);

const ActivityLoading = () => (
    <div className="space-y-3">
        <div className="h-6 bg-muted animate-pulse rounded w-1/3" />
        {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-12 bg-muted animate-pulse rounded" />
        ))}
    </div>
);

class DashboardErrorBoundary extends React.Component<
    { children: React.ReactNode; fallback?: React.ReactNode },
    { hasError: boolean }
> {
    constructor(props: { children: React.ReactNode; fallback?: React.ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('Dashboard component error:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return this.props.fallback || (
                <div className="p-6 text-center">
                    <h3 className="text-lg font-medium text-foreground mb-2">
                        Something went wrong
                    </h3>
                    <p className="text-muted-foreground mb-4">
                        We encountered an error loading this section.
                    </p>
                    <button
                        onClick={() => this.setState({ hasError: false })}
                        className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
                    >
                        Try Again
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}

function DashboardInner() {
    const { user } = useAuth();

    if (!user) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        );
    }

    const welcomeMessage = React.useMemo(() => {
        const firstName = user.name?.split(' ')[0] || 'there';
        return `Welcome back, ${firstName}!`;
    }, [user.name]);

    return (
        <DashboardLayout
            title={welcomeMessage}
            description="Here's what's happening with your creative projects."
        >
            <div className="space-y-8">
                <DashboardErrorBoundary fallback={<DashboardStatsLoading />}>
                    <Suspense fallback={<DashboardStatsLoading />}>
                        <CartItemsClient>
                            {(cartItemCount) => (
                                <DashboardStats userId={user.id} cartItemCount={cartItemCount} />
                            )}
                        </CartItemsClient>
                    </Suspense>
                </DashboardErrorBoundary>

                <div className="grid lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-2">
                        <DashboardErrorBoundary fallback={<RecentOrdersLoading />}>
                            <Suspense fallback={<RecentOrdersLoading />}>
                                <RecentOrders userId={user.id} limit={3} />
                            </Suspense>
                        </DashboardErrorBoundary>
                    </div>

                    <div className="space-y-6">
                        <DashboardErrorBoundary>
                            <Suspense fallback={<div className="h-32 bg-muted animate-pulse rounded" />}>
                                <QuickActions />
                            </Suspense>
                        </DashboardErrorBoundary>

                        <DashboardErrorBoundary>
                            <Suspense fallback={<div className="h-40 bg-muted animate-pulse rounded" />}>
                                <AccountSummary userId={user.id} />
                            </Suspense>
                        </DashboardErrorBoundary>
                    </div>
                </div>

                <DashboardErrorBoundary fallback={<ActivityLoading />}>
                    <Suspense fallback={<ActivityLoading />}>
                        <RecentActivity userId={user.id} limit={4} />
                    </Suspense>
                </DashboardErrorBoundary>
            </div>
        </DashboardLayout>
    );
}

export default function DashboardContent() {
    return <DashboardInner />;
}