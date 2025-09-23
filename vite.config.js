import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'fp-multilanguage/assets/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                frontend: 'fp-multilanguage/assets/js/frontend.js',
                admin: 'fp-multilanguage/assets/js/admin.js'
            },
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name][extname]'
            }
        }
    }
});
