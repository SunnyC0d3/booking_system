export interface ApiResponse<T = any> {
    data: T;
    message?: string;
    status: 'success' | 'error';
    meta?: {
        pagination?: PaginationMeta;
        [key: string]: any;
    };
}

export interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status_code: number;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    stripe_customer_id?: string | null;
    password_changed_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    role?: {
        id: number;
        name: 'user' | 'vendor' | 'admin' | 'super admin';
    };
    user_address?: UserAddress;
    vendors?: Vendor[];
    security_info?: {
        requires_password_change: boolean;
        days_until_password_expiry: number;
        security_score: number;
        is_account_locked: boolean;
    };
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface UserAddress {
    id: number;
    address_line1: string;
    address_line2?: string | null;
    city: string;
    state?: string | null;
    country: string;
    postal_code: string;
}

export interface AuthResponse {
    user: User;
    access_token: string;
    token_type: string;
    expires_in: number;
}

export interface LoginRequest {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterRequest {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface ForgotPasswordRequest {
    email: string;
}

export interface ResetPasswordRequest {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface ChangePasswordRequest {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
}

export interface Product {
    id: number;
    name: string;
    description?: string;
    price: number;
    price_formatted: string;
    quantity: number;
    is_in_stock: boolean;
    is_low_stock: boolean;
    stock_status: 'in_stock' | 'out_of_stock' | 'low_stock';
    featured_image?: string | null;
    gallery?: MediaItem[];
    media_count?: {
        featured_image: number;
        gallery: number;
        total: number;
    };
    product_status?: {
        id: number;
        name: string;
    };
    category?: ProductCategory;
    vendor?: Vendor;
    variants?: ProductVariant[];
    tags?: ProductTag[];
    search_metadata?: {
        relevance_score?: number;
        search_score?: number;
        position?: number;
        highlights?: Record<string, string>;
    };
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface ProductCategory {
    id: number;
    name: string;
    parent_id?: number | null;
    parent?: {
        id: number;
        name: string;
        parent_id?: number | null;
    };
}

export interface ProductVariant {
    id: number;
    value: string;
    additional_price?: number | null;
    additional_price_formatted?: string | null;
    total_price: number;
    total_price_formatted: string;
    quantity: number;
    is_available: boolean;
    is_low_stock: boolean;
    product_attribute?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

export interface ProductTag {
    id: number;
    name: string;
    products_count?: number;
}

export interface MediaItem {
    id: number;
    url: string;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    alt_text?: string;
}

export interface Vendor {
    id: number;
    name: string;
    description?: string;
    user?: User;
    logo?: string;
    media?: MediaItem[];
    products_count?: number;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface Cart {
    id: number;
    user_id?: number | null;
    session_id?: string | null;
    total_amount: number;
    total_amount_formatted: string;
    total_items_count: number;
    expires_at?: string | null;
    is_expired: boolean;
    is_empty: boolean;
    items?: CartItem[];
    created_at: string;
    updated_at: string;
}

export interface CartItem {
    id: number;
    product_id: number;
    product_variant_id?: number | null;
    quantity: number;
    price_snapshot: number;
    price_formatted: string;
    line_total: number;
    line_total_formatted: string;
    current_price: number;
    has_price_changed: boolean;
    price_change?: number;
    is_available: boolean;
    available_stock: number;
    product?: {
        id: number;
        name: string;
        description?: string;
        price: number;
        price_formatted: string;
        featured_image?: string;
        status?: string;
    };
    product_variant?: {
        id: number;
        value: string;
        additional_price?: number;
        additional_price_formatted?: string;
        quantity: number;
        product_attribute?: {
            id: number;
            name: string;
        };
    };
    created_at: string;
    updated_at: string;
}

export interface AddToCartRequest {
    product_id: number;
    product_variant_id?: number | null;
    quantity: number;
}

export interface UpdateCartItemRequest {
    quantity: number;
}

export interface Order {
    id: number;
    user?: User;
    total_amount: number;
    total_amount_formatted: string;
    order_items?: OrderItem[];
    payments?: Payment[];
    status?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface OrderItem {
    id: number;
    product?: Product;
    product_variant?: ProductVariant;
    quantity: number;
    price: number;
    price_formatted: string;
    line_total: number;
    line_total_formatted: string;
    order_return?: OrderReturn;
    order?: Order;
    created_at: string;
    updated_at: string;
}

export interface OrderReturn {
    id: number;
    reason: string;
    status?: {
        id: number;
        name: string;
    };
    order_item?: OrderItem;
    order_refunds?: OrderRefund[];
    has_refunds: boolean;
    total_refunded_amount: number;
    total_refunded_amount_formatted: string;
    is_approved: boolean;
    is_completed: boolean;
    created_at: string;
    updated_at: string;
}

export interface OrderRefund {
    id: number;
    amount: number;
    amount_formatted: string;
    processed_at?: string | null;
    notes?: string;
    order_return?: OrderReturn;
    created_at: string;
    updated_at: string;
}

export interface Payment {
    id: number;
    gateway?: string;
    amount: number;
    amount_formatted: string;
    method?: string;
    status: string;
    transaction_reference?: string;
    processed_at?: string | null;
    user?: User;
    order?: Order;
    payment_method?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

export interface ShippingAddress {
    id: number;
    type: 'shipping' | 'billing' | 'both';
    type_label: string;
    name: string;
    company?: string | null;
    line1: string;
    line2?: string | null;
    city: string;
    county?: string | null;
    postcode: string;
    country: string;
    country_name: string;
    phone?: string | null;
    is_default: boolean;
    is_validated: boolean;
    is_uk_address: boolean;
    is_international: boolean;
    full_address: string;
    formatted_address: string;
    normalized_postcode: string;
    needs_validation: boolean;
    validation_data?: any;
    created_at: string;
    updated_at: string;
}

export interface ShippingQuote {
    id: number;
    name: string;
    description?: string;
    carrier: string;
    service_code?: string;
    cost: number;
    cost_formatted: string;
    is_free: boolean;
    estimated_delivery?: string;
    estimated_days_min: number;
    estimated_days_max: number;
    estimated_date_min?: string;
    estimated_date_max?: string;
    delivery_window?: string;
    rate_id: number;
    zone_id: number;
    metadata?: any;
    is_recommended?: boolean;
    savings?: {
        amount: number;
        amount_formatted: string;
    };
}

export interface ProductFilters {
    search?: string;
    category?: string;
    price?: string;
    priceRanges?: string;
    availability?: 'in_stock' | 'out_of_stock' | 'low_stock' | 'available';
    vendors?: string;
    brands?: string;
    tags?: string;
    tag_logic?: 'AND' | 'OR';
    attributes?: string;
    colors?: string;
    sizes?: string;
    material?: string;
    status?: string;
    created_at?: string;
    updated_at?: string;
    page?: number;
    per_page?: number;
    sort?: string;
    include?: string;
}

export interface SearchResponse<T> {
    data: T[];
    meta: {
        pagination: PaginationMeta;
        search: {
            query: string;
            processed_query: string;
            search_time_ms: number;
            total_results: number;
            has_results: boolean;
            quality_score?: number;
            filters_applied: string[];
        };
        facets?: Record<string, any>;
        suggestions?: string[];
    };
    links: {
        first: string;
        last: string;
        prev?: string | null;
        next?: string | null;
    };
}

export interface PaginatedRequest {
    page?: number;
    per_page?: number;
    sort?: string;
    include?: string;
}

export interface TimestampedEntity {
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}