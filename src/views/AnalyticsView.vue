<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Doughnut, Bar } from 'vue-chartjs'
import {
  Chart, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title
} from 'chart.js'
import { useAnalyticsStore } from '../store/analytics'
import { useProductsStore } from '../store/products'
import { useSalesStore } from '../store/sales'
import { money, num } from '../utils/format'

Chart.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title)

const analytics = useAnalyticsStore()
const products = useProductsStore()
const sales = useSalesStore()

onMounted(() => { analytics.fetch(); products.fetchAll() })

const margin = computed(() => {
  const rev = num(analytics.summary?.total_revenue)
  const prof = num(analytics.summary?.total_profit)
  if (!rev) return 0
  return Math.round((prof / rev) * 1000) / 10
})

const profitabilityData = computed(() => {
  const rev = num(analytics.summary?.total_revenue)
  const prof = num(analytics.summary?.total_profit)
  const cost = Math.max(0, rev - prof)
  return {
    labels: ['Beneficio', 'Coste'],
    datasets: [{
      data: [prof, cost],
      backgroundColor: ['#10b981', '#e5e7eb'],
      borderWidth: 0
    }]
  }
})

const topProductsData = computed(() => {
  const tally = new Map<number, { name: string; total: number; qty: number }>()
  for (const s of sales.recent) {
    const t = tally.get(s.product_id) || { name: s.product_name, total: 0, qty: 0 }
    t.total += num(s.total_price)
    t.qty += Number(s.quantity)
    tally.set(s.product_id, t)
  }
  const top = [...tally.values()].sort((a, b) => b.total - a.total).slice(0, 6)
  return {
    labels: top.map(t => t.name),
    datasets: [{
      label: 'Ingresos (€)',
      data: top.map(t => t.total),
      backgroundColor: '#4f46e5',
      borderRadius: 6
    }]
  }
})

const stockByCategory = computed(() => {
  const tally = new Map<string, number>()
  for (const p of products.items) {
    const k = p.category || 'Sin categoría'
    tally.set(k, (tally.get(k) || 0) + Number(p.stock))
  }
  const entries = [...tally.entries()]
  return {
    labels: entries.map(e => e[0]),
    datasets: [{
      label: 'Unidades',
      data: entries.map(e => e[1]),
      backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899'],
      borderWidth: 0
    }]
  }
})

const chartOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' as const } } }
const barOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, grid: { color: '#eef0f6' } }, x: { grid: { display: false } } }
}
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Analítica</h1>
      <div class="sub">Indicadores y tendencias de tu negocio.</div>
    </div>
    <button class="ghost" @click="analytics.refresh()">↻ Refrescar</button>
  </div>

  <div class="grid-kpis">
    <div class="card kpi">
      <div class="label">Ingresos</div>
      <div class="value">{{ money(analytics.summary?.total_revenue ?? 0) }}</div>
      <div class="delta"><span class="badge brand">Total</span></div>
    </div>
    <div class="card kpi">
      <div class="label">Beneficio</div>
      <div class="value">{{ money(analytics.summary?.total_profit ?? 0) }}</div>
      <div class="delta"><span class="badge ok">Margen {{ margin }}%</span></div>
    </div>
    <div class="card kpi">
      <div class="label">Ventas</div>
      <div class="value">{{ analytics.summary?.total_sales ?? 0 }}</div>
      <div class="delta"><span class="badge brand">Operaciones</span></div>
    </div>
    <div class="card kpi">
      <div class="label">Productos</div>
      <div class="value">{{ products.items.length }}</div>
      <div class="delta">
        <span class="badge warn">{{ products.lowStock.length }} bajos</span>
      </div>
    </div>
  </div>

  <div class="charts">
    <section class="card">
      <h3 style="margin-bottom: 4px;">Ingresos vs Coste</h3>
      <p class="muted" style="font-size: 13px; margin: 0 0 12px;">Distribución global del periodo.</p>
      <div class="chart-wrap">
        <Doughnut :data="profitabilityData" :options="chartOpts" />
      </div>
    </section>

    <section class="card">
      <h3 style="margin-bottom: 4px;">Top productos (sesión)</h3>
      <p class="muted" style="font-size: 13px; margin: 0 0 12px;">Por ingresos en ventas registradas.</p>
      <div v-if="!sales.recent.length" class="empty">
        <p>Registra ventas para ver el ranking.</p>
      </div>
      <div v-else class="chart-wrap">
        <Bar :data="topProductsData" :options="barOpts" />
      </div>
    </section>

    <section class="card span-2">
      <h3 style="margin-bottom: 4px;">Stock por categoría</h3>
      <p class="muted" style="font-size: 13px; margin: 0 0 12px;">Unidades disponibles.</p>
      <div v-if="!products.items.length" class="empty"><p>Sin datos.</p></div>
      <div v-else class="chart-wrap tall">
        <Doughnut :data="stockByCategory" :options="chartOpts" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.charts {
  display: grid; gap: 16px; margin-top: 20px;
  grid-template-columns: repeat(2, 1fr);
}
.span-2 { grid-column: span 2; }
@media (max-width: 800px) {
  .charts { grid-template-columns: 1fr; }
  .span-2 { grid-column: auto; }
}
.chart-wrap { height: 260px; position: relative; }
.chart-wrap.tall { height: 320px; }
</style>
