import { http } from './http'
import type { Role, User } from './types'

export interface LoginResp { success: boolean; token: string; user: User }
export interface RegisterResp { success: boolean; user_id: number }

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResp>('/auth/login', { email, password }).then(r => r.data),

  register: (payload: { name: string; email: string; password: string; role?: Role }) =>
    http.post<RegisterResp>('/auth/register', payload).then(r => r.data)
}
