import { NextConfig } from 'next';

const nextConfig: NextConfig = {
    /* Basic Configuration */
    reactStrictMode: true,
    swcMinify: true,
    poweredByHeader: false,

    /* Experimental Features */
    experimental: {
        optimizePackageImports: [
            'lucide-react',
            '@radix-ui/react-icons',
            'framer-motion',
            'date-fns',
            'lodash'
        ],
        turbo: {
            rules: {
                '*.svg': {
                    loaders: ['@svgr/webpack'],
                    as: '*.js',
                },
            },
        },
    },

    /* Images Configuration */
    images: {
        formats: ['image/webp', 'image/avif'],
        deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
        imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
        domains: [
            'localhost',
            '127.0.0.1',
        ],
        remotePatterns: [
            {
                protocol: 'https',
                hostname: 'your-api-domain.com',
                port: '',
                pathname: '/storage/**',
            },
            {
                protocol: 'https',
                hostname: 'cdn.your-domain.com',
                port: '',
                pathname: '/**',
            },
            {
                protocol: 'http',
                hostname: 'localhost',
                port: '8000',
                pathname: '/storage/**',
            },
        ],
        dangerouslyAllowSVG: true,
        contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    },

    /* Security Headers */
    async headers() {
        return [
            {
                source: '/(.*)',
                headers: [
                    // Security Headers
                    {
                        key: 'X-DNS-Prefetch-Control',
                        value: 'on'
                    },
                    {
                        key: 'Strict-Transport-Security',
                        value: 'max-age=63072000; includeSubDomains; preload'
                    },
                    {
                        key: 'X-XSS-Protection',
                        value: '1; mode=block'
                    },
                    {
                        key: 'X-Frame-Options',
                        value: 'DENY'
                    },
                    {
                        key: 'X-Content-Type-Options',
                        value: 'nosniff'
                    },
                    {
                        key: 'Referrer-Policy',
                        value: 'origin-when-cross-origin'
                    },
                    {
                        key: 'Content-Security-Policy',
                        value: [
                            "default-src 'self'",
                            "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://vercel.live https://va.vercel-scripts.com",
                            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                            "font-src 'self' https://fonts.gstatic.com",
                            "img-src 'self' data: https: blob:",
                            "media-src 'self' https:",
                            "connect-src 'self' https: wss:",
                            "worker-src 'self' blob:",
                            "child-src 'self'",
                            "object-src 'none'",
                            "base-uri 'self'",
                            "form-action 'self'",
                            "frame-ancestors 'none'",
                            "upgrade-insecure-requests"
                        ].join('; ')
                    },
                    {
                        key: 'Permissions-Policy',
                        value: [
                            'camera=()',
                            'microphone=()',
                            'geolocation=()',
                            'interest-cohort=()',
                            'payment=()',
                            'usb=()',
                            'serial=()',
                            'bluetooth=()',
                            'magnetometer=()',
                            'accelerometer=()',
                            'gyroscope=()'
                        ].join(', ')
                    }
                ],
            },
            // API Routes CORS
            {
                source: '/api/:path*',
                headers: [
                    {
                        key: 'Access-Control-Allow-Origin',
                        value: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
                    },
                    {
                        key: 'Access-Control-Allow-Methods',
                        value: 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                    },
                    {
                        key: 'Access-Control-Allow-Headers',
                        value: 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                    },
                    {
                        key: 'Access-Control-Allow-Credentials',
                        value: 'true',
                    },
                    {
                        key: 'Access-Control-Max-Age',
                        value: '86400',
                    },
                ],
            },
            // Static Assets Caching
            {
                source: '/assets/:path*',
                headers: [
                    {
                        key: 'Cache-Control',
                        value: 'public, max-age=31536000, immutable',
                    },
                ],
            },
        ];
    },

    /* Environment Variables */
    env: {
        NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
        NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
        NEXT_PUBLIC_APP_NAME: process.env.NEXT_PUBLIC_APP_NAME || 'Creative Business',
        NEXT_PUBLIC_APP_DESCRIPTION: process.env.NEXT_PUBLIC_APP_DESCRIPTION || 'Professional e-commerce for creative business',
    },

    /* TypeScript Configuration */
    typescript: {
        ignoreBuildErrors: false,
        tsconfigPath: './tsconfig.json',
    },

    /* ESLint Configuration */
    eslint: {
        ignoreDuringBuilds: false,
        dirs: ['src'],
    },

    /* Redirects */
    async redirects() {
        return [
            {
                source: '/home',
                destination: '/',
                permanent: true,
            },
            {
                source: '/shop',
                destination: '/products',
                permanent: true,
            },
        ];
    },

    /* Rewrites */
    async rewrites() {
        return [
            {
                source: '/api/proxy/:path*',
                destination: `${process.env.NEXT_PUBLIC_API_URL}/:path*`,
            },
        ];
    },

    /* Webpack Configuration */
    webpack: (config, { buildId, dev, isServer, defaultLoaders, webpack }) => {
        // SVG Handling
        config.module.rules.push({
            test: /\.svg$/i,
            issuer: /\.[jt]sx?$/,
            use: ['@svgr/webpack'],
        });

        // Bundle Analyzer
        if (process.env.ANALYZE === 'true') {
            const { BundleAnalyzerPlugin } = require('@next/bundle-analyzer')({
                enabled: true,
            });
            config.plugins.push(new BundleAnalyzerPlugin());
        }

        // Optimize lodash imports
        config.resolve.alias['lodash'] = 'lodash-es';

        return config;
    },

    /* Output Configuration */
    output: 'standalone',

    /* Compression */
    compress: true,

    /* Development Configuration */
    ...(process.env.NODE_ENV === 'development' && {
        devIndicators: {
            buildActivity: true,
            buildActivityPosition: 'bottom-right',
        },
    }),

    /* Production Optimizations */
    ...(process.env.NODE_ENV === 'production' && {
        compiler: {
            removeConsole: {
                exclude: ['error', 'warn'],
            },
        },
        generateBuildId: async () => {
            return `${process.env.VERCEL_GIT_COMMIT_SHA || 'local'}-${Date.now()}`;
        },
    }),
};

export default nextConfig;