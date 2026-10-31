import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import excludeLegacyIslands from './resources/css/postcss-exclude-legacy-islands.js';

export default {
    plugins: [
        tailwindcss(),
        autoprefixer(),
        excludeLegacyIslands({ entry: 'resources/css/tabler.css' }),
    ],
};
