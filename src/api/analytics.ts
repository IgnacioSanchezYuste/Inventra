import { http } from './http'
import type { AnalyticsSummary } from './types'

export const analyticsApi = {
  summary: () => http.get<AnalyticsSummary>('/analytics/summary').then(r => r.data)
}
