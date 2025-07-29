export interface DigitalProduct {
    id: number;
    name: string;
    description: string;
    product_type: 'digital' | 'hybrid';
    requires_license: boolean;
    supported_platforms: string[];
    system_requirements?: string;
    latest_version: string;
    download_limit: number;
    download_expiry_days: number;
    auto_delivery: boolean;
    price: number;
    price_formatted: string;
    files?: ProductFile[];
    created_at: string;
    updated_at: string;
}

export interface ProductFile {
    id: number;
    name: string;
    filename: string;
    original_filename: string;
    file_type: string;
    mime_type: string;
    file_size: number;
    file_size_formatted: string;
    version: string;
    description: string;
    is_primary: boolean;
    download_limit?: number;
    created_at: string;
    updated_at: string;
}

export interface DownloadAccess {
    id: number;
    access_token: string;
    user_id: number;
    product_id: number;
    download_limit: number;
    downloads_remaining: number;
    expires_at: string;
    status: 'active' | 'expired' | 'revoked';
    ip_address?: string;
    user_agent?: string;
    created_at: string;
    updated_at: string;
    product: DigitalProduct;
}

export interface LicenseKey {
    id: number;
    key: string;
    product_id: number;
    user_id: number;
    license_type: string;
    max_activations: number;
    activation_count: number;
    expires_at?: string;
    status: 'active' | 'expired' | 'revoked';
    last_validated_at?: string;
    last_used_at?: string;
    validation_count: number;
    created_at: string;
    updated_at: string;
    product: DigitalProduct;
    activations?: LicenseActivation[];
}

export interface LicenseActivation {
    id: number;
    license_key_id: number;
    device_identifier: string;
    device_name?: string;
    activated_at: string;
    last_seen_at: string;
    ip_address?: string;
    user_agent?: string;
}

export interface DigitalProductStatistics {
    total_products: number;
    total_downloads: number;
    active_licenses: number;
    expired_licenses: number;
    expiring_soon: number;
    downloads_this_month: number;
    downloads_this_week: number;
    total_file_size: string;
    average_downloads_per_product: number;
}

export interface DownloadAttempt {
    id: string;
    download_access_id: number;
    status: 'in_progress' | 'completed' | 'failed' | 'cancelled';
    progress: number;
    started_at: string;
    completed_at?: string;
    error_message?: string;
    ip_address: string;
    user_agent: string;
}

// API Response Types
export interface DigitalProductsResponse {
    data: {
        products: DigitalProduct[];
        pagination: {
            current_page: number;
            total: number;
            per_page: number;
            last_page: number;
        };
    };
    message: string;
    status: number;
}

export interface DigitalLibraryResponse {
    data: {
        download_accesses: DownloadAccess[];
        license_keys: LicenseKey[];
        statistics: DigitalProductStatistics;
    };
    message: string;
    status: number;
}

export interface DownloadInfoResponse {
    data: {
        file: ProductFile;
        access: DownloadAccess;
        product: DigitalProduct;
    };
    message: string;
    status: number;
}

export interface LicenseValidationResponse {
    data: {
        valid: boolean;
        license: LicenseKey;
        remaining_activations: number;
        expires_at?: string;
    };
    message: string;
    status: number;
}