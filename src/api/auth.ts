import { http } from './http'
import type { Role, User } from './types'

export interface LoginResp { success: boolean; token: string; user: User }
export interface RegisterResp { success: boolean; user_id: number; company_assigned: boolean }
export interface MeResp { user: User; token?: string }

export interface RegisterPayload {
  name: string
  email: string
  password: string
  role: Role
  company_name?: string
}

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResp>('/auth/login', { email, password }).then(r => r.data),

  register: (payload: RegisterPayload) =>
    http.post<RegisterResp>('/auth/register', payload).then(r => r.data),

  me: () => http.get<MeResp>('/me').then(r => r.data)
}
