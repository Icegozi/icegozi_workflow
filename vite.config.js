import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],

        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },

        server: {
            host: '0.0.0.0',
            port: Number(env.VITE_PORT || 5174),
            strictPort: true,

            hmr: {
                host: env.VITE_HOST || 'localhost',
                port: Number(env.VITE_PORT || 5174),
            },

            watch: {
                usePolling: true,
            },
        },
    };
});