import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const target = env.VITE_API_PROXY_TARGET || 'https://ignaciosanchezyuste.es'
  const base = env.VITE_BASE_PATH || (mode === 'production' ? '/Inventra/' : '/')

  return {
    base,
    plugins: [vue()],
    server: {
      proxy: {
        '/api': {
          target,
          changeOrigin: true,
          secure: false,
          rewrite: (p) => p.replace(/^\/api/, '/API_Inventra')
        }
      }
    }
  }
})
