import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  base: '/wp-content/plugins/gpllib-connector/admin/dist/',
  plugins: [vue()],
  build: {
    outDir: 'dist',
    manifest: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: 'src/main.js',
      output: {
        
        manualChunks: undefined,
        entryFileNames: 'js/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
})
