import type { Config } from 'tailwindcss';

const config: Config = {
    darkMode: ['class'],
    // Optimized content paths for better performance and tree-shaking
    content: [
        './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
        './src/components/**/*.{js,ts,jsx,tsx,mdx}',
        './src/app/**/*.{js,ts,jsx,tsx,mdx}',
        // Include ui components if using shadcn/ui
        './src/components/ui/**/*.{js,ts,jsx,tsx,mdx}',
        // Exclude node_modules except for specific component libraries
        '!./node_modules/**',
    ],
    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: {
                '2xl': '1400px',
            },
        },
        extend: {
            // Use CSS custom properties for better performance and theme switching
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',

                // Simplified primary color system using CSS variables
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                    // Reduced color palette for better performance
                    50: 'hsl(var(--primary-50))',
                    100: 'hsl(var(--primary-100))',
                    200: 'hsl(var(--primary-200))',
                    300: 'hsl(var(--primary-300))',
                    400: 'hsl(var(--primary-400))',
                    500: 'hsl(var(--primary-500))',
                    600: 'hsl(var(--primary-600))',
                    700: 'hsl(var(--primary-700))',
                    800: 'hsl(var(--primary-800))',
                    900: 'hsl(var(--primary-900))',
                },

                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                    50: 'hsl(var(--secondary-50))',
                    100: 'hsl(var(--secondary-100))',
                    200: 'hsl(var(--secondary-200))',
                    300: 'hsl(var(--secondary-300))',
                    400: 'hsl(var(--secondary-400))',
                    500: 'hsl(var(--secondary-500))',
                    600: 'hsl(var(--secondary-600))',
                    700: 'hsl(var(--secondary-700))',
                    800: 'hsl(var(--secondary-800))',
                    900: 'hsl(var(--secondary-900))',
                },

                // Semantic color system using CSS variables
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },

                // Status colors using CSS variables for consistency
                success: {
                    DEFAULT: 'hsl(var(--success))',
                    foreground: 'hsl(var(--success-foreground))',
                    50: 'hsl(var(--success-50))',
                    100: 'hsl(var(--success-100))',
                    500: 'hsl(var(--success-500))',
                    600: 'hsl(var(--success-600))',
                    700: 'hsl(var(--success-700))',
                },
                warning: {
                    DEFAULT: 'hsl(var(--warning))',
                    foreground: 'hsl(var(--warning-foreground))',
                    50: 'hsl(var(--warning-50))',
                    100: 'hsl(var(--warning-100))',
                    500: 'hsl(var(--warning-500))',
                    600: 'hsl(var(--warning-600))',
                    700: 'hsl(var(--warning-700))',
                },
                error: {
                    DEFAULT: 'hsl(var(--error))',
                    foreground: 'hsl(var(--error-foreground))',
                    50: 'hsl(var(--error-50))',
                    100: 'hsl(var(--error-100))',
                    500: 'hsl(var(--error-500))',
                    600: 'hsl(var(--error-600))',
                    700: 'hsl(var(--error-700))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },

                // Custom brand colors using CSS variables
                brand: {
                    cream: 'hsl(var(--brand-cream))',
                    sage: 'hsl(var(--brand-sage))',
                    lavender: 'hsl(var(--brand-lavender))',
                },
            },

            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },

            // Optimized font stack with system fonts first for better performance
            fontFamily: {
                sans: [
                    'var(--font-inter)',
                    'Inter',
                    'system-ui',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    'Segoe UI',
                    'Roboto',
                    'Helvetica Neue',
                    'Arial',
                    'sans-serif',
                ],
                heading: [
                    'var(--font-inter)',
                    'Inter',
                    'system-ui',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    'Segoe UI',
                    'Roboto',
                    'sans-serif',
                ],
                mono: [
                    'var(--font-jetbrains-mono)',
                    'JetBrains Mono',
                    'Menlo',
                    'Monaco',
                    'Cascadia Code',
                    'Segoe UI Mono',
                    'Roboto Mono',
                    'Consolas',
                    'monospace',
                ],
            },

            // Optimized typography scale
            fontSize: {
                xs: ['0.75rem', { lineHeight: '1rem', letterSpacing: '0.025em' }],
                sm: ['0.875rem', { lineHeight: '1.25rem', letterSpacing: '0.025em' }],
                base: ['1rem', { lineHeight: '1.5rem', letterSpacing: 'normal' }],
                lg: ['1.125rem', { lineHeight: '1.75rem', letterSpacing: '-0.025em' }],
                xl: ['1.25rem', { lineHeight: '1.75rem', letterSpacing: '-0.025em' }],
                '2xl': ['1.5rem', { lineHeight: '2rem', letterSpacing: '-0.025em' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem', letterSpacing: '-0.05em' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem', letterSpacing: '-0.05em' }],
                '5xl': ['3rem', { lineHeight: '3rem', letterSpacing: '-0.05em' }],
                '6xl': ['3.75rem', { lineHeight: '3.75rem', letterSpacing: '-0.05em' }],
                '7xl': ['4.5rem', { lineHeight: '4.5rem', letterSpacing: '-0.05em' }],
                '8xl': ['6rem', { lineHeight: '6rem', letterSpacing: '-0.05em' }],
                '9xl': ['8rem', { lineHeight: '8rem', letterSpacing: '-0.05em' }],
            },

            // Extended spacing for modern layouts
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
                '100': '25rem',
                '112': '28rem',
                '128': '32rem',
            },

            maxWidth: {
                '8xl': '88rem',
                '9xl': '96rem',
            },

            // Performance-optimized animations
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'fade-out': 'fadeOut 0.2s ease-in',
                'slide-in': 'slideIn 0.3s ease-out',
                'slide-out': 'slideOut 0.2s ease-in',
                'scale-in': 'scaleIn 0.2s ease-out',
                'scale-out': 'scaleOut 0.15s ease-in',
                'shimmer': 'shimmer 1.5s linear infinite',
                'pulse-slow': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                // New animations for better UX
                'bounce-in': 'bounceIn 0.5s ease-out',
                'spin-slow': 'spin 3s linear infinite',
                'ping-slow': 'ping 2s cubic-bezier(0, 0, 0.2, 1) infinite',
            },

            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeOut: {
                    '0%': { opacity: '1', transform: 'translateY(0)' },
                    '100%': { opacity: '0', transform: 'translateY(-4px)' },
                },
                slideIn: {
                    '0%': { transform: 'translateY(8px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                slideOut: {
                    '0%': { transform: 'translateY(0)', opacity: '1' },
                    '100%': { transform: 'translateY(-8px)', opacity: '0' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.96)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                scaleOut: {
                    '0%': { transform: 'scale(1)', opacity: '1' },
                    '100%': { transform: 'scale(0.96)', opacity: '0' },
                },
                bounceIn: {
                    '0%': { transform: 'scale(0.3)', opacity: '0' },
                    '50%': { transform: 'scale(1.05)' },
                    '70%': { transform: 'scale(0.9)' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                shimmer: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(100%)' },
                },
            },

            // Modern shadow system
            boxShadow: {
                'soft': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
                'soft-lg': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                'glow': '0 0 15px rgba(var(--primary-rgb), 0.15)',
                'glow-lg': '0 0 25px rgba(var(--primary-rgb), 0.2)',
                'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.06)',
            },

            // Modern backdrop blur
            backdropBlur: {
                xs: '2px',
            },

            // Enhanced gradient system
            backgroundImage: {
                'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
                'gradient-creative': 'linear-gradient(135deg, hsl(var(--primary-50)), hsl(var(--accent)), hsl(var(--secondary-50)))',
                'grid': 'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\' width=\'32\' height=\'32\' fill=\'none\' stroke=\'rgb(148 163 184 / 0.05)\'%3e%3cpath d=\'m0 .5h32m-32 32v-32\'/%3e%3c/svg%3e")',
                'dot': 'radial-gradient(circle, rgb(148 163 184 / 0.05) 1px, transparent 1px)',
            },

            // Enhanced screen sizes for better responsive design
            screens: {
                'xs': '475px',
                '3xl': '1680px',
            },

            // Modern transitions
            transitionDuration: {
                '400': '400ms',
                '600': '600ms',
            },
            transitionTimingFunction: {
                'out-expo': 'cubic-bezier(0.19, 1, 0.22, 1)',
                'in-expo': 'cubic-bezier(0.95, 0.05, 0.795, 0.035)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
    ],
};

export default config;