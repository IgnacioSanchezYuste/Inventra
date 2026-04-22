<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAnalyticsStore } from '../store/analytics'
import { useProductsStore } from '../store/products'
import { useAuthStore } from '../store/auth'
import { money } from '../utils/format'
import StockBadge from '../components/StockBadge.vue'

const analytics = useAnalyticsStore()
const products = useProductsStore()
const auth = useAuthStore()
const router = useRouter()

const canSeeAnalytics = computed(() => auth.canManage)

onMounted(() => {
  if (canSeeAnalytics.value) analytics.fetch()
  products.fetchAll()
})

const kpis = computed(() => [
  { label: 'Ingresos', value: money(analytics.summary?.total_revenue ?? 0), accent: 'brand' },
  { label: 'Beneficios', value: money(analytics.summary?.total_profit ?? 0), accent: 'ok' },
  { label: 'Ventas', value: String(analytics.summary?.total_sales ?? 0), accent: 'brand' },
  { label: 'Stock bajo', value: String(analytics.summary?.low_stock_products?.length ?? 0), accent: 'warn' }
])
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Hola, {{ auth.user?.name?.split(' ')[0] }} 👋</h1>
      <div class="sub">Resumen de la operación.</div>
    </div>
    <div class="row">
      <button class="ghost" @click="analytics.refresh(); products.fetchAll(true)">↻ Refrescar</button>
      <button @click="router.push('/sales')">+ Registrar venta</button>
    </div>
  </div>

  <div v-if="!canSeeAnalytics" class="card empty">
    <h4>Bienvenido</h4>
    <p>Tu rol no tiene acceso a métricas. Usa el menú para gestionar productos y ventas.</p>
  </div>

  <template v-else>
    <div class="grid-kpis">
      <div v-for="k in kpis" :key="k.label" class="card kpi">
        <div class="label">{{ k.label }}</div>
        <div class="value">{{ k.value }}</div>
        <div class="delta">
          <span class="badge" :class="k.accent">en vivo</span>
        </div>
      </div>
    </div>

    <div class="row" style="margin-top: 20px; align-items: stretch;">
      <section class="card grow">
        <div class="row" style="justify-content: space-between;">
          <h3>Stock bajo</h3>
          <RouterLink to="/products" class="muted" style="font-size: 13px;">Ver productos →</RouterLink>
        </div>

        <div v-if="analytics.loading && !analytics.summary" class="empty">
          <div class="spinner" style="margin: 0 auto;"></div>
        </div>
        <div v-else-if="!analytics.summary?.low_stock_products?.length" class="empty">
          <h4>Todo correcto</h4>
          <p>Ningún producto bajo el umbral.</p>
        </div>
        <table v-else style="margin-top: 12px;">
          <thead><tr><th>Producto</th><th>ID</th><th style="text-align:right;">Stock</th></tr></thead>
          <tbody>
            <tr v-for="p in analytics.summary.low_stock_products" :key="p.id">
              <td>{{ p.name }}</td>
              <td class="muted">#{{ p.id }}</td>
              <td style="text-align:right;"><StockBadge :stock="p.stock" /></td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </template>
</template>
