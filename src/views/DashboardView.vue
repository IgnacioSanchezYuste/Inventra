<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Line } from 'vue-chartjs'
import {
  Chart, CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend
} from 'chart.js'
import { useAnalyticsStore } from '../store/analytics'
import { useProductsStore } from '../store/products'
import { useSalesStore } from '../store/sales'
import { useAuthStore } from '../store/auth'
import { useExpensesStore } from '../store/expenses'
import { money, fmtDate, num } from '../utils/format'
import { dailyBuckets, topProducts } from '../utils/series'
import { useAutoRefresh } from '../utils/useAutoRefresh'
import Icon from '../components/Icon.vue'
import Sparkline from '../components/Sparkline.vue'
import StockBadge from '../components/StockBadge.vue'

Chart.register(CategoryScale, LinearScale, LineElement, PointElement, Filler, Tooltip, Legend)

const analytics = useAnalyticsStore()
const products = useProductsStore()
const sales = useSalesStore()
const auth = useAuthStore()
const expenses = useExpensesStore()
const router = useRouter()

const canSeeAnalytics = computed(() => auth.canManage)
const canSeeExpenses = computed(() => auth.canManage)

function refreshAll() {
  products.fetchAll(true)
  sales.fetchAll(true)
  if (canSeeAnalytics.value) analytics.refresh()
  if (canSeeExpenses.value) expenses.fetchAll(true)
}

onMounted(refreshAll)
useAutoRefresh(refreshAll, 20000)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Buenos días'
  if (h < 20) return 'Buenas tardes'
  return 'Buenas noches'
})

