import * as React from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { OrderHistoryList } from '@/components/dashboard/OrderHistory';
import { Order } from '@/types/api';

export const metadata: Metadata = {
    title: 'Order History | Creative Business',
    description: 'View and manage your order history.',
};

// Sample orders data (replace with API call)
const sampleOrders: Order[] = [
    {
        id: 12345,
        total_amount: 12500,
        total_amount_formatted: '£125.00',
        status: {
            id: 3,
            name: 'shipped',
        },
        order_items: [
            {
                id: 1,
                quantity: 50,
                price: 2500,
                price_formatted: '£25.00',
                line_total: 12500,
                line_total_formatted: '£125.00',
                product: {
                    id: 1,
                    name: 'Wedding Invitation Set',
                    description: 'Elegant wedding invitations with gold foil accents',
                    price: 2500,
                    price_formatted: '£25.00',
                    featured_image: '/api/placeholder/300/300',
                    status: 'active',
                },
                product_variant: {
                    id: 1,
                    value: 'Cream & Gold',
                    additional_price: 0,
                    additional_price_formatted: '£0.00',
                    quantity: 100,
                    product_attribute: {
                        id: 1,
                        name: 'Color Theme',
                    },
                },
                created_at: '2024-07-25T10:30:00.000000Z',
                updated_at: '2024-07-25T11:00:00.000000Z',
                deleted_at: null,
            },
            {
                id: 12344,
                total_amount: 8950,
                total_amount_formatted: '£89.50',
                status: {
                    id: 5,
                    name: 'delivered',
                },
                order_items: [
                    {
                        id: 2,
                        quantity: 200,
                        price: 4475,
                        price_formatted: '£44.75',
                        line_total: 8950,
                        line_total_formatted: '£89.50',
                        product: {
                            id: 2,
                            name: 'Custom Business Labels',
                            description: 'Professional waterproof business labels',
                            price: 4475,
                            price_formatted: '£44.75',
                            featured_image: '/api/placeholder/300/300',
                            status: 'active',
                        },
                        created_at: '2024-07-22T14:20:00.000000Z',
                        updated_at: '2024-07-22T14:20:00.000000Z',
                    },
                ],
                payments: [
                    {
                        id: 2,
                        gateway: 'stripe',
                        amount: 8950,
                        amount_formatted: '£89.50',
                        method: 'card',
                        status: 'completed',
                        transaction_reference: 'pi_0987654321',
                        processed_at: '2024-07-22T14:25:00.000000Z',
                        created_at: '2024-07-22T14:20:00.000000Z',
                        updated_at: '2024-07-22T14:25:00.000000Z',
                    },
                ],
                created_at: '2024-07-22T14:20:00.000000Z',
                updated_at: '2024-07-23T09:15:00.000000Z',
                deleted_at: null,
            },
            {
                id: 12343,
                total_amount: 3275,
                total_amount_formatted: '£32.75',
                status: {
                    id: 2,
                    name: 'processing',
                },
                order_items: [
                    {
                        id: 3,
                        quantity: 25,
                        price: 1310,
                        price_formatted: '£13.10',
                        line_total: 3275,
                        line_total_formatted: '£32.75',
                        product: {
                            id: 3,
                            name: 'Birthday Gift Tags',
                            description: 'Colorful birthday gift tags with ribbon',
                            price: 1310,
                            price_formatted: '£13.10',
                            featured_image: '/api/placeholder/300/300',
                            status: 'active',
                        },
                        created_at: '2024-07-20T16:45:00.000000Z',
                        updated_at: '2024-07-20T16:45:00.000000Z',
                    },
                ],
                payments: [
                    {
                        id: 3,
                        gateway: 'stripe',
                        amount: 3275,
                        amount_formatted: '£32.75',
                        method: 'card',
                        status: 'completed',
                        transaction_reference: 'pi_1122334455',
                        processed_at: '2024-07-20T16:50:00.000000Z',
                        created_at: '2024-07-20T16:45:00.000000Z',
                        updated_at: '2024-07-20T16:50:00.000000Z',
                    },
                ],
                created_at: '2024-07-20T16:45:00.000000Z',
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
