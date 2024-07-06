import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/apexcharts.js'],
            refresh: true,
        }),
        visualizer({
            open: false,
            filename: 'dist/stats.html',
        }),
    ],
});
