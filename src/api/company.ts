import { http } from './http'
import type { Company, Member, Invitation } from './types'

export const companyApi = {
  current: () => http.get<{ company: Company | null }>('/company').then(r => r.data.company),
  members: () => http.get<{ members: Member[] }>('/company/members').then(r => r.data.members),
  removeMember: (id: number) => http.delete<{ success: boolean }>(`/company/members/${id}`).then(r => r.data),

  invitations: () => http.get<{ invitations: Invitation[] }>('/company/invitations').then(r => r.data.invitations),
  invite: (email: string, role: 'manager' | 'user') =>
    http.post<{ success: boolean; auto_assigned: boolean }>('/company/invitations', { email, role }).then(r => r.data),
  revoke: (id: number) => http.delete<{ success: boolean }>(`/company/invitations/${id}`).then(r => r.data)
}
