import { http } from './http'
import type { Product } from './types'

export type NewProduct = Omit<Product, 'id' | 'created_at'>
export type UpdateProduct = Partial<NewProduct>

export const productsApi = {
  list: () => http.get<{ products: Product[] }>('/products').then(r => r.data.products),
  create: (p: NewProduct) =>
    http.post<{ success: boolean; product_id: number }>('/products', p).then(r => r.data),
  update: (id: number, p: UpdateProduct) =>
    http.put<{ success: boolean }>(`/products/${id}`, p).then(r => r.data),
  remove: (id: number) =>
    http.delete<{ success: boolean }>(`/products/${id}`).then(r => r.data)
}
