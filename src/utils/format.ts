export function money(v: number | string | null | undefined): string {
  const n = Number(v ?? 0)
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(isFinite(n) ? n : 0)
}

export function num(v: number | string | null | undefined): number {
  const n = Number(v ?? 0)
  return isFinite(n) ? n : 0
}

export function fmtDate(s?: string): string {
  if (!s) return '—'
  const d = new Date(s.replace(' ', 'T'))
  if (isNaN(d.getTime())) return s
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

export function decodeJwt<T = any>(token: string): T | null {
  try {
    const part = token.split('.')[1]
    const json = atob(part.replace(/-/g, '+').replace(/_/g, '/'))
    return JSON.parse(decodeURIComponent(escape(json))) as T
  } catch { return null }
}
