import { http } from './http'
import type { Expense } from './types'

export interface NewExpense {
  description: string
  amount: number
  expense_date: string
  category: string | null
}

export interface UpdateExpense {
  description?: string
  amount?: number
  expense_date?: string
  category?: string | null
}

export const expensesApi = {
  list: () => http.get<{ expenses: Expense[] }>('/expenses').then(r => r.data.expenses),

  create: (payload: NewExpense) =>
    http.post<{ success: boolean; expense: Expense }>('/expenses', payload).then(r => r.data.expense),

  update: (id: number, payload: UpdateExpense) =>
    http.put<{ success: boolean; expense: Expense }>(`/expenses/${id}`, payload).then(r => r.data.expense),

  remove: (id: number) =>
    http.delete<{ success: boolean }>(`/expenses/${id}`).then(r => r.data)
}
