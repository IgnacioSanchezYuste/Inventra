import { defineStore } from 'pinia'
import { productsApi, type NewProduct, type UpdateProduct } from '../api/products'
import type { Product } from '../api/types'
import { apiError } from '../api/http'

interface State {
  items: Product[]
  loaded: boolean
  loading: boolean
  error: string | null
}

export const useProductsStore = defineStore('products', {
  state: (): State => ({ items: [], loaded: false, loading: false, error: null }),
  getters: {
    byId: (s) => (id: number) => s.items.find(p => p.id === id),
    lowStock: (s) => s.items.filter(p => Number(p.stock) < 5)
  },
  actions: {
    async fetchAll(force = false) {
      if (this.loaded && !force) return
      this.loading = true; this.error = null
      try {
        this.items = await productsApi.list()
        this.loaded = true
      } catch (e) {
        this.error = apiError(e)
      } finally { this.loading = false }
    },
    async create(p: NewProduct) {
      const r = await productsApi.create(p)
      await this.fetchAll(true)
      return r.product_id
    },
    async update(id: number, p: UpdateProduct) {
      await productsApi.update(id, p)
      const idx = this.items.findIndex(x => x.id === id)
      if (idx >= 0) this.items[idx] = { ...this.items[idx], ...p } as Product
    },
    async remove(id: number) {
      await productsApi.remove(id)
      this.items = this.items.filter(p => p.id !== id)
    },
    decrementStockLocal(id: number, qty: number) {
      const idx = this.items.findIndex(p => p.id === id)
      if (idx >= 0) this.items[idx] = { ...this.items[idx], stock: Number(this.items[idx].stock) - qty }
    }
  }
})
