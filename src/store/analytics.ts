import { defineStore } from 'pinia'
import { analyticsApi } from '../api/analytics'
import type { AnalyticsSummary } from '../api/types'
import { apiError } from '../api/http'

interface State {
  summary: AnalyticsSummary | null
  loaded: boolean
  loading: boolean
  error: string | null
  lastFetch: number
}

const TTL = 30_000

export const useAnalyticsStore = defineStore('analytics', {
  state: (): State => ({ summary: null, loaded: false, loading: false, error: null, lastFetch: 0 }),
  actions: {
    async fetch(force = false) {
      if (!force && this.loaded && Date.now() - this.lastFetch < TTL) return
      this.loading = true; this.error = null
      try {
        this.summary = await analyticsApi.summary()
        this.loaded = true
        this.lastFetch = Date.now()
      } catch (e) {
        this.error = apiError(e)
      } finally { this.loading = false }
    },
    refresh() { return this.fetch(true) }
  }
})
