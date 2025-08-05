'use client'

import * as React from 'react';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { OrderHistoryList } from '@/components/dashboard/OrderHistory';
import { Order } from '@/types/api';

// Sample data - replace with actual API call
const sampleOrders: Order[] = [
    {
        id: 1,
        total_amount: 149.99,
        total_amount_formatted: '£149.99',
        status: { id: 1, name: 'completed' },
        order_items: [
            {
                id: 1,
                quantity: 2,
                price: 74.99,
                price_formatted: '£74.99',
                line_total: 149.98,
                line_total_formatted: '£149.98',
                product: {
                    id: 1,
                    name: 'Custom Wedding Invitations',
                    description: 'Beautiful custom wedding invitations',
                    price: 74.99,
                    price_formatted: '£74.99',
                    quantity: 100,
                    is_in_stock: true,
                    is_low_stock: false,
                    stock_status: 'in_stock' as const,
                    created_at: '2024-07-01T10:30:00.000000Z',
                    updated_at: '2024-07-01T10:30:00.000000Z',
                },
                created_at: '2024-07-20T10:30:00.000000Z',
                updated_at: '2024-07-21T10:30:00.000000Z',
            },
        ],
        created_at: '2024-07-20T10:30:00.000000Z',
        updated_at: '2024-07-21T10:30:00.000000Z',
        deleted_at: null,
    },
];

function OrdersPage() {
    const [orders, setOrders] = React.useState<Order[]>(sampleOrders);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    // Simulate loading orders from API
    React.useEffect(() => {
        const loadOrders = async () => {
            setLoading(true);
            try {
                // Here you would call your API
                // const response = await orderApi.getOrders();
                // setOrders(response.data);

                // Simulate API delay
                await new Promise(resolve => setTimeout(resolve, 1000));
                setOrders(sampleOrders);
            } catch (err: any) {
                setError(err.message || 'Failed to load orders');
            } finally {
                setLoading(false);
            }
        };

        loadOrders();
    }, []);

    const handleRefresh = () => {
        setError(null);
        // Reload orders
        window.location.reload();
    };

    return (
        <RouteGuard requireAuth>
            <DashboardLayout
                title="Order History"
                description="View and manage your past orders"
            >
                <OrderHistoryList
                    orders={orders}
                    loading={loading}
                    error={error}
                    onRefresh={handleRefresh}
                />
            </DashboardLayout>
        </RouteGuard>
    );
}

export default OrdersPage;