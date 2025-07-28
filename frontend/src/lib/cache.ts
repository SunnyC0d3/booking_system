import { LRUCache } from 'lru-cache';

// Cache configuration interface
interface CacheConfig {
    maxSize: number;
    ttl: number; // Time to live in milliseconds
    updateAgeOnGet?: boolean;
    allowStale?: boolean;
}

// Default cache configurations
const CACHE_CONFIGS = {
    api: { maxSize: 100, ttl: 5 * 60 * 1000 }, // 5 minutes
    images: { maxSize: 50, ttl: 30 * 60 * 1000 }, // 30 minutes
    user: { maxSize: 10, ttl: 10 * 60 * 1000 }, // 10 minutes
    products: { maxSize: 200, ttl: 15 * 60 * 1000 }, // 15 minutes
    static: { maxSize: 50, ttl: 60 * 60 * 1000 }, // 1 hour
} as const;

// Generic cache class
class Cache<T = any> {
    private cache: LRUCache<string, CacheEntry<T>>;
    private name: string;

    constructor(config: CacheConfig, name: string) {
        this.name = name;
        this.cache = new LRUCache({
            max: config.maxSize,
            ttl: config.ttl,
            updateAgeOnGet: config.updateAgeOnGet ?? true,
            allowStale: config.allowStale ?? false,
        });
    }

    set(key: string, value: T, customTtl?: number): void {
        const entry: CacheEntry<T> = {
            data: value,
            timestamp: Date.now(),
            ttl: customTtl || this.cache.ttl,
        };

        this.cache.set(key, entry, { ttl: customTtl });

        if (process.env.NODE_ENV === 'development') {
            console.log(`Cache [${this.name}] SET: ${key}`);
        }
    }

    get(key: string): T | null {
        const entry = this.cache.get(key);

        if (!entry) {
            if (process.env.NODE_ENV === 'development') {
                console.log(`Cache [${this.name}] MISS: ${key}`);
            }
            return null;
        }

        // Check if entry is still valid
        if (Date.now() - entry.timestamp > entry.ttl) {
            this.cache.delete(key);
            return null;
        }

        if (process.env.NODE_ENV === 'development') {
            console.log(`Cache [${this.name}] HIT: ${key}`);
        }

        return entry.data;
    }

    has(key: string): boolean {
        return this.cache.has(key);
    }

    delete(key: string): boolean {
        return this.cache.delete(key);
    }

    clear(): void {
        this.cache.clear();
        if (process.env.NODE_ENV === 'development') {
            console.log(`Cache [${this.name}] CLEARED`);
        }
    }

    size(): number {
        return this.cache.size;
    }

    // Get cache statistics
    getStats() {
        return {
            size: this.cache.size,
            max: this.cache.max,
            ttl: this.cache.ttl,
        };
    }
}

interface CacheEntry<T> {
    data: T;
    timestamp: number;
    ttl: number;
}

// Cache instances
export const caches = {
    api: new Cache(CACHE_CONFIGS.api, 'API'),
    images: new Cache(CACHE_CONFIGS.images, 'Images'),
    user: new Cache(CACHE_CONFIGS.user, 'User'),
    products: new Cache(CACHE_CONFIGS.products, 'Products'),
    static: new Cache(CACHE_CONFIGS.static, 'Static'),
};

// Higher-order function for caching API calls
export function withCache<T extends any[], R>(
    cacheInstance: Cache<R>,
    keyGenerator: (...args: T) => string,
    ttl?: number
) {
    return function cachingWrapper(
        originalFunction: (...args: T) => Promise<R>
    ) {
        return async (...args: T): Promise<R> => {
            const cacheKey = keyGenerator(...args);

            // Try to get from cache first
            const cachedResult = cacheInstance.get(cacheKey);
            if (cachedResult !== null) {
                return cachedResult;
            }

            // If not in cache, execute function and cache result
            try {
                const result = await originalFunction(...args);
                cacheInstance.set(cacheKey, result, ttl);
                return result;
            } catch (error) {
                // Don't cache errors
                throw error;
            }
        };
    };
}