const today = computed(() =>
  new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

const series = computed(() => dailyBuckets(sales.recent, 7))
const top = computed(() => topProducts(sales.recent, 5))
const recentSales = computed(() => sales.recent.slice(0, 6))
const recentExpenses = computed(() => expenses.items.slice(0, 6))

// Resumen local (sales + products) — sirve a usuarios sin acceso a /analytics
const localSummary = computed(() => {
  const productMap = new Map(products.items.map(p => [p.id, p]))
  let revenue = 0, profit = 0
  for (const s of sales.recent) {
    const p = productMap.get(s.product_id)
    const cost = p ? num(p.cost) : 0
    revenue += num(s.total_price)
    profit += Number(s.quantity) * (num(s.unit_price) - cost)
  }
  const totalExpenses = expenses.total
  return {
    total_revenue: revenue,
    total_profit: profit,
    total_expenses: totalExpenses,
    expenses_this_month: 0,
    total_expense_entries: expenses.items.length,
    net_profit: profit - totalExpenses,
    total_sales: sales.recent.length,
    expenses_by_category: [],
    low_stock_products: products.lowStock.map(p => ({ id: p.id, name: p.name, stock: Number(p.stock) }))
  }
})

const summary = computed(() => analytics.summary || localSummary.value)
const summaryExpenses = computed(() => num(summary.value.total_expenses ?? 0))
const summaryNetProfit = computed(() => num(summary.value.net_profit ?? (num(summary.value.total_profit) - summaryExpenses.value)))

const lowStockList = computed(() => summary.value.low_stock_products || [])
const sessionRevenue = computed(() => sales.recent.reduce((a, s) => a + num(s.total_price), 0))

const profitSeries = computed(() => {
  // Beneficio diario: por día, suma qty*(unit_price - cost) de las ventas de ese día
  const productMap = new Map(products.items.map(p => [p.id, p]))
  const buckets = dailyBuckets(sales.recent, 7)
  const revenue: number[] = buckets.revenue
  const result: number[] = revenue.map(() => 0)
  // Recalcular por día con coste:
  const today = new Date(); today.setHours(0,0,0,0)
  for (let i = 6; i >= 0; i--) {
    const d = new Date(today); d.setDate(today.getDate() - i)
    const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
    let p = 0
    for (const s of sales.recent) {
      const sd = (s.created_at || '').slice(0, 10).replace(' ', 'T')
      const sDate = new Date((s.created_at || '').includes('T') ? s.created_at! : (s.created_at || '').replace(' ','T'))
      const sKey = isNaN(sDate.getTime()) ? sd : `${sDate.getFullYear()}-${String(sDate.getMonth()+1).padStart(2,'0')}-${String(sDate.getDate()).padStart(2,'0')}`
      if (sKey === key) {
        const prod = productMap.get(s.product_id)
        const cost = prod ? num(prod.cost) : 0
        p += Number(s.quantity) * (num(s.unit_price) - cost)
      }
    }
    result[6 - i] = Math.round(p * 100) / 100
  }
  return result
})

// Serie diaria de gastos (últimos 7 días) usando expense_date
const expensesSeries = computed(() => {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const out: number[] = []
  for (let i = 6; i >= 0; i--) {
    const d = new Date(today); d.setDate(today.getDate() - i)
    const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
    let acc = 0
    for (const e of expenses.items) {
      if ((e.expense_date || '').slice(0, 10) === key) acc += num(e.amount)
    }
    out.push(Math.round(acc * 100) / 100)
  }
  return out
})

const netProfitSeries = computed(() =>
  profitSeries.value.map((p, i) => Math.round((p - (expensesSeries.value[i] || 0)) * 100) / 100)
)

const kpis = computed(() => {
  const list: Array<{ label: string; value: string; icon: string; grad: string; spark: number[] }> = [
    { label: 'Ingresos totales', value: money(summary.value.total_revenue), icon: 'euro',     grad: 'g-indigo', spark: series.value.revenue },
    { label: 'Beneficio bruto',  value: money(summary.value.total_profit),  icon: 'trend_up', grad: 'g-green',  spark: profitSeries.value }
  ]
  if (canSeeExpenses.value) {
    list.push({ label: 'Gastos',         value: money(summaryExpenses.value),  icon: 'wallet',   grad: 'g-red',  spark: expensesSeries.value })
    list.push({ label: 'Beneficio neto', value: money(summaryNetProfit.value), icon: 'receipt',  grad: 'g-teal', spark: netProfitSeries.value })
  }
  list.push({ label: 'Ventas registradas',    value: String(summary.value.total_sales), icon: 'cart',    grad: 'g-amber', spark: series.value.count })
  list.push({ label: 'Productos en catálogo', value: String(products.items.length),     icon: 'package', grad: 'g-pink',  spark: products.items.slice(-7).map(p => Number(p.stock)) })
  return list
})

const lineData = computed(() => ({
  labels: series.value.labels,
  datasets: [{
    label: 'Ingresos',
    data: series.value.revenue,
    borderColor: '#4f46e5',
    backgroundColor: (ctx: any) => {
      const c = ctx?.chart?.ctx
      if (!c) return 'rgba(79,70,229,0.1)'
      const g = c.createLinearGradient(0, 0, 0, 220)
      g.addColorStop(0, 'rgba(79,70,229,0.35)')
      g.addColorStop(1, 'rgba(79,70,229,0)')
      return g
    },
    fill: true,
    tension: 0.35,
    pointRadius: 3,
    pointBackgroundColor: '#4f46e5'
  }]
}))

const lineOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1a1d29',
      padding: 10, cornerRadius: 8,
      callbacks: {
        label: (c: any) => money(c.parsed.y)
      }
    }
  },
  scales: {
    y: { beginAtZero: true, grid: { color: '#eef0f6' }, ticks: { font: { size: 11 }, callback: (v: any) => money(v) } },
    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
  }
}
</script>

