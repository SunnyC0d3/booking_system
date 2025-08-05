import { api } from './client';
import type {
    Product,
    ProductFilters,
    ProductSort
} from '@/types/api';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface ProductListResponse extends PaginatedResponse<Product> {}

/**
 * Products API Client
 * Handles all product-related API operations
 */
export class ProductsApi {
    // Get all products with filters and pagination
    async getProducts(params?: {
        filters?: Partial<ProductFilters>;
        sort?: ProductSort;
        page?: number;
        limit?: number;
    }): Promise<ProductListResponse> {
        const queryParams = new URLSearchParams();

        if (params?.filters) {
            Object.entries(params.filters).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    queryParams.append(key, String(value));
                }
            });
        }

        if (params?.sort) queryParams.append('sort', params.sort);
        if (params?.page) queryParams.append('page', String(params.page));
        if (params?.limit) queryParams.append('limit', String(params.limit));

        const response = await api.get<ProductListResponse>(`/products?${queryParams.toString()}`);
        return response.data;
    }

    // Get single product by ID
    async getProduct(id: number): Promise<Product> {
        const response = await api.get<{ data: Product }>(`/products/${id}`);
        return response.data.data;
    }

    // Search products
    async searchProducts(query: string, filters?: Partial<ProductFilters>): Promise<ProductListResponse> {
        return this.getProducts({
            filters: { ...filters, search: query }
        });
    }

    // Get featured products
    async getFeaturedProducts(limit = 12): Promise<Product[]> {
        const response = await this.getProducts({
            filters: { featured: true } as Partial<ProductFilters>,
            limit
        });
        return response.data;
    }

    // Get products by category
    async getProductsByCategory(categoryId: number, params?: {
        sort?: ProductSort;
        page?: number;
        limit?: number;
    }): Promise<ProductListResponse> {
        return this.getProducts({
            filters: { category: String(categoryId) } as Partial<ProductFilters>,
            ...params
        });
    }

    // Get related products
    async getRelatedProducts(productId: number, limit = 8): Promise<Product[]> {
        const response = await api.get<{ data: Product[] }>(`/products/${productId}/related?limit=${limit}`);
        return response.data.data;
    }
}

// Create singleton instance
export const productsApi = new ProductsApi();