// API-specific caching utilities
export const apiCache = {
    // Cache GET requests
    get: withCache(
        caches.api,
        (url: string, params?: any) =>
            `GET:${url}${params ? ':' + JSON.stringify(params) : ''}`
    ),

    // Cache user data
    user: withCache(
        caches.user,
        (userId: string) => `user:${userId}`
    ),

    // Cache product data
    product: withCache(
        caches.products,
        (productId: string) => `product:${productId}`
    ),

    // Cache product lists
    productList: withCache(
        caches.products,
        (filters?: any) => `products:${JSON.stringify(filters || {})}`
    ),
};

// Browser storage cache (for persistence)
export class PersistentCache {
    private prefix: string;
    private storage: Storage;

    constructor(prefix: string = 'app_cache', useSessionStorage = false) {
        this.prefix = prefix;
        this.storage = useSessionStorage ? sessionStorage : localStorage;
    }

    set(key: string, value: any, ttl?: number): void {
        if (typeof window === 'undefined') return;

        const entry = {
            data: value,
            timestamp: Date.now(),
            ttl: ttl || 0,
        };

        try {
            this.storage.setItem(`${this.prefix}:${key}`, JSON.stringify(entry));
        } catch (error) {
            console.warn('Failed to set cache item:', error);
        }
    }

    get<T = any>(key: string): T | null {
        if (typeof window === 'undefined') return null;

        try {
            const item = this.storage.getItem(`${this.prefix}:${key}`);
            if (!item) return null;

            const entry = JSON.parse(item);

            // Check if expired
            if (entry.ttl > 0 && Date.now() - entry.timestamp > entry.ttl) {
                this.storage.removeItem(`${this.prefix}:${key}`);
                return null;
            }

            return entry.data;
        } catch (error) {
            console.warn('Failed to get cache item:', error);
            return null;
        }
    }

    remove(key: string): void {
        if (typeof window === 'undefined') return;
        this.storage.removeItem(`${this.prefix}:${key}`);
    }

    clear(): void {
        if (typeof window === 'undefined') return;

        const keys = Object.keys(this.storage);
        keys.forEach(key => {
            if (key.startsWith(this.prefix)) {
                this.storage.removeItem(key);
            }
        });
    }
}

// Pre-configured persistent caches
export const persistentCaches = {
    user: new PersistentCache('user', false),
    settings: new PersistentCache('settings', false),
    cart: new PersistentCache('cart', false),
    session: new PersistentCache('session', true),
};

// Cache invalidation utilities
export class CacheInvalidator {
    private static patterns: Map<string, RegExp[]> = new Map();

    static addPattern(cacheType: keyof typeof caches, pattern: RegExp): void {
        const existing = this.patterns.get(cacheType) || [];
        existing.push(pattern);
        this.patterns.set(cacheType, existing);
    }

    static invalidate(cacheType: keyof typeof caches, key?: string): void {
        if (key) {
            caches[cacheType].delete(key);
        } else {
            caches[cacheType].clear();
        }
    }

    static invalidatePattern(cacheType: keyof typeof caches, pattern: string): void {
        const cache = caches[cacheType];
        const keys = Array.from((cache as any).cache.keys());

        keys.forEach(key => {
            if (key.includes(pattern)) {
                cache.delete(key);
            }
        });
    }

    // Invalidate related caches when data changes
    static onUserUpdate(userId: string): void {
        this.invalidatePattern('user', userId);
        this.invalidatePattern('api', `user:${userId}`);
    }

    static onProductUpdate(productId: string): void {
        this.invalidatePattern('products', productId);
        this.invalidate('products'); // Clear all product lists
    }

    static onOrderUpdate(): void {
        this.invalidate('user'); // Clear user data (order history)
        this.invalidatePattern('api', 'orders');
    }
}

// Performance monitoring
export function getCachePerformance() {
    return Object.entries(caches).reduce((acc, [name, cache]) => {
        acc[name] = cache.getStats();
        return acc;
    }, {} as Record<string, any>);
}

// Initialize cache cleanup
if (typeof window !== 'undefined') {
    // Clean up expired items every 5 minutes
    setInterval(() => {
        Object.values(caches).forEach(cache => {
            // Force garbage collection by accessing size
            cache.size();
        });
    }, 5 * 60 * 1000);
}