<template>
  <section class="hero card">
    <div class="hero-bg"></div>
    <div class="hero-inner">
      <div>
        <div class="hello-line">
          <span class="dot-pulse"></span>
          <span class="muted">{{ today }}</span>
        </div>
        <h1>{{ greeting }}, {{ auth.user?.name?.split(' ')[0] }}</h1>
        <p class="muted" style="margin-top: 6px;">
          Aquí tienes el pulso de <strong>{{ auth.user?.company_name || 'tu empresa' }}</strong> en tiempo real.
        </p>
      </div>
      <div class="hero-actions">
        <button class="ghost" @click="refreshAll">
          <Icon name="refresh" /> Refrescar
        </button>
        <button @click="router.push('/sales')">
          <Icon name="plus" /> Nueva venta
        </button>
      </div>
    </div>
  </section>

  <div class="grid-kpis" style="margin-top: 20px;">
    <div v-for="k in kpis" :key="k.label" class="kpi-card" :class="k.grad">
      <div class="kpi-head">
        <div class="kpi-ico"><Icon :name="k.icon" :size="18" /></div>
        <span class="muted kpi-label">{{ k.label }}</span>
      </div>
      <div class="kpi-value">{{ k.value }}</div>
      <div class="kpi-spark">
        <Sparkline :data="k.spark" color="currentColor" fill />
      </div>
    </div>
  </div>

  <div class="dash-grid">
    <section class="card panel-chart">
      <div class="panel-head">
        <div>
          <h3>Actividad de ventas</h3>
          <p class="muted">Últimos 7 días · ingresos diarios</p>
        </div>
        <div class="badge brand">Total reciente: {{ money(sessionRevenue) }}</div>
      </div>

      <div v-if="!sales.recent.length" class="empty">
        <Icon name="chart" :size="32" />
        <h4>Sin ventas todavía</h4>
        <p>Registra una venta para ver la curva.</p>
        <button class="subtle" @click="router.push('/sales')" style="margin-top: 12px;">
          <Icon name="plus" /> Registrar venta
        </button>
      </div>

      <div v-else class="chart-wrap">
        <Line :data="lineData" :options="lineOpts" />
      </div>
    </section>

    <section class="card panel-quick">
      <h3>Acciones rápidas</h3>
      <p class="muted" style="font-size: 13px; margin: 4px 0 14px;">Atajos a las tareas comunes.</p>
      <button class="quick" @click="router.push('/sales')">
        <div class="qi g-indigo"><Icon name="cart" /></div>
        <div class="qt"><strong>Registrar venta</strong><span class="muted">Descuenta stock automáticamente</span></div>
        <Icon name="chevron_right" class="qa" />
      </button>
      <button class="quick" @click="router.push('/products')">
        <div class="qi g-green"><Icon name="plus" /></div>
        <div class="qt"><strong>Añadir producto</strong><span class="muted">Amplia tu catálogo</span></div>
        <Icon name="chevron_right" class="qa" />
      </button>
      <button v-if="canSeeExpenses" class="quick" @click="router.push('/expenses')">
        <div class="qi g-red"><Icon name="wallet" /></div>
        <div class="qt"><strong>Registrar gasto</strong><span class="muted">Alquiler, suministros, salarios…</span></div>
        <Icon name="chevron_right" class="qa" />
      </button>
      <button v-if="canSeeAnalytics" class="quick" @click="router.push('/analytics')">
        <div class="qi g-amber"><Icon name="chart" /></div>
        <div class="qt"><strong>Ver analítica</strong><span class="muted">Indicadores y tendencias</span></div>
        <Icon name="chevron_right" class="qa" />
      </button>
      <button v-if="auth.isAdmin" class="quick" @click="router.push('/company')">
        <div class="qi g-pink"><Icon name="user" /></div>
        <div class="qt"><strong>Gestionar equipo</strong><span class="muted">Invitar y administrar miembros</span></div>
        <Icon name="chevron_right" class="qa" />
      </button>
    </section>

    <section class="card panel-recent">
      <div class="panel-head">
        <div>
          <h3>Top productos</h3>
          <p class="muted">Por ingresos en ventas registradas</p>
        </div>
      </div>
      <div v-if="!top.length" class="empty small">
        <p>Las ventas registradas aparecerán aquí.</p>
      </div>
      <ol v-else class="rank">
        <li v-for="(t, i) in top" :key="t.name">
          <span class="pos">#{{ i + 1 }}</span>
          <div class="grow">
            <div class="rn">{{ t.name }}</div>
            <div class="muted" style="font-size: 12px;">{{ t.qty }} ud. vendidas</div>
          </div>
          <strong>{{ money(t.revenue) }}</strong>
        </li>
      </ol>
    </section>

    <section class="card panel-low">
      <div class="panel-head">
        <div>
          <h3>Stock bajo</h3>
          <p class="muted">Productos por debajo del umbral (5)</p>
        </div>
        <RouterLink to="/products" class="link">
          Ver todos <Icon name="arrow_right" :size="14" />
        </RouterLink>
      </div>

      <div v-if="!lowStockList.length" class="empty small">
        <Icon name="check" :size="28" />
        <p style="margin-top: 6px;">Stock saludable en todo el catálogo.</p>
      </div>
      <div v-else class="low-list">
        <div v-for="p in lowStockList.slice(0, 6)" :key="p.id" class="low-row">
          <div class="lp">
            <div class="lp-icon"><Icon name="alert" /></div>
            <div>
              <div class="rn">{{ p.name }}</div>
              <div class="muted" style="font-size: 12px;">ID #{{ p.id }}</div>
            </div>
          </div>
          <StockBadge :stock="p.stock" />
        </div>
      </div>
    </section>

    <section class="card panel-feed">
      <h3>Actividad reciente</h3>
      <p class="muted" style="font-size: 13px; margin: 4px 0 12px;">Últimas ventas de tu empresa.</p>
      <div v-if="!recentSales.length" class="empty small">
        <p>Aún sin actividad.</p>
      </div>
      <ul v-else class="feed">
        <li v-for="s in recentSales" :key="s.id">
          <div class="fb"><Icon name="cart" :size="14" /></div>
          <div class="grow">
            <div><strong>{{ s.product_name }}</strong> <span class="muted">× {{ s.quantity }}</span></div>
            <div class="muted" style="font-size: 12px;">{{ fmtDate(s.created_at) }}<span v-if="s.seller_name"> · {{ s.seller_name }}</span></div>
          </div>
          <strong class="brand-text">{{ money(s.total_price) }}</strong>
        </li>
      </ul>
    </section>

    <section v-if="canSeeExpenses" class="card panel-expenses">
      <div class="panel-head">
        <div>
          <h3>Gastos recientes</h3>
          <p class="muted">Últimos movimientos registrados</p>
        </div>
        <RouterLink to="/expenses" class="link">
          Ver todos <Icon name="arrow_right" :size="14" />
        </RouterLink>
      </div>
      <div v-if="!recentExpenses.length" class="empty small">
        <Icon name="wallet" :size="28" />
        <p style="margin-top: 6px;">Aún no se han registrado gastos.</p>
        <button class="subtle" @click="router.push('/expenses')" style="margin-top: 8px;">
          <Icon name="plus" /> Añadir gasto
        </button>
      </div>
      <ul v-else class="feed">
        <li v-for="e in recentExpenses" :key="e.id">
          <div class="fb fb-red"><Icon name="wallet" :size="14" /></div>
          <div class="grow">
            <div><strong>{{ e.description }}</strong></div>
            <div class="muted" style="font-size: 12px;">
              {{ fmtDate(e.expense_date) }}<span v-if="e.category"> · {{ e.category }}</span><span v-if="e.user_name"> · {{ e.user_name }}</span>
            </div>
          </div>
          <strong class="expense-text">−{{ money(e.amount) }}</strong>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.hero {
  position: relative; overflow: hidden;
  padding: 28px;
}
@media (max-width: 600px) {
  .hero { padding: 20px; }
  .hero h1 { font-size: 20px; }
  .kpi-value { font-size: 20px; }
  .panel-head { flex-wrap: wrap; }
  .grid-kpis { gap: 10px; }
}
.hero-bg {
  position: absolute; inset: 0; pointer-events: none;
  background:
    radial-gradient(600px 200px at 90% 10%, rgba(99, 102, 241, .14), transparent 65%),
    radial-gradient(600px 200px at 10% 100%, rgba(16, 185, 129, .12), transparent 65%);
}
.hero-inner {
  position: relative;
  display: flex; justify-content: space-between; align-items: flex-end;
  gap: 24px; flex-wrap: wrap;
}
.hero h1 { font-size: 26px; margin-top: 8px; letter-spacing: -.01em; }
.hello-line { display: flex; gap: 8px; align-items: center; font-size: 13px; text-transform: capitalize; }
.dot-pulse {
  width: 8px; height: 8px; border-radius: 50%; background: var(--success);
  box-shadow: 0 0 0 0 rgba(16, 185, 129, .6);
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, .5); }
  70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.hero-actions { display: flex; gap: 8px; }
