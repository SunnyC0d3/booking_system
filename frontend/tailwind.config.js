/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './index.html',
        './App.jsx',
        './components/**/*.{js,jsx,ts,tsx}',
        './auth/**/*.{js,jsx,ts,tsx}',
        './**/*.jsx',
    ],
    theme: {
        extend: {}
    },
    plugins: []
}
