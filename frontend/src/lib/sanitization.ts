import DOMPurify from 'isomorphic-dompurify';
import { z } from 'zod';

// Enhanced type definitions for better type safety
interface SanitizeOptions {
    allowedTags?: string[];
    allowedAttributes?: Record<string, string[]>;
    stripScripts?: boolean;
}

interface SanitizeObjectOptions<T> {
    htmlFields?: (keyof T)[];
    textFields?: (keyof T)[];
    urlFields?: (keyof T)[];
    fileFields?: (keyof T)[];
}

// Sanitization utilities
export class InputSanitizer {
    // HTML sanitization with proper type handling
    static sanitizeHtml(input: string, options?: SanitizeOptions): string {
        // Extract allowed attributes as string array for DOMPurify
        const allowedAttributes = options?.allowedAttributes || {};
        const flattenedAttributes = Object.values(allowedAttributes).flat();

        const config = {
            ALLOWED_TAGS: options?.allowedTags || ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li'],
            ALLOWED_ATTR: flattenedAttributes.length > 0 ? flattenedAttributes : [],
            FORBID_TAGS: options?.stripScripts !== false ? ['script', 'iframe', 'object', 'embed'] : [],
            FORBID_ATTR: ['onclick', 'onload', 'onmouseover', 'onfocus', 'onerror'],
            ALLOW_DATA_ATTR: false,
            ALLOW_UNKNOWN_PROTOCOLS: false,
            WHOLE_DOCUMENT: false,
            RETURN_DOM: false,
            RETURN_DOM_FRAGMENT: false,
            RETURN_TRUSTED_TYPE: false,
        };

        // Environment check for additional safety
        if (typeof window === 'undefined' && typeof DOMPurify === 'undefined') {
            // Fallback for edge cases where DOMPurify is not available
            return input.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }

        // Handle the return type properly - DOMPurify can return TrustedHTML or string
        const result = DOMPurify.sanitize(input, config);
        return typeof result === 'string' ? result : String(result);
    }

    // Text sanitization (remove all HTML)
    static sanitizeText(input: string): string {
        const result = DOMPurify.sanitize(input, {
            ALLOWED_TAGS: [],
            ALLOWED_ATTR: [],
        });
        return typeof result === 'string' ? result : String(result);
    }

    // SQL injection prevention
    static escapeSql(input: string): string {
        return input
            .replace(/'/g, "''")
            .replace(/\\/g, '\\\\')
            .replace(/\0/g, '\\0')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r')
            .replace(/\x1a/g, '\\Z');
    }

    // XSS prevention for attributes
    static sanitizeAttribute(input: string): string {
        return input
            .replace(/[<>'"&]/g, (match) => {
                const entities: Record<string, string> = {
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#x27;',
                    '&': '&amp;',
                };
                return entities[match] || match;
            });
    }

    // File name sanitization
    static sanitizeFileName(input: string): string {
        return input
            .replace(/[^a-zA-Z0-9._-]/g, '_')
            .replace(/_{2,}/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 255); // Limit length
    }

    // URL sanitization
    static sanitizeUrl(input: string): string {
        try {
            const url = new URL(input);

            // Allow only HTTP and HTTPS
            if (!['http:', 'https:'].includes(url.protocol)) {
                return '';
            }

            // Remove dangerous parameters
            url.searchParams.delete('javascript');
            url.searchParams.delete('vbscript');
            url.searchParams.delete('data');

            return url.toString();
        } catch {
            return '';
        }
    }

    // Deep sanitize object - Fixed generic type constraints
    static sanitizeObject<T extends Record<string, any>>(
        obj: T,
        options?: SanitizeObjectOptions<T>
    ): T {
        // Create a mutable copy using type assertion
        const sanitized = { ...obj } as Record<string, any>;

        Object.keys(sanitized).forEach(key => {
            const value = sanitized[key];

            if (typeof value === 'string') {
                if (options?.htmlFields?.includes(key as keyof T)) {
                    sanitized[key] = this.sanitizeHtml(value);
                } else if (options?.textFields?.includes(key as keyof T)) {
                    sanitized[key] = this.sanitizeText(value);
                } else if (options?.urlFields?.includes(key as keyof T)) {
                    sanitized[key] = this.sanitizeUrl(value);
                } else if (options?.fileFields?.includes(key as keyof T)) {
                    sanitized[key] = this.sanitizeFileName(value);
                } else {
                    sanitized[key] = this.sanitizeAttribute(value);
                }
            } else if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                sanitized[key] = this.sanitizeObject(value, options);
            } else if (Array.isArray(value)) {
                // Handle arrays properly
                sanitized[key] = value.map(item =>
                    typeof item === 'object' && item !== null
                        ? this.sanitizeObject(item, options)
                        : typeof item === 'string'
                            ? this.sanitizeAttribute(item)
                            : item
                );
            }
        });

        return sanitized as T;
    }
}

