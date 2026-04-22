<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useProductsStore } from '../store/products'
import { useSalesStore } from '../store/sales'
import { useToast } from '../utils/toast'
import { money, fmtDate, num } from '../utils/format'

const products = useProductsStore()
const sales = useSalesStore()
const toast = useToast()

const productId = ref<number | null>(null)
const quantity = ref(1)
const filterProduct = ref<number | null>(null)
const filterDate = ref('')

const selected = computed(() => productId.value ? products.byId(productId.value) : null)
const lineTotal = computed(() => selected.value ? num(selected.value.price) * Math.max(0, quantity.value) : 0)
const maxStock = computed(() => selected.value ? Number(selected.value.stock) : 0)

const filteredSales = computed(() => {
  let arr = sales.byProduct(filterProduct.value)
  if (filterDate.value) {
    const d = filterDate.value
    arr = arr.filter(s => (s.created_at || '').slice(0, 10) === d)
  }
  return arr
})

const totalFiltered = computed(() => filteredSales.value.reduce((a, s) => a + num(s.total_price), 0))

onMounted(() => products.fetchAll())

async function submit() {
  if (!productId.value || quantity.value < 1) return
  try {
    await sales.create(productId.value, quantity.value)
    toast.success('Venta registrada')
    quantity.value = 1
    if (selected.value && Number(selected.value.stock) <= 0) productId.value = null
  } catch (e: any) {
    toast.error(sales.error || 'No se pudo registrar')
  }
}
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Ventas</h1>
      <div class="sub">Registra una venta y consulta el histórico de esta sesión.</div>
    </div>
  </div>

  <div class="grid">
    <section class="card form-card">
      <h3>Nueva venta</h3>
      <p class="muted" style="margin: 4px 0 16px; font-size: 13px;">El stock se descuenta automáticamente.</p>

      <form @submit.prevent="submit" class="col">
        <div>
          <label>Producto</label>
          <select v-model="productId" required>
            <option :value="null" disabled>Selecciona…</option>
            <option v-for="p in products.items" :key="p.id" :value="p.id" :disabled="Number(p.stock) <= 0">
              {{ p.name }} · {{ money(p.price) }} ({{ Number(p.stock) > 0 ? `${p.stock} ud` : 'Sin stock' }})
            </option>
          </select>
        </div>

        <div>
          <label>Cantidad</label>
          <input v-model.number="quantity" type="number" min="1" :max="maxStock || undefined" required />
          <div v-if="selected" class="muted" style="font-size: 12px; margin-top: 4px;">
            Stock disponible: {{ maxStock }}
          </div>
        </div>

        <div v-if="selected" class="recap">
          <div><span class="muted">Precio unitario</span><strong>{{ money(selected.price) }}</strong></div>
          <div><span class="muted">Cantidad</span><strong>{{ quantity }}</strong></div>
          <div class="total"><span>Total</span><strong>{{ money(lineTotal) }}</strong></div>
        </div>

        <button :disabled="sales.loading || !productId || quantity < 1 || quantity > maxStock" type="submit">
          <span v-if="sales.loading" class="spinner" style="margin-right: 8px;"></span>
          {{ sales.loading ? 'Registrando…' : 'Registrar venta' }}
        </button>
      </form>
    </section>

    <section class="card history-card">
      <div class="row" style="justify-content: space-between; flex-wrap: wrap;">
        <h3>Histórico (sesión)</h3>
        <div class="row" style="gap: 8px;">
          <select v-model="filterProduct" style="width: 200px;">
            <option :value="null">Todos los productos</option>
            <option v-for="p in products.items" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <input type="date" v-model="filterDate" style="width: 160px;" />
        </div>
      </div>

      <p class="muted" style="font-size: 12px; margin: 6px 0 12px;">
        La API no expone listado histórico; mostramos las ventas registradas en este dispositivo.
      </p>

      <div v-if="!filteredSales.length" class="empty">
        <h4>Sin ventas</h4>
        <p>Registra una venta para verla aquí.</p>
      </div>

      <div v-else style="overflow-x: auto;">
        <table>
          <thead>
            <tr><th>Fecha</th><th>Producto</th><th>Cant.</th><th>P.U.</th><th style="text-align:right;">Total</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in filteredSales" :key="s.id">
              <td>{{ fmtDate(s.created_at) }}</td>
              <td>{{ s.product_name }}</td>
              <td>{{ s.quantity }}</td>
              <td>{{ money(s.unit_price) }}</td>
              <td style="text-align: right; font-weight: 600;">{{ money(s.total_price) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="muted" style="text-align: right;">Total filtrado</td>
              <td style="text-align: right; font-weight: 600;">{{ money(totalFiltered) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.grid {
  display: grid; gap: 20px;
  grid-template-columns: 360px 1fr;
}
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

.recap {
  background: var(--surface-2);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  display: flex; flex-direction: column; gap: 6px;
  font-size: 13px;
}
.recap > div { display: flex; justify-content: space-between; }
.recap .total { padding-top: 8px; border-top: 1px solid var(--border); font-size: 15px; }

tfoot td { background: var(--surface-2); }
</style>
