'use client'

import * as React from 'react';
import { Metadata } from 'next';
import DashboardLayout from '@/components/layout/DashboardLayout';
import DashboardStats from '@/components/dashboard/DashboardStats';
import RecentOrders from '@/components/dashboard/RecentOrders';
import QuickActions from '@/components/dashboard/QuickActions';
import AccountSummary from '@/components/dashboard/AccountSummary';
import RecentActivity from '@/components/dashboard/RecentActivity';
import CartItemsClient from '@/components/dashboard/CartItemsClient';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { useAuth } from '@/stores/authStore';

export const metadata: Metadata = {
    title: 'Dashboard | Creative Business',
    description: 'Manage your orders, track progress, and explore new products.',
};

function DashboardContent() {
    return (
        <RouteGuard requireAuth>
            <DashboardInner />
        </RouteGuard>
    );
}

function DashboardInner() {
    const { user } = useAuth();

    if (!user) {
        return null;
    }

    const welcomeMessage = `Welcome back, ${user.name?.split(' ')[0] || 'there'}!`;

    return (
        <DashboardLayout
            title={welcomeMessage}
            description="Here's what's happening with your creative projects."
        >
            <div className="space-y-8">
                <CartItemsClient>
                    {(cartItemCount) => (
                        <DashboardStats userId={user.id} cartItemCount={cartItemCount} />
                    )}
                </CartItemsClient>

                <div className="grid lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-2">
                        <RecentOrders userId={user.id} limit={3} />
                    </div>

                    <div className="space-y-6">
                        <QuickActions />
                        <AccountSummary userId={user.id} />
                    </div>
                </div>

                <RecentActivity userId={user.id} limit={4} />
            </div>
        </DashboardLayout>
    );
}

export default function DashboardPage() {
    return <DashboardContent />;
}