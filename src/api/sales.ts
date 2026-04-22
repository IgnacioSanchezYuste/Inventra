import { http } from './http'
import type { Sale } from './types'

export interface CreateSaleResp {
  success: boolean
  sale_id: number
  total_price: number
}

export const salesApi = {
  create: (product_id: number, quantity: number) =>
    http.post<CreateSaleResp>('/sales', { product_id, quantity }).then(r => r.data),

  list: () => http.get<{ sales: Sale[] }>('/sales').then(r => r.data.sales)
}
