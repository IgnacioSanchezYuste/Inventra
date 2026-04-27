<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Doughnut, Bar, Line } from 'vue-chartjs'
import {
  Chart, ArcElement, Tooltip, Legend, CategoryScale, LinearScale,
  BarElement, LineElement, PointElement, Filler, Title
} from 'chart.js'
import { useAnalyticsStore } from '../store/analytics'
import { useProductsStore } from '../store/products'
import { useSalesStore } from '../store/sales'
import { useExpensesStore } from '../store/expenses'
import { money, num } from '../utils/format'
import { dailyBuckets, topProducts } from '../utils/series'
import { useAutoRefresh } from '../utils/useAutoRefresh'
import Icon from '../components/Icon.vue'

Chart.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, LineElement, PointElement, Filler, Title)

const analytics = useAnalyticsStore()
const products = useProductsStore()
const sales = useSalesStore()
const expenses = useExpensesStore()

function refreshAll() { analytics.refresh(); products.fetchAll(true); sales.fetchAll(true); expenses.fetchAll(true) }
onMounted(refreshAll)
useAutoRefresh(refreshAll, 20000)

const grossMargin = computed(() => {
  const rev = num(analytics.summary?.total_revenue)
  const prof = num(analytics.summary?.total_profit)
  if (!rev) return 0
  return Math.round((prof / rev) * 1000) / 10
})

const netMargin = computed(() => {
  const rev = num(analytics.summary?.total_revenue)
  const net = num(analytics.summary?.net_profit)
  if (!rev) return 0
  return Math.round((net / rev) * 1000) / 10
})

const totalExpenses = computed(() => num(analytics.summary?.total_expenses ?? expenses.total))
const expensesThisMonth = computed(() => num(analytics.summary?.expenses_this_month ?? 0))
const netProfit = computed(() => num(
  analytics.summary?.net_profit
  ?? (num(analytics.summary?.total_profit) - totalExpenses.value)
))

const series = computed(() => dailyBuckets(sales.recent, 14))
const top = computed(() => topProducts(sales.recent, 6))

