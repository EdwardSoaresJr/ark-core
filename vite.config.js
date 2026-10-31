import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { Features } from 'lightningcss';

export default defineConfig({
    css: {
        lightningcss: {
            // Tabler nests a dark sidebar in a light page with light-dark(). The minifier's polyfill resolves
            // light-dark() once at :root, so the nested sidebar stayed light. Ship the native function instead.
            exclude: Features.LightDark,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/tabler.css',
                'resources/js/app.js',
                'resources/js/tabler.js',
                'resources/js/cloud-funnel.js',
            ],
            buildDirectory: process.env.VITE_BUILD_DIRECTORY || 'build',
            refresh: true,
        }),
    ],
});
