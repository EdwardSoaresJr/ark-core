import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/public-lead-phone.js',
                'resources/js/public-book-wizard.js',
                'resources/js/public-mobile-contact-bar.js',
                'resources/js/public-surface-events.js',
                'resources/js/cloud-funnel.js',
            ],
            buildDirectory: process.env.VITE_BUILD_DIRECTORY || 'build',
            refresh: true,
        }),
    ],
});