// Serie diaria de gastos (mismos 14 días)
const expensesDaily = computed(() => {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const out: number[] = []
  for (let i = 13; i >= 0; i--) {
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

const profitabilityData = computed(() => {
  const rev = num(analytics.summary?.total_revenue)
  const grossProfit = num(analytics.summary?.total_profit)
  const productCost = Math.max(0, rev - grossProfit)
  const expVal = totalExpenses.value
  const net = Math.max(0, grossProfit - expVal)
  return {
    labels: ['Beneficio neto', 'Coste de productos', 'Gastos operativos'],
    datasets: [{
      data: [net, productCost, expVal],
      backgroundColor: ['#10b981', '#e5e7eb', '#ef4444'],
      borderWidth: 0,
      borderRadius: 4
    }]
  }
})

// Categorías de gasto (del backend si está, si no del store)
const expensesCategoryData = computed(() => {
  const fromBackend = analytics.summary?.expenses_by_category || []
  const list = (fromBackend.length
    ? fromBackend.map(c => ({ category: c.category, total: num(c.total) }))
    : expenses.byCategory
  ).slice(0, 8)
  return {
    labels: list.map(c => c.category),
    datasets: [{
      data: list.map(c => c.total),
      backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#06b6d4', '#8b5cf6', '#ec4899'],
      borderWidth: 0
    }]
  }
})

const flowData = computed(() => ({
  labels: series.value.labels,
  datasets: [
    {
      label: 'Ingresos',
      data: series.value.revenue,
      borderColor: '#4f46e5',
      backgroundColor: 'rgba(79,70,229,0.12)',
      fill: true,
      tension: 0.35,
      pointRadius: 2
    },
    {
      label: 'Gastos',
      data: expensesDaily.value,
      borderColor: '#ef4444',
      backgroundColor: 'rgba(239,68,68,0.12)',
      fill: true,
      tension: 0.35,
      pointRadius: 2
    }
  ]
}))

const lineData = computed(() => ({
  labels: series.value.labels,
  datasets: [{
    label: 'Ingresos (€)',
    data: series.value.revenue,
    borderColor: '#4f46e5',
    backgroundColor: (ctx: any) => {
      const c = ctx.chart.ctx
      const g = c.createLinearGradient(0, 0, 0, 240)
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

const topData = computed(() => ({
  labels: top.value.map(t => t.name),
  datasets: [{
    label: 'Ingresos (€)',
    data: top.value.map(t => t.revenue),
    backgroundColor: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#e0e7ff'],
    borderRadius: 6,
    borderSkipped: false
  }]
}))

const stockByCategory = computed(() => {
  const tally = new Map<string, number>()
  for (const p of products.items) {
    const k = p.category || 'Sin categoría'
    tally.set(k, (tally.get(k) || 0) + Number(p.stock))
  }
  const entries = [...tally.entries()].sort((a, b) => b[1] - a[1])
  return {
    labels: entries.map(e => e[0]),
    datasets: [{
      data: entries.map(e => e[1]),
      backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16'],
      borderWidth: 0
    }]
  }
})

const baseOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1a1d29',
      padding: 10,
      cornerRadius: 8,
      titleFont: { weight: 'bold' as const, size: 12 },
      bodyFont: { size: 12 }
    }
  }
}
const lineOpts = {
  ...baseOpts,
  scales: {
    y: { beginAtZero: true, grid: { color: '#eef0f6' }, ticks: { font: { size: 11 } } },
    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
  }
}
const barOpts = {
  ...baseOpts,
  scales: {
    y: { beginAtZero: true, grid: { color: '#eef0f6' }, ticks: { font: { size: 11 } } },
    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
  }
}
const donutOpts = {
  ...baseOpts,
  cutout: '65%',
  plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom' as const, labels: { boxWidth: 10, font: { size: 11 } } } }
}
const lineMultiOpts = {
  ...lineOpts,
  plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom' as const, labels: { boxWidth: 10, font: { size: 11 } } } }
}
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Analítica</h1>
      <div class="sub">Indicadores y tendencias de tu negocio.</div>
    </div>
    <button class="ghost" @click="refreshAll">
      <Icon name="refresh" /> Refrescar
    </button>
  </div>

  <div class="grid-stats">
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="euro" :size="18" style="color: var(--primary)" /><span class="label">Ingresos</span></div>
      <div class="value">{{ money(analytics.summary?.total_revenue ?? 0) }}</div>
      <div class="delta"><span class="badge brand">Total acumulado</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="trend_up" :size="18" style="color: #059669" /><span class="label">Beneficio bruto</span></div>
      <div class="value">{{ money(analytics.summary?.total_profit ?? 0) }}</div>
      <div class="delta"><span class="badge ok">Margen bruto {{ grossMargin }}%</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="wallet" :size="18" style="color: #b91c1c" /><span class="label">Gastos</span></div>
      <div class="value">{{ money(totalExpenses) }}</div>
      <div class="delta"><span class="badge bad" v-if="expensesThisMonth > 0">{{ money(expensesThisMonth) }} este mes</span><span v-else class="badge">Sin gastos este mes</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="receipt" :size="18" style="color: #0f766e" /><span class="label">Beneficio neto</span></div>
      <div class="value" :style="{ color: netProfit < 0 ? '#b91c1c' : 'inherit' }">{{ money(netProfit) }}</div>
      <div class="delta"><span class="badge" :class="netProfit < 0 ? 'bad' : 'ok'">Margen neto {{ netMargin }}%</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="cart" :size="18" style="color: #d97706" /><span class="label">Operaciones</span></div>
      <div class="value">{{ analytics.summary?.total_sales ?? 0 }}</div>
      <div class="delta"><span class="badge warn">Ventas registradas</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="package" :size="18" style="color: #db2777" /><span class="label">Catálogo</span></div>
      <div class="value">{{ products.items.length }}</div>
      <div class="delta"><span class="badge bad" v-if="products.lowStock.length">{{ products.lowStock.length }} en stock bajo</span><span v-else class="badge ok">Sano</span></div>
    </div>
  </div>

  <div class="charts">
    <section class="card span-2">
      <div class="row" style="justify-content: space-between;">
        <div>
          <h3>Tendencia de ingresos</h3>
          <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Ventas de los últimos 14 días en esta sesión.</p>
        </div>
      </div>
      <div v-if="!sales.recent.length" class="empty">
        <Icon name="chart" :size="32" />
        <p>Sin ventas registradas todavía.</p>
      </div>
      <div v-else class="chart-wrap tall">
        <Line :data="lineData" :options="lineOpts" />
      </div>
    </section>

    <section class="card">
      <h3>Composición de ingresos</h3>
      <p class="muted" style="font-size: 12px; margin: 2px 0 12px;">Beneficio neto vs coste vs gastos.</p>
      <div class="chart-wrap">
        <Doughnut :data="profitabilityData" :options="donutOpts" />
      </div>
    </section>

    <section class="card span-2">
      <div class="row" style="justify-content: space-between;">
        <div>
          <h3>Ingresos vs Gastos</h3>
          <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Flujo diario de los últimos 14 días.</p>
        </div>
      </div>
      <div v-if="!sales.recent.length && !expenses.items.length" class="empty">
        <Icon name="chart" :size="32" />
        <p>Aún no hay actividad para representar.</p>
      </div>
      <div v-else class="chart-wrap tall">
        <Line :data="flowData" :options="lineMultiOpts" />
      </div>
    </section>

    <section class="card">
      <h3>Gastos por categoría</h3>
      <p class="muted" style="font-size: 12px; margin: 2px 0 12px;">Distribución del gasto total.</p>
      <div v-if="!expenses.items.length" class="empty">
        <Icon name="wallet" :size="32" />
        <p>Sin gastos registrados.</p>
      </div>
      <div v-else class="chart-wrap">
        <Doughnut :data="expensesCategoryData" :options="donutOpts" />
      </div>
    </section>

    <section class="card">
      <h3>Stock por categoría</h3>
      <p class="muted" style="font-size: 12px; margin: 2px 0 12px;">Unidades disponibles.</p>
      <div v-if="!products.items.length" class="empty"><p>Sin datos.</p></div>
      <div v-else class="chart-wrap">
        <Doughnut :data="stockByCategory" :options="donutOpts" />
      </div>
    </section>

    <section class="card span-2">
      <h3>Top productos</h3>
      <p class="muted" style="font-size: 12px; margin: 2px 0 12px;">Por ingresos en ventas registradas.</p>
      <div v-if="!top.length" class="empty"><p>Registra ventas para ver el ranking.</p></div>
      <div v-else class="chart-wrap">
        <Bar :data="topData" :options="barOpts" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.grid-stats {
  display: grid; gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  margin-bottom: 16px;
}
.kpi .label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 500; }
.kpi .value { font-size: 24px; font-weight: 700; letter-spacing: -.02em; }

.charts {
  display: grid; gap: 16px;
  grid-template-columns: repeat(2, 1fr);
}
.span-2 { grid-column: span 2; }
@media (max-width: 900px) {
  .charts { grid-template-columns: 1fr; }
  .span-2 { grid-column: auto; }
}
.chart-wrap { height: 260px; position: relative; }
.chart-wrap.tall { height: 320px; }
@media (max-width: 600px) {
  .chart-wrap { height: 220px; }
  .chart-wrap.tall { height: 260px; }
}
.empty { padding: 24px 16px; text-align: center; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 6px; }
.empty p { margin: 0; font-size: 13px; }
</style>
