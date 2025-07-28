import { NextConfig } from 'next';
import { BundleAnalyzerPlugin } from 'webpack-bundle-analyzer';

const nextConfig: NextConfig = {
    /* Basic Configuration */
    reactStrictMode: true,
    swcMinify: true,
    poweredByHeader: false,

    /* Experimental Features for Performance */
    experimental: {
        optimizePackageImports: [
            'lucide-react',
            '@radix-ui/react-icons',
            'framer-motion',
            'date-fns',
            'lodash',
            'axios',
            'zustand',
            '@tanstack/react-query',
        ],
        turbo: {
            rules: {
                '*.svg': {
                    loaders: ['@svgr/webpack'],
                    as: '*.js',
                },
            },
        },
        serverComponentsExternalPackages: ['sharp'],
        optimizeCss: true,
        gzipSize: true,
        craCompat: true,
        esmExternals: true,
        serverMinification: true,
        instrumentationHook: true,
    },

    /* Enhanced Images Configuration */
    images: {
        formats: ['image/avif', 'image/webp'],
        deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
        imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
        domains: ['localhost', '127.0.0.1'],
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
        minimumCacheTTL: 86400, // 24 hours
        disableStaticImages: false,
        unoptimized: false,
    },

    /* Performance Headers */
    async headers() {
        return [
            {
                source: '/((?!api|_next/static|_next/image|favicon.ico).*)',
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
                    // Performance Headers
                    {
                        key: 'X-Preload',
                        value: 'prefetch'
                    },
                ],
            },
            // Static Assets Aggressive Caching
            {
                source: '/_next/static/:path*',
                headers: [
                    {
                        key: 'Cache-Control',
                        value: 'public, max-age=31536000, immutable',
                    },
                ],
            },
            // Images Caching
            {
                source: '/_next/image/:path*',
                headers: [
                    {
                        key: 'Cache-Control',
                        value: 'public, max-age=86400, s-maxage=86400',
                    },
                ],
            },
            // API Response Caching
            {
                source: '/api/:path*',
                headers: [
                    {
                        key: 'Cache-Control',
                        value: 'public, max-age=300, s-maxage=300, stale-while-revalidate=86400',
                    },
                ],
            },
            // Font Preloading
            {
                source: '/',
                headers: [
                    {
                        key: 'Link',
                        value: '<https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap>; rel=preload; as=style',
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

    /* Performance Redirects */
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
            // Redirect old admin routes
            {
                source: '/admin-panel/:path*',
                destination: '/admin/:path*',
                permanent: true,
            },
        ];
    },

    /* Performance Rewrites */
    async rewrites() {
        return [
            {
                source: '/api/proxy/:path*',
                destination: `${process.env.NEXT_PUBLIC_API_URL}/:path*`,
            },
            // Static assets optimization
            {
                source: '/assets/:path*',
                destination: '/_next/static/:path*',
            },
        ];
    },

    /* Advanced Webpack Configuration */
    webpack: (config, { buildId, dev, isServer, defaultLoaders, webpack }) => {
        // Bundle Analyzer
        if (process.env.ANALYZE === 'true') {
            config.plugins.push(
                new BundleAnalyzerPlugin({
                    analyzerMode: 'static',
                    openAnalyzer: false,
                    reportFilename: `./analyze/${isServer ? 'server' : 'client'}.html`,
                })
            );
        }

        // SVG Handling with Optimization
        config.module.rules.push({
            test: /\.svg$/i,
            issuer: /\.[jt]sx?$/,
            use: [
                {
                    loader: '@svgr/webpack',
                    options: {
                        svgoConfig: {
                            plugins: [
                                {
                                    name: 'preset-default',
                                    params: {
                                        overrides: {
                                            removeViewBox: false,
                                        },
                                    },
                                },
                                'removeDimensions',
                            ],
                        },
                    },
                },
            ],
        });

        // Optimize imports
        config.resolve.alias = {
            ...config.resolve.alias,
            'lodash': 'lodash-es',
            'date-fns': 'date-fns/esm',
        };

        // Optimize chunk splitting
        if (!dev && !isServer) {
            config.optimization.splitChunks = {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendors',
                        chunks: 'all',
                        priority: 10,
                    },
                    ui: {
                        test: /[\\/]src[\\/]components[\\/]ui[\\/]/,
                        name: 'ui',
                        chunks: 'all',
                        priority: 20,
                    },
                    common: {
                        minChunks: 2,
                        name: 'common',
                        chunks: 'all',
                        priority: 5,
                    },
                },
            };
        }

        // Tree shaking optimization
        config.optimization.usedExports = true;
        config.optimization.sideEffects = false;

        // Compression plugins
        if (!dev) {
            config.plugins.push(
                new webpack.IgnorePlugin({
                    resourceRegExp: /^\.\/locale$/,
                    contextRegExp: /moment$/,
                })
            );
        }

        // Performance optimization
        config.resolve.fallback = {
            ...config.resolve.fallback,
            fs: false,
            path: false,
            crypto: false,
        };

        return config;
    },

    /* Output Configuration */
    output: 'standalone',
    distDir: '.next',
    compress: true,
    generateEtags: true,

    /* Development Configuration */
    ...(process.env.NODE_ENV === 'development' && {
        devIndicators: {
            buildActivity: true,
            buildActivityPosition: 'bottom-right',
        },
        onDemandEntries: {
            maxInactiveAge: 25 * 1000,
            pagesBufferLength: 2,
        },
    }),

    /* Production Optimizations */
    ...(process.env.NODE_ENV === 'production' && {
        compiler: {
            removeConsole: {
                exclude: ['error', 'warn'],
            },
            reactRemoveProperties: true,
            styledComponents: true,
        },
        generateBuildId: async () => {
            return `${process.env.VERCEL_GIT_COMMIT_SHA || process.env.GITHUB_SHA || 'local'}-${Date.now()}`;
        },
        // Production-only optimizations
        swcMinify: true,
        modularizeImports: {
            'lucide-react': {
                transform: 'lucide-react/dist/esm/icons/{{kebabCase member}}',
            },
            'date-fns': {
                transform: 'date-fns/{{member}}',
            },
        },
    }),

    /* Runtime Configuration */
    serverRuntimeConfig: {
        // Only available on the server side
        mySecret: 'secret',
    },
    publicRuntimeConfig: {
        // Available on both server and client
        staticFolder: '/static',
    },
};

export default nextConfig;