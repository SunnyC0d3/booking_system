'use client'

// Performance monitoring utilities
export class PerformanceMonitor {
    private static metrics = new Map<string, number>();
    private static observers = new Map<string, PerformanceObserver>();

    // Web Vitals monitoring
    static initWebVitals() {
        if (typeof window === 'undefined') return;

        // Core Web Vitals
        this.observeMetric('largest-contentful-paint', (entries) => {
            const lastEntry = entries[entries.length - 1];
            this.logMetric('LCP', lastEntry.startTime, 'ms');
            this.sendToAnalytics('lcp', lastEntry.startTime);
        });

        this.observeMetric('first-input', (entries) => {
            const firstEntry = entries[0];
            const delay = firstEntry.processingStart - firstEntry.startTime;
            this.logMetric('FID', delay, 'ms');
            this.sendToAnalytics('fid', delay);
        });

        this.observeMetric('layout-shift', (entries) => {
            let cumulativeScore = 0;
            entries.forEach(entry => {
                if (!entry.hadRecentInput) {
                    cumulativeScore += entry.value;
                }
            });
            this.logMetric('CLS', cumulativeScore);
            this.sendToAnalytics('cls', cumulativeScore);
        });

        // Additional metrics
        this.observeMetric('navigation', (entries) => {
            const nav = entries[0];
            this.logMetric('TTFB', nav.responseStart - nav.requestStart, 'ms');
            this.logMetric('DOM Load', nav.domContentLoadedEventEnd - nav.domContentLoadedEventStart, 'ms');
            this.logMetric('Page Load', nav.loadEventEnd - nav.loadEventStart, 'ms');
        });

        // Resource timing
        this.observeMetric('resource', (entries) => {
            entries.forEach(entry => {
                if (entry.initiatorType === 'img' && entry.duration > 1000) {
                    this.logMetric('Slow Image', entry.duration, 'ms', entry.name);
                }
                if (entry.initiatorType === 'script' && entry.duration > 500) {
                    this.logMetric('Slow Script', entry.duration, 'ms', entry.name);
                }
            });
        });
    }

    private static observeMetric(type: string, callback: (entries: any[]) => void) {
        try {
            const observer = new PerformanceObserver((list) => {
                callback(list.getEntries());
            });
            observer.observe({ type, buffered: true });
            this.observers.set(type, observer);
        } catch (error) {
            console.warn(`Failed to observe ${type}:`, error);
        }
    }

    private static logMetric(name: string, value: number, unit = '', resource = '') {
        if (process.env.NODE_ENV === 'development') {
            console.log(`Performance [${name}]: ${value.toFixed(2)}${unit}${resource ? ` (${resource})` : ''}`);
        }
        this.metrics.set(`${name}${resource ? `_${resource.split('/').pop()}` : ''}`, value);
    }

    private static sendToAnalytics(metric: string, value: number) {
        // Send to Google Analytics
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', 'web_vital', {
                metric_name: metric,
                metric_value: Math.round(value),
                custom_parameter: true,
            });
        }

        // Send to custom analytics endpoint
        if (process.env.NODE_ENV === 'production') {
            fetch('/api/analytics/performance', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    metric,
                    value: Math.round(value),
                    timestamp: Date.now(),
                    url: window.location.pathname,
                    userAgent: navigator.userAgent,
                }),
            }).catch(() => {}); // Silently fail
        }
    }

    // Manual performance measurement
    static startTiming(label: string): () => void {
        const startTime = performance.now();
        return () => {
            const duration = performance.now() - startTime;
            this.logMetric(label, duration, 'ms');
            return duration;
        };
    }

    // Component render timing
    static measureComponent(componentName: string) {
        return (target: any, propertyName: string, descriptor: PropertyDescriptor) => {
            const method = descriptor.value;
            descriptor.value = function (...args: any[]) {
                const endTiming = PerformanceMonitor.startTiming(`${componentName}.${propertyName}`);
                const result = method.apply(this, args);
                endTiming();
                return result;
            };
        };
    }

    // Get all metrics
    static getMetrics(): Record<string, number> {
        return Object.fromEntries(this.metrics);
    }

    // Clean up observers
    static cleanup() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
        this.metrics.clear();
    }
}

