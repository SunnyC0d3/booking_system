import { api } from './client';
import {
    User,
    UpdateUserRequest,
    ChangePasswordRequest,
    Address,
    UserPreferences,
    ApiResponse
} from '@/types/api';

/**
 * Users API Client
 * Handles all user-related API operations
 */
export class UsersApi {
    // Get current user profile
    async getProfile(): Promise<User> {
        const response = await api.get<{ data: User }>('/user/profile');
        return response.data.data;
    }

    // Update user profile
    async updateProfile(data: UpdateUserRequest): Promise<User> {
        const response = await api.put<{ data: User }>('/user/profile', data);
        return response.data.data;
    }

    // Change password
    async changePassword(data: ChangePasswordRequest): Promise<void> {
        await api.post('/user/change-password', data);
    }

    // Upload avatar
    async uploadAvatar(file: File): Promise<{ avatar_url: string }> {
        const formData = new FormData();
        formData.append('avatar', file);

        const response = await api.post<{ data: { avatar_url: string } }>(
            '/user/avatar',
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        );
        return response.data.data;
    }

    // Get user addresses
    async getAddresses(): Promise<Address[]> {
        const response = await api.get<{ data: Address[] }>('/user/addresses');
        return response.data.data;
    }

    // Add new address
    async addAddress(address: Omit<Address, 'id' | 'user_id' | 'created_at' | 'updated_at'>): Promise<Address> {
        const response = await api.post<{ data: Address }>('/user/addresses', address);
        return response.data.data;
    }

    // Update address
    async updateAddress(id: number, address: Partial<Address>): Promise<Address> {
        const response = await api.put<{ data: Address }>(`/user/addresses/${id}`, address);
        return response.data.data;
    }

    // Delete address
    async deleteAddress(id: number): Promise<void> {
        await api.delete(`/user/addresses/${id}`);
    }

    // Set default address
    async setDefaultAddress(id: number, type: 'billing' | 'shipping'): Promise<void> {
        await api.post(`/user/addresses/${id}/set-default`, { type });
    }

    // Get user preferences
    async getPreferences(): Promise<UserPreferences> {
        const response = await api.get<{ data: UserPreferences }>('/user/preferences');
        return response.data.data;
    }

    // Update user preferences
    async updatePreferences(preferences: Partial<UserPreferences>): Promise<UserPreferences> {
        const response = await api.put<{ data: UserPreferences }>('/user/preferences', preferences);
        return response.data.data;
    }

    // Delete user account
    async deleteAccount(password: string): Promise<void> {
        await api.post('/user/delete-account', { password });
    }

    // Get user activity/audit log
    async getActivityLog(params?: {
        page?: number;
        limit?: number;
        type?: string;
    }): Promise<{
        data: Array<{
            id: number;
            type: string;
            description: string;
            ip_address: string;
            user_agent: string;
            created_at: string;
        }>;
        meta: {
            pagination: {
                current_page: number;
                last_page: number;
                per_page: number;
                total: number;
            };
        };
    }> {
        const queryParams = new URLSearchParams();

        if (params?.page) queryParams.append('page', String(params.page));
        if (params?.limit) queryParams.append('limit', String(params.limit));
        if (params?.type) queryParams.append('type', params.type);

        const response = await api.get(`/user/activity?${queryParams.toString()}`);
        return response.data;
    }
}

// Create singleton instance
export const usersApi = new UsersApi();