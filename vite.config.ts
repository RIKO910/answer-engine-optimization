import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'admin-app/src'),
    },
  },
  build: {
    outDir: 'assets/build',
    target: 'es2022',
    emptyOutDir: true,
    manifest: 'manifest.json',
    // The bundle is enqueued as a classic <script>, not a module, so the
    // module-preload polyfill would be dead code.
    modulePreload: { polyfill: false },
    rollupOptions: {
      input: path.resolve(__dirname, 'admin-app/src/main.tsx'),
      output: {
        // Loaded as a classic <script> in wp-admin: wrap it in an IIFE so minified
        // top-level names never leak into window (one used to overwrite window.wp),
        // while keeping the CSS as its own file (format 'iife' would inline it).
        banner: '(() => {',
        footer: '})();',
        // The wrapper only works for a single file: a lazy import() would create
        // chunks with `import`/`export` statements that cannot run as classic scripts.
        inlineDynamicImports: true,
      },
    },
  },
});
