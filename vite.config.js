import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // Entry point is app.jsx (React) — replaces the default app.js
            input: ['resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        host: 'localhost',
    },
    resolve: {
        alias: {
            // Allows short imports: import X from '@/Components/...'
            '@': '/resources/js',
        },
    },
    
});

