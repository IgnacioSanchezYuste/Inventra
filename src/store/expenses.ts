import { defineStore } from 'pinia'
import { expensesApi, type NewExpense, type UpdateExpense } from '../api/expenses'
import { useAnalyticsStore } from './analytics'
import { apiError } from '../api/http'
import type { Expense } from '../api/types'
import { num } from '../utils/format'

interface State {
  items: Expense[]
  loaded: boolean
  loading: boolean
  saving: boolean
  error: string | null
}

export const useExpensesStore = defineStore('expenses', {
  state: (): State => ({ items: [], loaded: false, loading: false, saving: false, error: null }),

  getters: {
    total: (s) => s.items.reduce((a, e) => a + num(e.amount), 0),
    byCategory: (s) => {
      const map = new Map<string, number>()
      for (const e of s.items) {
        const k = (e.category && e.category.trim()) || 'Sin categoría'
        map.set(k, (map.get(k) || 0) + num(e.amount))
      }
      return [...map.entries()]
        .map(([category, total]) => ({ category, total }))
        .sort((a, b) => b.total - a.total)
    }
  },

  actions: {
    async fetchAll(force = false) {
      if (this.loaded && !force) return
      this.loading = true; this.error = null
      try {
        this.items = await expensesApi.list()
        this.loaded = true
      } catch (e) { this.error = apiError(e) }
      finally { this.loading = false }
    },

    async create(payload: NewExpense) {
      this.saving = true; this.error = null
      try {
        const expense = await expensesApi.create(payload)
        this.items = [expense, ...this.items]
        useAnalyticsStore().refresh().catch(() => {})
        return expense
      } catch (e: any) {
        this.error = apiError(e)
        throw e
      } finally { this.saving = false }
    },

    async update(id: number, payload: UpdateExpense) {
      this.saving = true; this.error = null
      try {
        const expense = await expensesApi.update(id, payload)
        const idx = this.items.findIndex(x => x.id === id)
        if (idx >= 0) this.items.splice(idx, 1, expense)
        // Re-ordenar por expense_date desc por si la fecha cambió
        this.items = [...this.items].sort((a, b) => {
          if (a.expense_date === b.expense_date) return b.id - a.id
          return a.expense_date < b.expense_date ? 1 : -1
        })
        useAnalyticsStore().refresh().catch(() => {})
        return expense
      } catch (e: any) {
        this.error = apiError(e)
        throw e
      } finally { this.saving = false }
    },

    async remove(id: number) {
      this.saving = true; this.error = null
      try {
        await expensesApi.remove(id)
        this.items = this.items.filter(x => x.id !== id)
        useAnalyticsStore().refresh().catch(() => {})
      } catch (e: any) {
        this.error = apiError(e)
        throw e
      } finally { this.saving = false }
    },

    clear() { this.items = []; this.loaded = false }
  }
})
