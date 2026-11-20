import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'],
            refresh: true,
            // Inter staat in de repo (resources/fonts/inter, OFL-licentie), zodat een build nooit afhangt van een
            // lettertypedienst: fonts.bunny.net gaf op de server een 504 en blokkeerde daarmee de deploy.
            fonts: [
                local('Inter', {
                    variants: [400, 500, 600, 700].map((weight) => ({
                        src: `resources/fonts/inter/inter-latin-${weight}-normal.woff2`,
                        weight,
                    })),
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
