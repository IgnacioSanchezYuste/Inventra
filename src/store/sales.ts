import { defineStore } from 'pinia'
import { salesApi } from '../api/sales'
import { useProductsStore } from './products'
import { useAnalyticsStore } from './analytics'
import { apiError } from '../api/http'
import type { Sale } from '../api/types'

interface State {
  items: Sale[]
  loaded: boolean
  loading: boolean
  error: string | null
}

export const useSalesStore = defineStore('sales', {
  state: (): State => ({ items: [], loaded: false, loading: false, error: null }),
  getters: {
    recent: (s) => s.items,
    byProduct: (s) => (pid: number | null) => pid ? s.items.filter(x => x.product_id === pid) : s.items
  },
  actions: {
    async fetchAll(force = false) {
      if (this.loaded && !force) return
      this.loading = true; this.error = null
      try {
        this.items = await salesApi.list()
        this.loaded = true
      } catch (e) { this.error = apiError(e) }
      finally { this.loading = false }
    },
    async create(product_id: number, quantity: number) {
      this.loading = true; this.error = null
      try {
        const products = useProductsStore()
        const p = products.byId(product_id)
        const r = await salesApi.create(product_id, quantity)
        const sale: Sale = {
          id: r.sale_id,
          product_id,
          user_id: 0,
          quantity,
          unit_price: p ? Number(p.price) : 0,
          total_price: r.total_price,
          product_name: p?.name || `#${product_id}`,
          created_at: new Date().toISOString().replace('T', ' ').slice(0, 19)
        }
        this.items = [sale, ...this.items]
        products.decrementStockLocal(product_id, quantity)
        useAnalyticsStore().refresh().catch(() => {})
        return sale
      } catch (e: any) {
        this.error = apiError(e)
        throw e
      } finally { this.loading = false }
    },
    clear() { this.items = []; this.loaded = false }
  }
})
