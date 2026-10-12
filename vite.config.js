import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://localhost:5173',
        cors: {
            origin: 'http://localhost:8000',
        },
        hmr: {
            host: 'localhost',
        },
        watch: {
            // Bind-mounted files on Windows/macOS do not emit change events inside Docker.
            usePolling: true,
            interval: 1000,
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
                '**/storage/**',
                '**/public/build/**',
                '**/database/**',
                '**/tests/**',
            ],
        },
    },
});
