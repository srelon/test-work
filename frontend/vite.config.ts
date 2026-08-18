import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [vue()],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
    },
    resolve: {
        alias: [
            {
                find: '@',
                replacement: path.resolve(__dirname, 'src'),
            },
            {
                find: '@public',
                replacement: path.resolve(__dirname, 'public'),
            },
        ],
    },
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: `@use "@/assets/scss/variables" as *;`,
            },
        },
    },
})
