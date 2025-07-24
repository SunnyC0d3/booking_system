/** @type {import('next').NextConfig} */
const nextConfig = {
    experimental: {
        serverActions: true,
    },

    images: {
        domains: ['localhost', '127.0.0.1'],
        remotePatterns: [
            {
                protocol: 'https',
                hostname: 'your-trusted-domain.com',
            },
            {
                protocol: 'https',
                hostname: 'cdn.your-domain.com',
            },
            {
                protocol: 'http',
                hostname: 'localhost',
                port: '8000',
            },
        ],
    },

    async headers() {
        return [
            {
                source: '/(.*)',
                headers: [
                    {
                        key: 'X-Frame-Options',
                        value: 'DENY',
                    },
                    {
                        key: 'X-Content-Type-Options',
                        value: 'nosniff',
                    },
                    {
                        key: 'Referrer-Policy',
                        value: 'origin-when-cross-origin',
                    },
                    {
                        key: 'X-XSS-Protection',
                        value: '1; mode=block',
                    },
                    {
                        key: 'Permissions-Policy',
                        value: 'camera=(), microphone=(), geolocation=()',
                    },
                ],
            },
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
                        value: 'Content-Type, Authorization, X-Requested-With',
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
        ];
    },

    env: {
        NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
        NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1',
    },

    typescript: {
        ignoreBuildErrors: false,
    },

    eslint: {
        ignoreDuringBuilds: false,
    },

    poweredByHeader: false,

    async redirects() {
        return [
        ];
    },

    async rewrites() {
        return [
        ];
    },
};

module.exports = nextConfig;