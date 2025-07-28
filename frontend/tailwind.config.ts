/** @type {import('tailwindcss').Config} */
const config = {
    darkMode: ['class'],
    content: [
        './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
        './src/components/**/*.{js,ts,jsx,tsx,mdx}',
        './src/app/**/*.{js,ts,jsx,tsx,mdx}',
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
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',

                // Professional Creative Business Palette
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                    50: '#fdf2f8',   // Soft blush
                    100: '#fce7f3',  // Light rose
                    200: '#fbcfe8',  // Pale pink
                    300: '#f9a8d4',  // Rose pink
                    400: '#f472b6',  // Medium pink
                    500: '#ec4899',  // Primary rose
                    600: '#db2777',  // Deep rose
                    700: '#be185d',  // Rich rose
                    800: '#9d174d',  // Dark rose
                    900: '#831843',  // Very dark rose
                    950: '#500724',  // Almost black rose
                },

                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                    50: '#f8fafc',   // Soft cloud
                    100: '#f1f5f9',  // Light gray
                    200: '#e2e8f0',  // Pale gray
                    300: '#cbd5e1',  // Medium gray
                    400: '#94a3b8',  // Steel gray
                    500: '#64748b',  // Slate gray
                    600: '#475569',  // Dark slate
                    700: '#334155',  // Charcoal
                    800: '#1e293b',  // Dark charcoal
                    900: '#0f172a',  // Almost black
                },

                // Warm Creative Accent Colors
                cream: {
                    50: '#fffdf7',   // Ivory
                    100: '#fffbeb',  // Cream
                    200: '#fef3c7',  // Light cream
                    300: '#fde68a',  // Warm cream
                    400: '#facc15',  // Gold cream
                    500: '#eab308',  // Rich gold
                    600: '#ca8a04',  // Deep gold
                    700: '#a16207',  // Bronze
                    800: '#854d0e',  // Dark bronze
                    900: '#713f12',  // Deep bronze
                },

                sage: {
                    50: '#f0fdf4',   // Soft mint
                    100: '#dcfce7',  // Light sage
                    200: '#bbf7d0',  // Pale sage
                    300: '#86efac',  // Medium sage
                    400: '#4ade80',  // Bright sage
                    500: '#22c55e',  // Primary sage
                    600: '#16a34a',  // Deep sage
                    700: '#15803d',  // Rich sage
                    800: '#166534',  // Dark sage
                    900: '#14532d',  // Very dark sage
                },

                lavender: {
                    50: '#faf5ff',   // Soft lavender
                    100: '#f3e8ff',  // Light lavender
                    200: '#e9d5ff',  // Pale lavender
                    300: '#d8b4fe',  // Medium lavender
                    400: '#c084fc',  // Bright lavender
                    500: '#a855f7',  // Primary lavender
                    600: '#9333ea',  // Deep lavender
                    700: '#7c3aed',  // Rich lavender
                    800: '#6b21a8',  // Dark lavender
                    900: '#581c87',  // Very dark lavender
                },

                // Professional UI Colors
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },

                // Status Colors
                success: {
                    DEFAULT: '#059669',
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    500: '#059669',
                    600: '#047857',
                    700: '#065f46',
                    foreground: '#ffffff',
                },
                warning: {
                    DEFAULT: '#d97706',
                    50: '#fffbeb',
                    100: '#fef3c7',
                    500: '#d97706',
                    600: '#b45309',
                    700: '#92400e',
                    foreground: '#ffffff',
                },
                error: {
                    DEFAULT: '#dc2626',
                    50: '#fef2f2',
                    100: '#fee2e2',
                    500: '#dc2626',
                    600: '#b91c1c',
                    700: '#991b1b',
                    foreground: '#ffffff',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
            },

            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },

            fontFamily: {
                sans: [
                    'Inter',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    'Segoe UI',
                    'Roboto',
                    'Helvetica Neue',
                    'Arial',
                    'sans-serif',
                ],
                heading: [
                    'Inter',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    'Segoe UI',
                    'Roboto',
                    'sans-serif',
                ],
                mono: [
                    'JetBrains Mono',
                    'Monaco',
                    'Cascadia Code',
                    'Segoe UI Mono',
                    'Roboto Mono',
                    'monospace',
                ],
            },

            fontSize: {
                xs: ['0.75rem', { lineHeight: '1rem' }],
                sm: ['0.875rem', { lineHeight: '1.25rem' }],
                base: ['1rem', { lineHeight: '1.5rem' }],
                lg: ['1.125rem', { lineHeight: '1.75rem' }],
                xl: ['1.25rem', { lineHeight: '1.75rem' }],
                '2xl': ['1.5rem', { lineHeight: '2rem' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
                '5xl': ['3rem', { lineHeight: '1' }],
                '6xl': ['3.75rem', { lineHeight: '1' }],
                '7xl': ['4.5rem', { lineHeight: '1' }],
                '8xl': ['6rem', { lineHeight: '1' }],
                '9xl': ['8rem', { lineHeight: '1' }],
            },

            fontWeight: {
                thin: '100',
                extralight: '200',
                light: '300',
                normal: '400',
                medium: '500',
                semibold: '600',
                bold: '700',
                extrabold: '800',
                black: '900',
            },

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

            animation: {
                'fade-in': 'fadeIn 0.5s ease-in-out',
                'fade-out': 'fadeOut 0.5s ease-in-out',
                'slide-in': 'slideIn 0.3s ease-out',
                'slide-out': 'slideOut 0.3s ease-in',
                'scale-in': 'scaleIn 0.2s ease-out',
                'scale-out': 'scaleOut 0.2s ease-in',
                'bounce-subtle': 'bounceSubtle 0.6s ease-in-out',
                'shimmer': 'shimmer 2s linear infinite',
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },

            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                fadeOut: {
                    '0%': { opacity: '1' },
                    '100%': { opacity: '0' },
                },
                slideIn: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                slideOut: {
                    '0%': { transform: 'translateY(0)', opacity: '1' },
                    '100%': { transform: 'translateY(-10px)', opacity: '0' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.95)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                scaleOut: {
                    '0%': { transform: 'scale(1)', opacity: '1' },
                    '100%': { transform: 'scale(0.95)', opacity: '0' },
                },
                bounceSubtle: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-5px)' },
                },
                shimmer: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(100%)' },
                },
            },

            backdropBlur: {
                xs: '2px',
            },

            boxShadow: {
                'soft': '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
                'soft-lg': '0 10px 30px -5px rgba(0, 0, 0, 0.1), 0 20px 25px -5px rgba(0, 0, 0, 0.04)',
                'glow': '0 0 20px rgba(236, 72, 153, 0.3)',
                'glow-lg': '0 0 30px rgba(236, 72, 153, 0.4)',
            },

            backgroundImage: {
                'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
                'gradient-creative': 'linear-gradient(135deg, #fdf2f8, #f3e8ff, #ecfdf5)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
    ],
};

module.exports = config;