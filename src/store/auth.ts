import { defineStore } from 'pinia'
import { authApi, type RegisterPayload } from '../api/auth'
import { apiError } from '../api/http'
import type { JwtPayload, Role, User } from '../api/types'
import { decodeJwt } from '../utils/format'
import { useProductsStore } from './products'
import { useSalesStore } from './sales'
import { useAnalyticsStore } from './analytics'
import { useCompanyStore } from './company'

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

function resetAppData() {
  useProductsStore().$reset()
  useSalesStore().$reset()
  useAnalyticsStore().$reset()
  useCompanyStore().$reset()
}

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
    isAdmin: (s) => s.user?.role === 'admin',
    hasCompany: (s) => !!s.user?.company_id
  },
  actions: {
    init() {
      if (!this.token) return
      const p = decodeJwt<JwtPayload>(this.token)
      if (!p || p.exp * 1000 < Date.now()) { this.logout(); return }
      this.expiresAt = p.exp * 1000
      if (!this.user) {
        this.user = {
          id: p.user_id, name: p.name, email: p.email, role: p.role,
          company_id: p.company_id, company_name: p.company_name
        }
        localStorage.setItem(KEY_USER, JSON.stringify(this.user))
      }
      this.scheduleAutoLogout()
    },
    scheduleAutoLogout() {
      if (logoutTimer) { clearTimeout(logoutTimer); logoutTimer = null }
      if (!this.expiresAt) return
      const ms = this.expiresAt - Date.now()
      if (ms <= 0) { this.logout(); return }
      logoutTimer = window.setTimeout(() => this.logout(), ms)
    },
    persistAuth(token: string, user: User) {
      const prevId = this.user?.id
      const prevCompany = this.user?.company_id
      this.token = token
      this.user = user
      const p = decodeJwt<JwtPayload>(token)
      this.expiresAt = p ? p.exp * 1000 : null
      localStorage.setItem(KEY_TOKEN, token)
      localStorage.setItem(KEY_USER, JSON.stringify(user))
      this.scheduleAutoLogout()
      // Si cambia de usuario o de empresa, limpia datos cacheados
      if (prevId !== user.id || prevCompany !== user.company_id) resetAppData()
    },
    async login(email: string, password: string) {
      this.loading = true; this.error = null
      try {
        const r = await authApi.login(email, password)
        this.persistAuth(r.token, r.user)
        return true
      } catch (e: any) { this.error = apiError(e); return false }
      finally { this.loading = false }
    },
    async register(payload: RegisterPayload) {
      this.loading = true; this.error = null
      try {
        await authApi.register(payload)
        return await this.login(payload.email, payload.password)
      } catch (e: any) { this.error = apiError(e); return false }
      finally { this.loading = false }
    },
    async refreshMe() {
      try {
        const r = await authApi.me()
        if (r.token) this.persistAuth(r.token, r.user)
        else {
          const prevCompany = this.user?.company_id
          this.user = r.user
          localStorage.setItem(KEY_USER, JSON.stringify(r.user))
          if (prevCompany !== r.user.company_id) resetAppData()
        }
        return r.user
      } catch (e) { return null }
    },
    logout() {
      this.token = null; this.user = null; this.expiresAt = null
      localStorage.removeItem(KEY_TOKEN)
      localStorage.removeItem(KEY_USER)
      if (logoutTimer) { clearTimeout(logoutTimer); logoutTimer = null }
      resetAppData()
    }
  }
})
