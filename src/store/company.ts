import { defineStore } from 'pinia'
import { companyApi } from '../api/company'
import { apiError } from '../api/http'
import type { Company, Member, Invitation } from '../api/types'

interface State {
  company: Company | null
  members: Member[]
  invitations: Invitation[]
  loading: boolean
  error: string | null
}

export const useCompanyStore = defineStore('company', {
  state: (): State => ({ company: null, members: [], invitations: [], loading: false, error: null }),
  actions: {
    async fetchAll() {
      this.loading = true; this.error = null
      try {
        const [c, m, i] = await Promise.all([
          companyApi.current(),
          companyApi.members().catch(() => []),
          companyApi.invitations().catch(() => [])
        ])
        this.company = c; this.members = m; this.invitations = i
      } catch (e) { this.error = apiError(e) }
      finally { this.loading = false }
    },
    async fetchCompany() {
      try { this.company = await companyApi.current() } catch (e) { this.error = apiError(e) }
    },
    async invite(email: string, role: 'manager' | 'user') {
      const r = await companyApi.invite(email, role)
      await this.fetchAll()
      return r
    },
    async revoke(id: number) { await companyApi.revoke(id); await this.fetchAll() },
    async removeMember(id: number) { await companyApi.removeMember(id); await this.fetchAll() }
  }
})
