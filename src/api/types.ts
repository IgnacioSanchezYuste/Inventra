export type Role = 'admin' | 'manager' | 'user'

export interface User {
  id: number
  name: string
  email?: string
  role: Role
  company_id: number | null
  company_name: string | null
}

export interface JwtPayload {
  user_id: number
  name: string
  email: string
  role: Role
  company_id: number | null
  company_name: string | null
  iat: number
  exp: number
}

export interface Company {
  id: number
  name: string
  created_at: string
  admin_id: number
  admin_name: string
  admin_email: string
}

export interface Member {
  id: number
  name: string
  email: string
  role: Role
  created_at: string
}

export interface Invitation {
  id: number
  email: string
  role: 'manager' | 'user'
  status: 'pending' | 'accepted' | 'revoked'
  created_at: string
  accepted_at: string | null
}

export interface Product {
  id: number
  company_id?: number
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
  seller_name?: string
}

export interface AnalyticsSummary {
  total_revenue: number
  total_profit: number
  total_sales: number
  low_stock_products: { id: number; name: string; stock: number }[]
}