.hero-actions button { display: inline-flex; gap: 6px; align-items: center; }

.kpi-card {
  position: relative; overflow: hidden;
  padding: 18px 20px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
  display: flex; flex-direction: column; gap: 10px;
  transition: transform .15s, box-shadow .15s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.kpi-card::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background: var(--g, transparent);
  opacity: .08;
}
.kpi-card.g-indigo { --g: linear-gradient(135deg, #6366f1, #4f46e5); color: #4f46e5; }
.kpi-card.g-green  { --g: linear-gradient(135deg, #10b981, #059669); color: #059669; }
.kpi-card.g-amber  { --g: linear-gradient(135deg, #f59e0b, #d97706); color: #d97706; }
.kpi-card.g-pink   { --g: linear-gradient(135deg, #ec4899, #db2777); color: #db2777; }
.kpi-card.g-red    { --g: linear-gradient(135deg, #f87171, #b91c1c); color: #b91c1c; }
.kpi-card.g-teal   { --g: linear-gradient(135deg, #14b8a6, #0f766e); color: #0f766e; }

.kpi-head { display: flex; align-items: center; gap: 10px; }
.kpi-ico {
  width: 32px; height: 32px; border-radius: 9px;
  background: var(--g, var(--primary));
  color: #fff;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.kpi-label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; font-weight: 500; }
.kpi-value { font-size: 24px; font-weight: 700; color: var(--text); letter-spacing: -.02em; }
.kpi-spark { margin-top: auto; height: 32px; }

.dash-grid {
  display: grid; gap: 16px; margin-top: 20px;
  grid-template-columns: 2fr 1fr;
  grid-auto-flow: dense;
}
.panel-chart { grid-column: 1 / 2; }
.panel-quick { grid-column: 2 / 3; grid-row: span 2; }
.panel-recent { grid-column: 1 / 2; }
.panel-low { grid-column: 1 / 3; }
.panel-feed { grid-column: 1 / 3; }
.panel-expenses { grid-column: 1 / 3; }

@media (max-width: 980px) {
  .dash-grid { grid-template-columns: 1fr; }
  .panel-chart, .panel-quick, .panel-recent, .panel-low, .panel-feed, .panel-expenses { grid-column: auto; grid-row: auto; }
}

.panel-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; margin-bottom: 14px;
}
.panel-head h3 { font-size: 15px; margin: 0; }
.panel-head p { font-size: 12px; margin: 2px 0 0; }

.chart-wrap { height: 240px; position: relative; }

.empty {
  text-align: center; padding: 32px 16px;
  color: var(--text-muted);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.empty.small { padding: 20px 12px; }
.empty h4 { color: var(--text); margin: 4px 0 0; font-size: 14px; font-weight: 600; }
.empty p { font-size: 13px; margin: 0; }

.quick {
  display: flex; align-items: center; gap: 12px;
  width: 100%; padding: 12px;
  background: transparent; color: var(--text);
  border-radius: var(--radius-sm);
  text-align: left; margin-bottom: 6px;
  border: 1px solid var(--border);
  transition: background .12s, border-color .12s, transform .08s;
}
.quick:hover { background: var(--surface-2); border-color: #d4d6df; }
.quick:active { transform: translateY(1px); }
.qi {
  width: 36px; height: 36px; border-radius: 9px;
  display: grid; place-items: center; color: #fff;
  flex-shrink: 0;
}
.qi.g-indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.qi.g-green  { background: linear-gradient(135deg, #10b981, #059669); }
.qi.g-amber  { background: linear-gradient(135deg, #f59e0b, #d97706); }
.qi.g-pink   { background: linear-gradient(135deg, #ec4899, #db2777); }
.qi.g-red    { background: linear-gradient(135deg, #f87171, #b91c1c); }
.qi.g-teal   { background: linear-gradient(135deg, #14b8a6, #0f766e); }
.qt { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.qt strong { font-size: 13px; font-weight: 600; }
.qt span { font-size: 12px; }
.qa { color: var(--text-muted); }

.rank { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 2px; }
.rank li {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 8px; border-radius: var(--radius-sm);
  transition: background .1s;
}
.rank li:hover { background: var(--surface-2); }
.pos {
  font-weight: 700; color: var(--primary);
  width: 28px; text-align: center;
  font-size: 13px;
}
.rn { font-weight: 500; }

.low-list { display: flex; flex-direction: column; gap: 6px; }
.low-row {
  display: flex; justify-content: space-between; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: var(--radius-sm);
  background: var(--surface-2);
  transition: background .1s;
}
.low-row:hover { background: #f3f4fb; }
.lp { display: flex; align-items: center; gap: 10px; }
.lp-icon {
  width: 30px; height: 30px; border-radius: 8px;
  background: var(--warning-soft); color: #92400e;
  display: grid; place-items: center;
}

.feed { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.feed li {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 8px; border-radius: var(--radius-sm);
}
.feed li:hover { background: var(--surface-2); }
.fb {
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center;
}
.brand-text { color: var(--primary); }
.expense-text { color: #b91c1c; }
.fb.fb-red {
  background: var(--danger-soft); color: #b91c1c;
}

.link { display: inline-flex; gap: 4px; align-items: center; font-size: 12px; }
</style>