// React performance hooks
export function usePerformanceMonitor(componentName: string) {
    const renderCount = React.useRef(0);
    const startTime = React.useRef<number>();

    React.useEffect(() => {
        renderCount.current++;
        startTime.current = performance.now();

        return () => {
            if (startTime.current) {
                const duration = performance.now() - startTime.current;
                if (process.env.NODE_ENV === 'development') {
                    console.log(`${componentName} render #${renderCount.current}: ${duration.toFixed(2)}ms`);
                }
            }
        };
    });

    const logEvent = React.useCallback((eventName: string, data?: Record<string, any>) => {
        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('event', eventName, {
                component: componentName,
                ...data,
            });
        }
    }, [componentName]);

    return { logEvent, renderCount: renderCount.current };
}

// Error tracking
export class ErrorTracker {
    private static errors: Array<{
        error: Error;
        timestamp: number;
        context?: Record<string, any>;
    }> = [];

    static init() {
        if (typeof window === 'undefined') return;

        // Catch JavaScript errors
        window.addEventListener('error', (event) => {
            this.captureError(new Error(event.message), {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                type: 'javascript',
            });
        });

        // Catch unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.captureError(new Error(event.reason), {
                type: 'unhandled_promise',
            });
        });

        // Resource loading errors
        window.addEventListener('error', (event) => {
            if (event.target !== window) {
                this.captureError(new Error(`Resource failed to load: ${(event.target as any)?.src || 'unknown'}`), {
                    type: 'resource',
                    element: event.target?.tagName?.toLowerCase(),
                });
            }
        }, true);
    }

    static captureError(error: Error, context?: Record<string, any>) {
        const errorData = {
            error,
            timestamp: Date.now(),
            context: {
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString(),
                ...context,
            },
        };

        this.errors.push(errorData);

        // Log to console in development
        if (process.env.NODE_ENV === 'development') {
            console.error('Error captured:', error, context);
        }

        // Send to error tracking service
        this.sendErrorReport(errorData);
    }

    private static sendErrorReport(errorData: any) {
        if (process.env.NODE_ENV === 'production') {
            fetch('/api/errors', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: errorData.error.message,
                    stack: errorData.error.stack,
                    context: errorData.context,
                }),
            }).catch(() => {}); // Silently fail
        }
    }

    static getErrors() {
        return [...this.errors];
    }

    static clearErrors() {
        this.errors.length = 0;
    }
}

// Bundle analysis utilities
export class BundleAnalyzer {
    static analyzeChunks() {
        if (typeof window === 'undefined') return;

        const scripts = Array.from(document.querySelectorAll('script[src]'));
        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));

        const analysis = {
            scripts: scripts.map(script => ({
                src: script.getAttribute('src'),
                async: script.hasAttribute('async'),
                defer: script.hasAttribute('defer'),
                size: 0, // Would need to be measured
            })),
            styles: styles.map(link => ({
                href: link.getAttribute('href'),
                size: 0, // Would need to be measured
            })),
            totalScripts: scripts.length,
            totalStyles: styles.length,
        };

        console.log('Bundle Analysis:', analysis);
        return analysis;
    }

    static measureResourceSizes() {
        if (typeof window === 'undefined') return;

        const resources = performance.getEntriesByType('resource');
        const resourceSizes = resources.map(resource => ({
            name: resource.name,
            size: (resource as any).transferSize || 0,
            type: resource.initiatorType,
            duration: resource.duration,
        }));

        const totalSize = resourceSizes.reduce((sum, resource) => sum + resource.size, 0);

        console.log('Resource Sizes:', {
            resources: resourceSizes,
            totalSize: `${(totalSize / 1024).toFixed(2)} KB`,
            byType: resourceSizes.reduce((acc, resource) => {
                acc[resource.type] = (acc[resource.type] || 0) + resource.size;
                return acc;
            }, {} as Record<string, number>),
        });

        return resourceSizes;
    }
}

