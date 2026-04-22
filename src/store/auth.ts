import { defineStore } from 'pinia'
import { authApi } from '../api/auth'
import { apiError } from '../api/http'
import type { JwtPayload, Role, User } from '../api/types'
import { decodeJwt } from '../utils/format'

const KEY_TOKEN = 'inventra_token'
const KEY_USER = 'inventra_user'

interface State {
  token: string | null
  user: User | null
  expiresAt: number | null
  loading: boolean
  error: string | null
}

let logoutTimer: number | null = null

export const useAuthStore = defineStore('auth', {
  state: (): State => ({
    token: localStorage.getItem(KEY_TOKEN),
    user: JSON.parse(localStorage.getItem(KEY_USER) || 'null'),
    expiresAt: null,
    loading: false,
    error: null
  }),
  getters: {
    isAuthenticated: (s) => !!s.token && !!s.user,
    role: (s): Role | null => s.user?.role ?? null,
    canManage: (s) => s.user?.role === 'admin' || s.user?.role === 'manager',
    isAdmin: (s) => s.user?.role === 'admin'
  },
  actions: {
    init() {
      if (this.token) {
        const p = decodeJwt<JwtPayload>(this.token)
        if (!p || p.exp * 1000 < Date.now()) { this.logout(); return }
        this.expiresAt = p.exp * 1000
        if (!this.user) {
          this.user = { id: p.user_id, name: p.name, email: p.email, role: p.role }
          localStorage.setItem(KEY_USER, JSON.stringify(this.user))
        }
        this.scheduleAutoLogout()
      }
    },
    scheduleAutoLogout() {
      if (logoutTimer) { clearTimeout(logoutTimer); logoutTimer = null }
      if (!this.expiresAt) return
      const ms = this.expiresAt - Date.now()
      if (ms <= 0) { this.logout(); return }
      logoutTimer = window.setTimeout(() => this.logout(), ms)
    },
    async login(email: string, password: string) {
      this.loading = true; this.error = null
      try {
        const r = await authApi.login(email, password)
        this.token = r.token
        this.user = r.user
        const p = decodeJwt<JwtPayload>(r.token)
        this.expiresAt = p ? p.exp * 1000 : null
        localStorage.setItem(KEY_TOKEN, r.token)
        localStorage.setItem(KEY_USER, JSON.stringify(r.user))
        this.scheduleAutoLogout()
        return true
      } catch (e: any) {
        this.error = apiError(e)
        return false
      } finally { this.loading = false }
    },
    async register(payload: { name: string; email: string; password: string; role?: Role }) {
      this.loading = true; this.error = null
      try {
        await authApi.register(payload)
        return await this.login(payload.email, payload.password)
      } catch (e: any) {
        this.error = apiError(e)
        return false
      } finally { this.loading = false }
    },
    logout() {
      this.token = null; this.user = null; this.expiresAt = null
      localStorage.removeItem(KEY_TOKEN)
      localStorage.removeItem(KEY_USER)
      if (logoutTimer) { clearTimeout(logoutTimer); logoutTimer = null }
    }
  }
})
