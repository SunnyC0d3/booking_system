import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname),
            '@api': path.resolve(__dirname, 'api'),
            '@assets': path.resolve(__dirname, 'assets'),
            '@context': path.resolve(__dirname, 'context'),
            '@hooks': path.resolve(__dirname, 'hooks'),
            '@components': path.resolve(__dirname, 'components'),
        },
    },
});