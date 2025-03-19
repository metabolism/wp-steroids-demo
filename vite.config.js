import { defineConfig } from 'vite'
import * as path from 'path'
import sassGlobImports from 'vite-plugin-sass-glob-import';

// https://vite.dev/config/
export default defineConfig(({ command }) =>{
  return{
    plugins: [
      sassGlobImports(),
      {
        name: 'twig',
        handleHotUpdate({ file, server }) {
          if (file.endsWith('.twig')) {
            server.ws.send({ type: 'full-reload' });
          }
        },
      },
    ],
    resolve: {
      alias:[
          { find: '@', replacement: path.resolve(__dirname, 'assets') },
          { find: 'vue', replacement: 'vue/dist/vue.esm-bundler.js' }
      ]
    },
    define: {
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: command!=='build',
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: command!=='build'
    },
    base: '/build',
    css: {
      devSourcemap: true,
      preprocessorOptions: {
        scss: {
          api: 'modern',
          silenceDeprecations: ['import'],
        },
      },
    },
    server:{
      host: 'localhost',
      strictPort: true,
      port: 8080,
      cors: { origin: '*' },
    },
    build: {
      target: 'esnext',
      manifest: true,
      copyPublicDir: false,
      assetsDir: '',
      outDir: './public/build',
      emptyOutDir: true,
      rollupOptions: {
        input: {
          script: './assets/scripts/app.js',
          style: './assets/styles/app.scss'
        }
      }
    }
  }
})
