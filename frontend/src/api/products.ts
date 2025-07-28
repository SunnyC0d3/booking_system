import { api } from './client';
import {
    Product,
    ProductCategory,
    ProductListResponse,
    ProductSearchParams,
    ProductSearchResult,
    ProductReview,
    ReviewStats,
    ApiResponse,
} from '@/types/product';

/**
 * Product API functions
 */
export class ProductApi {
    // Product CRUD
    async getProducts(params?: ProductSearchParams): Promise<ProductListResponse> {
        const response = await api.get<ProductListResponse>('/products', { params });
        return response.data;
    }

    async getProduct(slug: string): Promise<Product> {
        const response = await api.get<Product>(`/products/${slug}`);
        return response.data;
    }

    async getFeaturedProducts(limit: number = 8): Promise<Product[]> {
        const response = await api.get<Product[]>('/products/featured', {
            params: { limit }
        });
        return response.data;
    }

    async getRelatedProducts(productId: number, limit: number = 4): Promise<Product[]> {
        const response = await api.get<Product[]>(`/products/${productId}/related`, {
            params: { limit }
        });
        return response.data;
    }

    async getRecentlyViewedProducts(limit: number = 6): Promise<Product[]> {
        const response = await api.get<Product[]>('/products/recently-viewed', {
            params: { limit }
        });
        return response.data;
    }

    // Categories
    async getCategories(params?: {
        parent_id?: number;
        featured?: boolean;
        with_products_count?: boolean;
    }): Promise<ProductCategory[]> {
        const response = await api.get<ProductCategory[]>('/categories', { params });
        return response.data;
    }

    async getCategory(slug: string): Promise<ProductCategory> {
        const response = await api.get<ProductCategory>(`/categories/${slug}`);
        return response.data;
    }

    async getCategoryProducts(
        categorySlug: string,
        params?: ProductSearchParams
    ): Promise<ProductListResponse> {
        const response = await api.get<ProductListResponse>(
            `/categories/${categorySlug}/products`,
            { params }
        );
        return response.data;
    }

    // Search
    async searchProducts(
        query: string,
        params?: ProductSearchParams
    ): Promise<ProductSearchResult> {
        const searchParams = { q: query, ...params };
        const response = await api.get<ProductSearchResult>('/products/search', {
            params: searchParams
        });
        return response.data;
    }

    async getSearchSuggestions(query: string, limit: number = 5): Promise<string[]> {
        const response = await api.get<string[]>('/products/search/suggestions', {
            params: { q: query, limit }
        });
        return response.data;
    }

    async getPopularSearches(limit: number = 10): Promise<string[]> {
        const response = await api.get<string[]>('/products/search/popular', {
            params: { limit }
        });
        return response.data;
    }

    // Filters
    async getProductFilters(params?: ProductSearchParams): Promise<any> {
        const response = await api.get('/products/filters', { params });
        return response.data;
    }

    // Reviews
    async getProductReviews(
        productId: number,
        params?: {
            page?: number;
            per_page?: number;
            sort?: 'newest' | 'oldest' | 'rating_high' | 'rating_low' | 'helpful';
            rating?: number;
        }
    ): Promise<ApiResponse<ProductReview[]>> {
        const response = await api.get(`/products/${productId}/reviews`, { params });
        return response;
    }

    async getReviewStats(productId: number): Promise<ReviewStats> {
        const response = await api.get<ReviewStats>(`/products/${productId}/reviews/stats`);
        return response.data;
    }

    async submitReview(
        productId: number,
        review: {
            rating: number;
            title: string;
            content: string;
            images?: File[];
        }
    ): Promise<ProductReview> {
        const formData = new FormData();
        formData.append('rating', review.rating.toString());
        formData.append('title', review.title);
        formData.append('content', review.content);

        if (review.images) {
            review.images.forEach((image, index) => {
                formData.append(`images[${index}]`, image);
            });
        }

        const response = await api.post<ProductReview>(
            `/products/${productId}/reviews`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        );
        return response.data;
    }

