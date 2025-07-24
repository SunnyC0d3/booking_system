import { ReactNode } from 'react';

export interface BaseComponentProps {
    className?: string;
    children?: ReactNode;
}

export interface LayoutProps extends BaseComponentProps {
    title?: string;
    description?: string;
}

export interface FormFieldProps {
    name: string;
    label?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    error?: string;
    helperText?: string;
}

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export type LoadingState = 'idle' | 'loading' | 'success' | 'error';

export interface AsyncState<T = any> {
    data: T | null;
    loading: boolean;
    error: string | null;
}

export type Theme = 'light' | 'dark' | 'system';

export interface ThemeConfig {
    theme: Theme;
    setTheme: (theme: Theme) => void;
}

export interface NavItem {
    label: string;
    href: string;
    icon?: ReactNode;
    badge?: string | number;
    children?: NavItem[];
    external?: boolean;
    disabled?: boolean;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
    current?: boolean;
}

export interface ModalProps extends BaseComponentProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    closeOnOverlayClick?: boolean;
    closeOnEsc?: boolean;
}

export interface TableColumn<T = any> {
    key: keyof T | string;
    label: string;
    sortable?: boolean;
    width?: string;
    align?: 'left' | 'center' | 'right';
    render?: (value: any, row: T, index: number) => ReactNode;
}

export interface TableProps<T = any> {
    columns: TableColumn<T>[];
    data: T[];
    loading?: boolean;
    emptyMessage?: string;
    onSort?: (key: string, direction: 'asc' | 'desc') => void;
    sortKey?: string;
    sortDirection?: 'asc' | 'desc';
    onRowClick?: (row: T, index: number) => void;
    className?: string;
}

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

export interface Notification {
    id: string;
    type: NotificationType;
    title: string;
    message?: string;
    duration?: number;
    action?: {
        label: string;
        onClick: () => void;
    };
}

export interface FileUploadProps {
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
    maxFiles?: number;
    onFileSelect: (files: File[]) => void;
    onError?: (error: string) => void;
    disabled?: boolean;
    className?: string;
}

export interface UploadedFile {
    id: string;
    file: File;
    progress: number;
    status: 'pending' | 'uploading' | 'success' | 'error';
    error?: string;
    url?: string;
}

export interface SearchState {
    query: string;
    results: any[];
    loading: boolean;
    error: string | null;
    suggestions: string[];
    filters: Record<string, any>;
    facets: Record<string, any>;
    total: number;
    hasMore: boolean;
}

export interface PaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    showFirst?: boolean;
    showLast?: boolean;
    showPrevNext?: boolean;
    maxVisiblePages?: number;
    className?: string;
}

export interface FilterOption {
    value: string | number;
    label: string;
    count?: number;
    selected?: boolean;
}

export interface FilterGroup {
    key: string;
    label: string;
    type: 'checkbox' | 'radio' | 'range' | 'select';
    options?: FilterOption[];
    min?: number;
    max?: number;
    step?: number;
    placeholder?: string;
}

export interface SortOption {
    value: string;
    label: string;
    direction?: 'asc' | 'desc';
}

export interface ChartDataPoint {
    label: string;
    value: number;
    color?: string;
}

export interface ChartProps {
    data: ChartDataPoint[];
    width?: number;
    height?: number;
    className?: string;
}

export interface AppError {
    message: string;
    code?: string;
    status?: number;
    details?: any;
    timestamp?: string;
}

export interface ErrorBoundaryState {
    hasError: boolean;
    error?: Error;
    errorInfo?: any;
}

export interface ApiClient {
    get: <T = any>(url: string, config?: any) => Promise<T>;
    post: <T = any>(url: string, data?: any, config?: any) => Promise<T>;
    put: <T = any>(url: string, data?: any, config?: any) => Promise<T>;
    patch: <T = any>(url: string, data?: any, config?: any) => Promise<T>;
    delete: <T = any>(url: string, config?: any) => Promise<T>;
}

export interface Store<T = any> {
    state: T;
    actions: Record<string, (...args: any[]) => any>;
}

export interface RouteConfig {
    path: string;
    component: React.ComponentType;
    exact?: boolean;
    requiresAuth?: boolean;
    roles?: string[];
    layout?: React.ComponentType;
    meta?: {
        title?: string;
        description?: string;
        keywords?: string[];
    };
}

export interface Permission {
    id: number;
    name: string;
    description?: string;
}

export interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

export type Nullable<T> = T | null;
export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>;
export type RequireAtLeastOne<T, Keys extends keyof T = keyof T> = Pick<
    T,
    Exclude<keyof T, Keys>
> &
    {
        [K in Keys]-?: Required<Pick<T, K>> & Partial<Pick<T, Exclude<Keys, K>>>;
    }[Keys];

export type DataResponse<T> = {
    data: T;
    message?: string;
    timestamp: string;
};

export interface CustomEvent<T = any> {
    type: string;
    data: T;
    timestamp: number;
}

export interface AppConfig {
    apiUrl: string;
    appName: string;
    version: string;
    environment: 'development' | 'staging' | 'production';
    features: {
        [key: string]: boolean;
    };
    limits: {
        fileUploadSize: number;
        requestTimeout: number;
        retryAttempts: number;
    };
}