// Memory monitoring
export class MemoryMonitor {
    private static measurements: Array<{
        timestamp: number;
        memory: any;
    }> = [];

    static startMonitoring(interval = 10000) {
        if (typeof window === 'undefined' || !('memory' in performance)) return;

        const measureMemory = () => {
            const memory = (performance as any).memory;
            this.measurements.push({
                timestamp: Date.now(),
                memory: {
                    usedJSHeapSize: memory.usedJSHeapSize,
                    totalJSHeapSize: memory.totalJSHeapSize,
                    jsHeapSizeLimit: memory.jsHeapSizeLimit,
                },
            });

            // Keep only last 100 measurements
            if (this.measurements.length > 100) {
                this.measurements = this.measurements.slice(-100);
            }

            // Log warning if memory usage is high
            const memoryUsagePercent = (memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100;
            if (memoryUsagePercent > 80) {
                console.warn(`High memory usage: ${memoryUsagePercent.toFixed(1)}%`);
            }
        };

        measureMemory();
        return setInterval(measureMemory, interval);
    }

    static getMemoryTrend() {
        if (this.measurements.length < 2) return null;

        const recent = this.measurements.slice(-10);
        const first = recent[0];
        const last = recent[recent.length - 1];

        return {
            trend: last.memory.usedJSHeapSize > first.memory.usedJSHeapSize ? 'increasing' : 'decreasing',
            change: last.memory.usedJSHeapSize - first.memory.usedJSHeapSize,
            measurements: recent,
        };
    }
}

// Network monitoring
export class NetworkMonitor {
    static getConnectionInfo() {
        if (typeof navigator === 'undefined' || !('connection' in navigator)) {
            return null;
        }

        const connection = (navigator as any).connection;
        return {
            effectiveType: connection.effectiveType,
            downlink: connection.downlink,
            rtt: connection.rtt,
            saveData: connection.saveData,
        };
    }

    static monitorNetworkChanges(callback: (info: any) => void) {
        if (typeof navigator === 'undefined' || !('connection' in navigator)) {
            return () => {};
        }

        const connection = (navigator as any).connection;
        const handleChange = () => callback(this.getConnectionInfo());

        connection.addEventListener('change', handleChange);
        return () => connection.removeEventListener('change', handleChange);
    }
}

// Initialize monitoring in production
export function initializeMonitoring() {
    if (typeof window === 'undefined') return;

    // Initialize performance monitoring
    PerformanceMonitor.initWebVitals();

    // Initialize error tracking
    ErrorTracker.init();

    // Start memory monitoring in development
    if (process.env.NODE_ENV === 'development') {
        MemoryMonitor.startMonitoring();
    }

    // Log initial performance
    window.addEventListener('load', () => {
        setTimeout(() => {
            console.log('Performance Metrics:', PerformanceMonitor.getMetrics());
            BundleAnalyzer.measureResourceSizes();
        }, 1000);
    });

    // Cleanup on unload
    window.addEventListener('beforeunload', () => {
        PerformanceMonitor.cleanup();
    });
}

// React hooks for monitoring
export function useNetworkStatus() {
    const [networkInfo, setNetworkInfo] = React.useState(NetworkMonitor.getConnectionInfo());

    React.useEffect(() => {
        const cleanup = NetworkMonitor.monitorNetworkChanges(setNetworkInfo);
        return cleanup;
    }, []);

    return networkInfo;
}

export function useMemoryWarning(threshold = 80) {
    const [isHighMemory, setIsHighMemory] = React.useState(false);

    React.useEffect(() => {
        if (typeof window === 'undefined' || !('memory' in performance)) return;

        const checkMemory = () => {
            const memory = (performance as any).memory;
            const usagePercent = (memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100;
            setIsHighMemory(usagePercent > threshold);
        };

        const interval = setInterval(checkMemory, 5000);
        return () => clearInterval(interval);
    }, [threshold]);

    return isHighMemory;
}