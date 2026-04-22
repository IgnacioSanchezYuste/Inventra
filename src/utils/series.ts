import type { Sale } from '../api/types'
import { num } from './format'

function localDateKey(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function saleDateKey(s: Sale): string {
  if (!s.created_at) return ''
  const iso = s.created_at.includes('T') ? s.created_at : s.created_at.replace(' ', 'T')
  const d = new Date(iso)
  if (isNaN(d.getTime())) return s.created_at.slice(0, 10)
  return localDateKey(d)
}

export function dailyBuckets(sales: Sale[], days = 7) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const labels: string[] = []
  const revenue: number[] = []
  const count: number[] = []
  for (let i = days - 1; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(today.getDate() - i)
    const key = localDateKey(d)
    labels.push(d.toLocaleDateString('es-ES', { weekday: 'short', day: '2-digit' }))
    let r = 0, c = 0
    for (const s of sales) {
      if (saleDateKey(s) === key) { r += num(s.total_price); c++ }
    }
    revenue.push(Math.round(r * 100) / 100)
    count.push(c)
  }
  return { labels, revenue, count }
}

export function topProducts(sales: Sale[], limit = 5) {
  const map = new Map<number, { name: string; revenue: number; qty: number }>()
  for (const s of sales) {
    const t = map.get(s.product_id) || { name: s.product_name || `#${s.product_id}`, revenue: 0, qty: 0 }
    t.revenue += num(s.total_price)
    t.qty += Number(s.quantity)
    map.set(s.product_id, t)
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue).slice(0, limit)
}
