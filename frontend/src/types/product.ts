import { z } from 'zod';

// Core Product Types
export interface Product {
    id: number;
    name: string;
    slug: string;
    description: string;
    short_description?: string;
    sku: string;
    price: number;
    price_formatted: string;
    compare_price?: number;
    compare_price_formatted?: string;
    cost_price?: number;
    track_inventory: boolean;
    inventory_quantity?: number;
    allow_backorder: boolean;
    weight?: number;
    dimensions?: ProductDimensions;
    status: ProductStatus;
    visibility: ProductVisibility;
    featured: boolean;
    featured_image?: string;
    gallery: ProductImage[];
    category?: ProductCategory;
    categories: ProductCategory[];
    tags: ProductTag[];
    variants: ProductVariant[];
    attributes: ProductAttribute[];
    seo?: ProductSEO;
    reviews_count: number;
    reviews_average: number;
    created_at: string;
    updated_at: string;
}

export interface ProductDimensions {
    length?: number;
    width?: number;
    height?: number;
    unit: 'cm' | 'in';
}

export interface ProductImage {
    id: number;
    url: string;
    alt_text?: string;
    sort_order: number;
    is_featured: boolean;
}

export interface ProductCategory {
    id: number;
    name: string;
    slug: string;
    description?: string;
    image?: string;
    parent_id?: number;
    parent?: ProductCategory;
    children?: ProductCategory[];
    products_count: number;
    sort_order: number;
    is_featured: boolean;
    seo?: CategorySEO;
    created_at: string;
    updated_at: string;
}

export interface ProductTag {
    id: number;
    name: string;
    slug: string;
    description?: string;
    products_count: number;
}

export interface ProductVariant {
    id: number;
    product_id: number;
    name: string;
    value: string;
    price_adjustment: number;
    price_adjustment_type: 'fixed' | 'percentage';
    sku?: string;
    inventory_quantity?: number;
    weight?: number;
    image?: string;
    sort_order: number;
    is_default: boolean;
    attribute: ProductAttribute;
}

export interface ProductAttribute {
    id: number;
    name: string;
    slug: string;
    type: AttributeType;
    values: AttributeValue[];
    is_required: boolean;
    is_filterable: boolean;
    sort_order: number;
}

export interface AttributeValue {
    id: number;
    value: string;
    color_code?: string;
    image?: string;
    sort_order: number;
}

export type AttributeType = 'text' | 'color' | 'image' | 'dropdown' | 'radio' | 'checkbox';
export type ProductStatus = 'active' | 'draft' | 'archived';
export type ProductVisibility = 'public' | 'private' | 'password';

export interface ProductSEO {
    meta_title?: string;
    meta_description?: string;
    meta_keywords?: string[];
    og_title?: string;
    og_description?: string;
    og_image?: string;
}

export interface CategorySEO {
    meta_title?: string;
    meta_description?: string;
    meta_keywords?: string[];
    og_title?: string;
    og_description?: string;
    og_image?: string;
}

