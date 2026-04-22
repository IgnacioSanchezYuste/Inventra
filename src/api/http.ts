import axios, { AxiosError } from 'axios'
import { useAuthStore } from '../store/auth'

const baseURL = import.meta.env.VITE_API_BASE_URL || '/api'

export const http = axios.create({ baseURL, timeout: 15000 })

http.interceptors.request.use((cfg) => {
  const token = localStorage.getItem('inventra_token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  return cfg
})

http.interceptors.response.use(
  (r) => r,
  (err: AxiosError<any>) => {
    const url = err.config?.url || ''
    const isAuthCall = /\/auth\/(login|register)/.test(url)
    if (err.response?.status === 401 && !isAuthCall) {
      const auth = useAuthStore()
      auth.logout()
      if (location.hash !== '#/login') location.hash = '#/login'
    }
    return Promise.reject(err)
  }
)

export function apiError(e: any): string {
  if (e?.response?.data?.message) return e.response.data.message
  if (e?.response?.status) return `Error ${e.response.status}`
  if (e?.code === 'ERR_NETWORK') return 'Sin conexión con el servidor (¿CORS o servidor caído?)'
  return e?.message || 'Error de red'
}
