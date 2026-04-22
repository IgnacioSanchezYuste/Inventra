import { http } from './http'

export interface CreateSaleResp {
  success: boolean
  sale_id: number
  total_price: number
}

export const salesApi = {
  create: (product_id: number, quantity: number) =>
    http.post<CreateSaleResp>('/sales', { product_id, quantity }).then(r => r.data)
}