// Product Listing & Search Types
export interface ProductListResponse {
    data: Product[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: ProductFilters;
    sort_options: SortOption[];
}

export interface ProductFilters {
    categories: FilterCategory[];
    price_range: {
        min: number;
        max: number;
        current_min?: number;
        current_max?: number;
    };
    attributes: FilterAttribute[];
    tags: FilterTag[];
    availability: FilterAvailability[];
    rating: FilterRating[];
}

export interface FilterCategory {
    id: number;
    name: string;
    slug: string;
    count: number;
    selected: boolean;
    children?: FilterCategory[];
}

export interface FilterAttribute {
    id: number;
    name: string;
    slug: string;
    type: AttributeType;
    values: FilterAttributeValue[];
}

export interface FilterAttributeValue {
    id: number;
    value: string;
    count: number;
    selected: boolean;
    color_code?: string;
    image?: string;
}

export interface FilterTag {
    id: number;
    name: string;
    slug: string;
    count: number;
    selected: boolean;
}

export interface FilterAvailability {
    key: 'in_stock' | 'out_of_stock' | 'backorder';
    label: string;
    count: number;
    selected: boolean;
}

export interface FilterRating {
    rating: number;
    count: number;
    selected: boolean;
}

export interface SortOption {
    key: string;
    label: string;
    direction: 'asc' | 'desc';
    selected: boolean;
}

// Search Types
export interface ProductSearchParams {
    q?: string;
    category?: string | string[];
    tags?: string | string[];
    price_min?: number;
    price_max?: number;
    attributes?: Record<string, string | string[]>;
    availability?: string | string[];
    rating?: number;
    sort?: string;
    page?: number;
    per_page?: number;
    featured?: boolean;
}

export interface ProductSearchResult {
    products: ProductListResponse;
    suggestions: string[];
    facets: SearchFacets;
    query_info: {
        query: string;
        total_results: number;
        search_time: number;
        corrected_query?: string;
    };
}

export interface SearchFacets {
    categories: SearchFacet[];
    brands: SearchFacet[];
    price_ranges: SearchPriceRange[];
    attributes: SearchAttributeFacet[];
}

export interface SearchFacet {
    key: string;
    label: string;
    count: number;
}

export interface SearchPriceRange {
    min: number;
    max: number;
    count: number;
    label: string;
}

export interface SearchAttributeFacet {
    attribute: string;
    values: SearchFacet[];
}

// Product Review Types
export interface ProductReview {
    id: number;
    product_id: number;
    user_id: number;
    user: {
        id: number;
        name: string;
        avatar?: string;
    };
    rating: number;
    title: string;
    content: string;
    images: ReviewImage[];
    helpful_count: number;
    verified_purchase: boolean;
    status: 'approved' | 'pending' | 'rejected';
    created_at: string;
    updated_at: string;
}

export interface ReviewImage {
    id: number;
    url: string;
    alt_text?: string;
}

export interface ReviewStats {
    total_reviews: number;
    average_rating: number;
    rating_distribution: {
        [key: number]: number;
    };
}

// Validation Schemas
export const productSearchSchema = z.object({
    q: z.string().optional(),
    category: z.union([z.string(), z.array(z.string())]).optional(),
    tags: z.union([z.string(), z.array(z.string())]).optional(),
    price_min: z.number().min(0).optional(),
    price_max: z.number().min(0).optional(),
    attributes: z.record(z.union([z.string(), z.array(z.string())])).optional(),
    availability: z.union([z.string(), z.array(z.string())]).optional(),
    rating: z.number().min(1).max(5).optional(),
    sort: z.string().optional(),
    page: z.number().min(1).optional(),
    per_page: z.number().min(1).max(100).optional(),
    featured: z.boolean().optional(),
});

export const reviewSubmissionSchema = z.object({
    rating: z.number().min(1).max(5),
    title: z.string().min(1, 'Review title is required').max(100),
    content: z.string().min(10, 'Review must be at least 10 characters').max(1000),
    images: z.array(z.instanceof(File)).max(5).optional(),
});

// Component Props Types
export interface ProductCardProps {
    product: Product;
    showQuickAdd?: boolean;
    showWishlist?: boolean;
    showCompare?: boolean;
    layout?: 'grid' | 'list';
    priority?: boolean;
    onQuickAdd?: (product: Product) => void;
    onWishlistToggle?: (product: Product) => void;
    onCompareToggle?: (product: Product) => void;
    className?: string;
}

export interface ProductGridProps {
    products: Product[];
    loading?: boolean;
    layout?: 'grid' | 'list';
    columns?: {
        sm?: number;
        md?: number;
        lg?: number;
        xl?: number;
    };
    showFilters?: boolean;
    showSort?: boolean;
    emptyMessage?: string;
    className?: string;
}

export interface ProductFiltersProps {
    filters: ProductFilters;
    selectedFilters: ProductSearchParams;
    onFilterChange: (filters: ProductSearchParams) => void;
    onClearFilters: () => void;
    loading?: boolean;
    className?: string;
}

export interface ProductSortProps {
    options: SortOption[];
    selected?: string;
    onSortChange: (sort: string) => void;
    className?: string;
}

// Store Types
export interface ProductState {
    products: Product[];
    categories: ProductCategory[];
    filters: ProductFilters | null;
    searchResults: ProductSearchResult | null;
    currentProduct: Product | null;
    relatedProducts: Product[];
    isLoading: boolean;
    error: string | null;
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    } | null;
}

export interface ProductActions {
    // Fetching
    fetchProducts: (params?: ProductSearchParams) => Promise<void>;
    fetchProduct: (slug: string) => Promise<void>;
    fetchCategories: () => Promise<void>;
    fetchRelatedProducts: (productId: number) => Promise<void>;
    searchProducts: (query: string, params?: ProductSearchParams) => Promise<void>;

    // Filtering & Sorting
    applyFilters: (filters: ProductSearchParams) => Promise<void>;
    clearFilters: () => void;

    // Utilities
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
    clearError: () => void;

    // Cache management
    clearCache: () => void;
}