export type Role = 'admin' | 'manager' | 'user'

export interface User {
  id: number
  name: string
  email?: string
  role: Role
}

export interface JwtPayload {
  user_id: number
  name: string
  email: string
  role: Role
  iat: number
  exp: number
}

export interface Product {
  id: number
  user_id?: number
  owner_name?: string | null
  name: string
  description: string | null
  image_url: string | null
  price: number | string
  cost: number | string
  stock: number
  category: string | null
  created_at?: string
}

export interface Sale {
  id: number
  product_id: number
  user_id: number
  quantity: number
  unit_price: number | string
  total_price: number | string
  created_at?: string
  product_name?: string
}

export interface AnalyticsSummary {
  total_revenue: number
  total_profit: number
  total_sales: number
  low_stock_products: { id: number; name: string; stock: number }[]
}
