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
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'admin-app/src/main.tsx'),
      // Loaded as a classic <script> in wp-admin: wrap it so minified top-level
      // names never leak into window (one of them used to overwrite window.wp).
      output: { format: 'iife' },
    },
  },
});
