import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './node_modules/preline/dist/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'ark-cerulean': {
                    50: '#edf8fc',
                    100: '#d4eef8',
                    200: '#a9ddf1',
                    300: '#72c7e8',
                    400: '#38abdb',
                    500: '#0099cc',
                    600: '#007db3',
                    700: '#006694',
                    800: '#004d70',
                    900: '#003854',
                    950: '#002538',
                },
                'ark-pink': {
                    50: '#ffeff8',
                    100: '#ffd9f0',
                    200: '#ffb3e1',
                    300: '#ff8ad2',
                    400: '#ff47c0',
                    500: '#ff12b0',
                    600: '#e6009d',
                    700: '#c80088',
                    800: '#a30070',
                    900: '#85005c',
                    950: '#5c0040',
                },
            },
        },
    },

    plugins: [forms],

    safelist: [
        {
            pattern: /^ops-disposition-select--(draft|recommended|approved|deferred|declined)$/,
        },
        {
            pattern: /^ops-state-pill--(draft|recommended|approved|deferred|declined)$/,
        },
        {
            pattern: /^ops-intake(-[a-z-]+)?$/,
        },
        {
            pattern: /^ops-(worksheet|review)-concern--intent-(immediate_attention|diagnostic|repair_verification|maintenance|plan_soon|information_only)$/,
        },
        {
            pattern: /^ops-intent-label--(immediate_attention|diagnostic|repair_verification|maintenance|plan_soon|information_only)$/,
        },
        {
            pattern: /^ops-intent-group-heading--(immediate_attention|diagnostic|repair_verification|maintenance|plan_soon|information_only)$/,
        },
        {
            pattern: /^ops-intent-group--intent-(immediate_attention|diagnostic|repair_verification|maintenance|plan_soon|information_only)$/,
        },
        {
            pattern: /^ops-scope-header-decision--(approved|deferred|declined|recommended)$/,
        },
        {
            pattern: /^ops-repair-action__compose-btn--(labor|part|note|sublet|fee|evidence|saved-work)$/,
        },
        {
            pattern: /^ops-workspace(-[a-z-]+)*(__[-a-z0-9]+)*(--[-a-z0-9]+)?$/,
        },
        {
            pattern: /^ops-job-card__(chip|promise|event)--[a-z-]+$/,
        },
        {
            pattern: /^public-page-section--(white|hero|muted|trust|accent)$/,
        },
        {
            pattern: /^public-action-card--(hero|rail|staged)$/,
        },
        {
            pattern: /^public-trust-band__badge-link--external$/,
        },
        'public-footer',
        'public-panel--reviews-compact',
    ],
};
