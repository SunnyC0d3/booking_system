import { api } from './client';
import type { Order } from '@/types/api';

interface CreateOrderRequest {
    shipping_address_id?: number;
    billing_address_id?: number;
    payment_method_id?: number;
    notes?: string;
}

interface UpdateOrderRequest {
    status?: string;
    notes?: string;
}

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface OrderListResponse extends PaginatedResponse<Order> {}

/**
 * Orders API Client
 * Handles all order-related API operations
 */
export class OrdersApi {
    // Get user's orders
    async getOrders(params?: {
        status?: string;
        page?: number;
        limit?: number;
    }): Promise<OrderListResponse> {
        const queryParams = new URLSearchParams();

        if (params?.status) queryParams.append('status', params.status);
        if (params?.page) queryParams.append('page', String(params.page));
        if (params?.limit) queryParams.append('limit', String(params.limit));

        const response = await api.get<OrderListResponse>(`/orders?${queryParams.toString()}`);
        return response.data;
    }

    // Get single order by ID
    async getOrder(id: number): Promise<Order> {
        const response = await api.get<{ data: Order }>(`/orders/${id}`);
        return response.data.data;
    }

    // Create order from cart
    async createOrderFromCart(data: CreateOrderRequest): Promise<Order> {
        const response = await api.post<{ data: Order }>('/orders/from-cart', data);
        return response.data.data;
    }

    // Update order
    async updateOrder(id: number, data: UpdateOrderRequest): Promise<Order> {
        const response = await api.put<{ data: Order }>(`/orders/${id}`, data);
        return response.data.data;
    }

    // Cancel order
    async cancelOrder(id: number, reason?: string): Promise<Order> {
        const response = await api.post<{ data: Order }>(`/orders/${id}/cancel`, { reason });
        return response.data.data;
    }

    // Get order tracking information
    async getOrderTracking(id: number): Promise<{
        tracking_number: string;
        carrier: string;
        status: string;
        tracking_url?: string;
        events: Array<{
            status: string;
            description: string;
            location?: string;
            timestamp: string;
        }>;
    }> {
        const response = await api.get(`/orders/${id}/tracking`);
        return response.data.data;
    }

    // Request order return
    async requestReturn(orderId: number, items: Array<{
        order_item_id: number;
        quantity: number;
        reason: string;
    }>, notes?: string): Promise<{
        return_id: string;
        status: string;
        return_label_url?: string;
    }> {
        const response = await api.post(`/orders/${orderId}/return`, {
            items,
            notes
        });
        return response.data.data;
    }
}

// Create singleton instance
export const ordersApi = new OrdersApi();