    async markReviewHelpful(reviewId: number): Promise<{ helpful_count: number }> {
        const response = await api.post<{ helpful_count: number }>(
            `/reviews/${reviewId}/helpful`
        );
        return response.data;
    }

    // Wishlist
    async addToWishlist(productId: number): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/wishlist', {
            product_id: productId
        });
        return response.data;
    }

    async removeFromWishlist(productId: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/wishlist/${productId}`);
        return response.data;
    }

    async getWishlist(): Promise<Product[]> {
        const response = await api.get<Product[]>('/wishlist');
        return response.data;
    }

    async isInWishlist(productId: number): Promise<{ in_wishlist: boolean }> {
        const response = await api.get<{ in_wishlist: boolean }>(`/wishlist/check/${productId}`);
        return response.data;
    }

    // Compare
    async addToCompare(productId: number): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>('/compare', {
            product_id: productId
        });
        return response.data;
    }

    async removeFromCompare(productId: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/compare/${productId}`);
        return response.data;
    }

    async getCompareList(): Promise<Product[]> {
        const response = await api.get<Product[]>('/compare');
        return response.data;
    }

    async clearCompareList(): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>('/compare');
        return response.data;
    }

    // Recently Viewed
    async addToRecentlyViewed(productId: number): Promise<void> {
        await api.post('/products/recently-viewed', {
            product_id: productId
        });
    }

    // Product Availability
    async checkAvailability(
        productId: number,
        variantId?: number,
        quantity: number = 1
    ): Promise<{
        available: boolean;
        stock_level: number;
        max_quantity: number;
        estimated_restock?: string;
    }> {
        const response = await api.post(`/products/${productId}/check-availability`, {
            variant_id: variantId,
            quantity
        });
        return response.data;
    }

    // Price Check
    async getProductPrice(
        productId: number,
        variantId?: number,
        quantity: number = 1
    ): Promise<{
        price: number;
        price_formatted: string;
        compare_price?: number;
        compare_price_formatted?: string;
        discount_amount?: number;
        discount_percentage?: number;
        bulk_pricing?: Array<{
            min_quantity: number;
            price: number;
            price_formatted: string;
        }>;
    }> {
        const response = await api.post(`/products/${productId}/price`, {
            variant_id: variantId,
            quantity
        });
        return response.data;
    }

    // Admin functions (if user has admin role)
    async createProduct(product: Partial<Product>): Promise<Product> {
        const response = await api.post<Product>('/admin/products', product);
        return response.data;
    }

    async updateProduct(id: number, product: Partial<Product>): Promise<Product> {
        const response = await api.patch<Product>(`/admin/products/${id}`, product);
        return response.data;
    }

    async deleteProduct(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/admin/products/${id}`);
        return response.data;
    }

    async bulkUpdateProducts(
        ids: number[],
        updates: Partial<Product>
    ): Promise<{ message: string; updated_count: number }> {
        const response = await api.patch('/admin/products/bulk', {
            product_ids: ids,
            updates
        });
        return response.data;
    }

    // Analytics
    async getProductAnalytics(
        productId: number,
        period: 'day' | 'week' | 'month' | 'year' = 'month'
    ): Promise<{
        views: number;
        sales: number;
        revenue: number;
        conversion_rate: number;
        chart_data: Array<{
            date: string;
            views: number;
            sales: number;
            revenue: number;
        }>;
    }> {
        const response = await api.get(`/admin/products/${productId}/analytics`, {
            params: { period }
        });
        return response.data;
    }

    // Cache utilities
    async invalidateCache(productId?: number): Promise<{ message: string }> {
        const endpoint = productId
            ? `/admin/products/${productId}/cache/invalidate`
            : '/admin/products/cache/invalidate';

        const response = await api.post<{ message: string }>(endpoint);
        return response.data;
    }
}

// Export singleton instance
export const productApi = new ProductApi();