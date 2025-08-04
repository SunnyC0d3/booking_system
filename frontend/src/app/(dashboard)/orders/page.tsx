'use client'

import * as React from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { OrderHistoryList } from '@/components/dashboard/OrderHistory';
import { Order } from '@/types/api';

// Sample data - replace with actual API call
const sampleOrders: Order[] = [
    {
        id: 'ORD-2024-001',
        user_id: 1,
        status: 'completed',
        payment_status: 'paid',
        total: 149.99,
        currency: 'GBP',
        shipping_address: {
            name: 'John Doe',
            address_line_1: '123 Main Street',
            address_line_2: '',
            city: 'London',
            county: 'Greater London',
            postcode: 'SW1A 1AA',
            country: 'UK',
        },
        items: [
            {
                id: 1,
                product_id: 1,
                quantity: 2,
                price: 74.99,
                product_name: 'Custom Wedding Invitations',
                product_sku: 'WED-INV-001',
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
                showBreadcrumbs
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