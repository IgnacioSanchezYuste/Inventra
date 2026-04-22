import { defineStore } from 'pinia'
import { salesApi } from '../api/sales'
import { useProductsStore } from './products'
import { useAnalyticsStore } from './analytics'
import type { Sale } from '../api/types'

interface LocalSale extends Sale {
  product_name: string
  created_at: string
}

interface State {
  recent: LocalSale[]
  loading: boolean
  error: string | null
}

const KEY = 'inventra_sales_local'

function loadLocal(): LocalSale[] {
  try { return JSON.parse(localStorage.getItem(KEY) || '[]') } catch { return [] }
}

export const useSalesStore = defineStore('sales', {
  state: (): State => ({ recent: loadLocal(), loading: false, error: null }),
  getters: {
    byProduct: (s) => (pid: number | null) =>
      pid ? s.recent.filter(x => x.product_id === pid) : s.recent
  },
  actions: {
    async create(product_id: number, quantity: number) {
      this.loading = true; this.error = null
      try {
        const products = useProductsStore()
        const p = products.byId(product_id)
        const r = await salesApi.create(product_id, quantity)
        const sale: LocalSale = {
          id: r.sale_id,
          product_id,
          user_id: 0,
          quantity,
          unit_price: p ? Number(p.price) : 0,
          total_price: r.total_price,
          product_name: p?.name || `#${product_id}`,
          created_at: new Date().toISOString()
        }
        this.recent = [sale, ...this.recent].slice(0, 200)
        localStorage.setItem(KEY, JSON.stringify(this.recent))
        products.decrementStockLocal(product_id, quantity)
        useAnalyticsStore().refresh().catch(() => {})
        return sale
      } catch (e: any) {
        this.error = e?.response?.data?.message || 'No se pudo registrar la venta'
        throw e
      } finally { this.loading = false }
    },
    clear() {
      this.recent = []
      localStorage.removeItem(KEY)
    }
  }
})