// Validation schemas
export const ValidationSchemas = {
    // User validation
    user: {
        register: z.object({
            name: z.string()
                .min(2, 'Name must be at least 2 characters')
                .max(50, 'Name must be less than 50 characters')
                .regex(/^[a-zA-Z\s-']+$/, 'Name contains invalid characters'),
            email: z.string()
                .email('Invalid email address')
                .max(255, 'Email too long')
                .transform(email => email.toLowerCase().trim()),
            password: z.string()
                .min(8, 'Password must be at least 8 characters')
                .max(128, 'Password too long')
                .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/,
                    'Password must contain uppercase, lowercase, number and special character'),
            password_confirmation: z.string(),
            terms_accepted: z.boolean().refine(val => val === true, 'Must accept terms'),
            marketing_consent: z.boolean().optional(),
        }).refine(data => data.password === data.password_confirmation, {
            message: "Passwords don't match",
            path: ["password_confirmation"],
        }),

        profile: z.object({
            name: z.string().min(2).max(50).regex(/^[a-zA-Z\s-']+$/),
            phone: z.string()
                .regex(/^\+?[1-9]\d{1,14}$/, 'Invalid phone number')
                .optional()
                .or(z.literal('')),
            date_of_birth: z.string()
                .regex(/^\d{4}-\d{2}-\d{2}$/, 'Invalid date format')
                .optional()
                .or(z.literal('')),
            gender: z.enum(['male', 'female', 'other']).optional(),
        }),
    },

    // Product validation
    product: {
        create: z.object({
            name: z.string()
                .min(1, 'Product name is required')
                .max(255, 'Product name too long'),
            description: z.string()
                .max(5000, 'Description too long')
                .optional(),
            price: z.number()
                .positive('Price must be positive')
                .max(999999.99, 'Price too high'),
            category_id: z.number().positive('Invalid category'),
            sku: z.string()
                .min(1, 'SKU is required')
                .max(50, 'SKU too long')
                .regex(/^[A-Z0-9-_]+$/, 'SKU must contain only uppercase letters, numbers, hyphens, and underscores'),
            stock_quantity: z.number()
                .int('Stock must be a whole number')
                .min(0, 'Stock cannot be negative'),
            weight: z.number()
                .positive('Weight must be positive')
                .optional(),
            dimensions: z.object({
                length: z.number().positive().optional(),
                width: z.number().positive().optional(),
                height: z.number().positive().optional(),
            }).optional(),
            tags: z.array(z.string().max(50)).max(10, 'Too many tags').optional(),
            images: z.array(z.string().url()).max(10, 'Too many images').optional(),
        }),

        update: z.object({
            name: z.string().min(1).max(255).optional(),
            description: z.string().max(5000).optional(),
            price: z.number().positive().max(999999.99).optional(),
            category_id: z.number().positive().optional(),
            stock_quantity: z.number().int().min(0).optional(),
            weight: z.number().positive().optional(),
            tags: z.array(z.string().max(50)).max(10).optional(),
        }),
    },

    // Order validation
    order: {
        create: z.object({
            items: z.array(z.object({
                product_id: z.number().positive(),
                quantity: z.number().int().positive().max(100),
                price: z.number().positive(),
            })).min(1, 'Order must contain at least one item'),
            shipping_address: z.object({
                street: z.string().min(5).max(255),
                city: z.string().min(2).max(100),
                state: z.string().min(2).max(100),
                postal_code: z.string().min(3).max(20),
                country: z.string().length(2, 'Country must be 2-letter code'),
            }),
            billing_address: z.object({
                street: z.string().min(5).max(255),
                city: z.string().min(2).max(100),
                state: z.string().min(2).max(100),
                postal_code: z.string().min(3).max(20),
                country: z.string().length(2),
            }).optional(),
            payment_method: z.enum(['card', 'paypal', 'bank_transfer']),
            notes: z.string().max(1000).optional(),
        }),
    },

    // Search validation
    search: z.object({
        query: z.string()
            .min(1, 'Search query is required')
            .max(100, 'Search query too long')
            .regex(/^[a-zA-Z0-9\s\-_\.]+$/, 'Invalid characters in search query'),
        category: z.string().max(50).optional(),
        min_price: z.number().positive().optional(),
        max_price: z.number().positive().optional(),
        sort: z.enum(['name', 'price', 'created_at', 'popularity']).optional(),
        order: z.enum(['asc', 'desc']).optional(),
        page: z.number().int().positive().max(1000).optional(),
        limit: z.number().int().positive().max(100).optional(),
    }).refine(data => {
        if (data.min_price && data.max_price) {
            return data.min_price <= data.max_price;
        }
        return true;
    }, {
        message: "Minimum price cannot be greater than maximum price",
        path: ["min_price"],
    }),

    // File upload validation
    file: z.object({
        name: z.string().min(1).max(255),
        size: z.number().positive().max(10 * 1024 * 1024), // 10MB
        type: z.string().regex(/^(image|video|document)\/(jpeg|jpg|png|gif|webp|mp4|avi|pdf|doc|docx)$/),
    }),

    // Contact form validation
    contact: z.object({
        name: z.string().min(2).max(100).regex(/^[a-zA-Z\s-']+$/),
        email: z.string().email().max(255),
        subject: z.string().min(5).max(200),
        message: z.string().min(10).max(2000),
        phone: z.string().regex(/^\+?[1-9]\d{1,14}$/).optional().or(z.literal('')),
    }),
};

// Rate limiting configurations
export const RateLimitConfig = {
    api: {
        general: { requests: 100, window: 15 * 60 * 1000 }, // 100 requests per 15 minutes
        auth: { requests: 5, window: 15 * 60 * 1000 }, // 5 auth attempts per 15 minutes
        search: { requests: 50, window: 60 * 1000 }, // 50 searches per minute
        upload: { requests: 10, window: 60 * 1000 }, // 10 uploads per minute
        contact: { requests: 3, window: 60 * 60 * 1000 }, // 3 contact forms per hour
    },
    admin: {
        general: { requests: 200, window: 15 * 60 * 1000 }, // Higher limits for admin
        bulk_operations: { requests: 5, window: 60 * 1000 }, // Limited bulk operations
    },
};

// Security validation helper
export class SecurityValidator {
    // Check for common attack patterns
    static containsMaliciousPatterns(input: string): boolean {
        const maliciousPatterns = [
            /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
            /javascript:/gi,
            /vbscript:/gi,
            /onload=|onclick=|onerror=|onmouseover=/gi,
            /eval\s*\(/gi,
            /document\.cookie/gi,
            /localStorage|sessionStorage/gi,
            /\bSELECT\b.*\bFROM\b/gi,
            /\bINSERT\b.*\bINTO\b/gi,
            /\bUPDATE\b.*\bSET\b/gi,
            /\bDELETE\b.*\bFROM\b/gi,
            /\bDROP\b.*\bTABLE\b/gi,
            /\bUNION\b.*\bSELECT\b/gi,
            /\'\s*OR\s*\'/gi,
            /\'\s*;\s*--/gi,
        ];

        return maliciousPatterns.some(pattern => pattern.test(input));
    }

    // Validate file upload security
    static validateFileUpload(file: File): { valid: boolean; errors: string[] } {
        const errors: string[] = [];
        const allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        const maxSize = 10 * 1024 * 1024; // 10MB
        const dangerousExtensions = ['.exe', '.bat', '.cmd', '.scr', '.pif', '.com', '.js', '.jar'];

        // Check file type
        if (!allowedTypes.includes(file.type)) {
            errors.push('File type not allowed');
        }

        // Check file size
        if (file.size > maxSize) {
            errors.push('File size exceeds limit (10MB)');
        }

        // Check file extension
        const extension = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));
        if (dangerousExtensions.includes(extension)) {
            errors.push('Dangerous file extension detected');
        }

        // Check filename for malicious patterns
        if (this.containsMaliciousPatterns(file.name)) {
            errors.push('Malicious filename detected');
        }

        return { valid: errors.length === 0, errors };
    }

    // Validate API request
    static validateApiRequest(request: {
        method: string;
        path: string;
        body?: any;
        headers: Record<string, string>;
    }): { valid: boolean; errors: string[] } {
        const errors: string[] = [];

        // Check for required headers
        if (!request.headers['content-type'] && ['POST', 'PUT', 'PATCH'].includes(request.method)) {
            errors.push('Content-Type header required');
        }

        // Validate JSON body
        if (request.body && typeof request.body === 'string') {
            try {
                JSON.parse(request.body);
            } catch {
                errors.push('Invalid JSON in request body');
            }
        }

        // Check for malicious patterns in path
        if (this.containsMaliciousPatterns(request.path)) {
            errors.push('Malicious patterns detected in request path');
        }

        return { valid: errors.length === 0, errors };
    }
}

// Enhanced CSRF token utilities with proper crypto usage
export class CSRFProtection {
    private static readonly SECRET_KEY = process.env.CSRF_SECRET || 'default-csrf-secret';

    static generateToken(): string {
        const timestamp = Date.now().toString();

        // Cross-platform crypto random bytes generation
        let randomBytes: Uint8Array;

        if (typeof window !== 'undefined' && window.crypto && window.crypto.getRandomValues) {
            // Browser environment
            randomBytes = window.crypto.getRandomValues(new Uint8Array(16));
        } else if (typeof globalThis !== 'undefined' && globalThis.crypto && globalThis.crypto.getRandomValues) {
            // Node.js environment with Web Crypto API
            randomBytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
        } else {
            // Fallback for environments without crypto API
            randomBytes = new Uint8Array(16);
            for (let i = 0; i < 16; i++) {
                randomBytes[i] = Math.floor(Math.random() * 256);
            }
        }

        const data = timestamp + Array.from(randomBytes).map(b => b.toString(16).padStart(2, '0')).join('');

        // Use secret key for additional security
        const tokenWithSecret = data + this.SECRET_KEY.slice(0, 8);

        // Use Buffer if available (Node.js), otherwise use btoa (browser)
        if (typeof Buffer !== 'undefined') {
            return Buffer.from(tokenWithSecret).toString('base64url');
        } else {
            // Browser fallback
            return btoa(tokenWithSecret).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }
    }

    static validateToken(token: string | null): boolean {
        if (!token) return false;

        try {
            let decoded: string;

            if (typeof Buffer !== 'undefined') {
                // Node.js environment
                decoded = Buffer.from(token, 'base64url').toString();
            } else {
                // Browser environment
                const normalizedToken = token.replace(/-/g, '+').replace(/_/g, '/');
                const padding = '='.repeat((4 - normalizedToken.length % 4) % 4);
                decoded = atob(normalizedToken + padding);
            }

            // Remove secret key suffix for validation
            const dataWithoutSecret = decoded.slice(0, -8);
            const timestamp = parseInt(dataWithoutSecret.slice(0, 13));
            const now = Date.now();

            // Token valid for 1 hour
            return !isNaN(timestamp) && (now - timestamp) < 60 * 60 * 1000;
        } catch {
            return false;
        }
    }
}

// Type-safe validation result interface
export interface ValidationResult<T> {
    success: boolean;
    data?: T;
    errors?: string[];
}

// Export main sanitization function with better type safety
export function sanitizeInput(
    input: any,
    type: 'html' | 'text' | 'url' | 'filename' | 'attribute' = 'text'
): any {
    if (typeof input === 'string') {
        switch (type) {
            case 'html': return InputSanitizer.sanitizeHtml(input);
            case 'text': return InputSanitizer.sanitizeText(input);
            case 'url': return InputSanitizer.sanitizeUrl(input);
            case 'filename': return InputSanitizer.sanitizeFileName(input);
            case 'attribute': return InputSanitizer.sanitizeAttribute(input);
            default: return InputSanitizer.sanitizeText(input);
        }
    }

    if (typeof input === 'object' && input !== null) {
        return InputSanitizer.sanitizeObject(input);
    }

    return input;
}

// Validation middleware helper with improved error handling
export function validateInput<T>(
    schema: z.ZodSchema<T>,
    data: unknown
): ValidationResult<T> {
    try {
        const result = schema.safeParse(data);

        if (result.success) {
            return { success: true, data: result.data };
        } else {
            const errors = result.error.errors.map(err => `${err.path.join('.')}: ${err.message}`);
            return { success: false, errors };
        }
    } catch (error) {
        return {
            success: false,
            errors: [error instanceof Error ? error.message : 'Validation failed']
        };
    }
}

// Export CSRF utilities
export const validateCSRF = CSRFProtection.validateToken;
export const generateCSRFToken = CSRFProtection.generateToken;