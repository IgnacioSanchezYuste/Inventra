<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useProductsStore } from '../store/products'
import { useSalesStore } from '../store/sales'
import { useToast } from '../utils/toast'
import { money, fmtDate, num } from '../utils/format'
import { apiError } from '../api/http'
import Icon from '../components/Icon.vue'
import { useAutoRefresh } from '../utils/useAutoRefresh'
import type { Product } from '../api/types'

interface CartLine {
  product_id: number
  name: string
  unit_price: number
  qty: number
  stock: number
}

const products = useProductsStore()
const sales = useSalesStore()
const toast = useToast()

const search = ref('')
const cart = ref<CartLine[]>([])
const submitting = ref(false)

const filterProduct = ref<number | null>(null)
const filterDate = ref('')

function refreshAll() { products.fetchAll(true); sales.fetchAll(true) }
onMounted(refreshAll)
useAutoRefresh(refreshAll, 15000)

const available = computed(() => {
  const q = search.value.trim().toLowerCase()
  return products.items
    .filter(p => Number(p.stock) > 0)
    .filter(p => !q || p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q))
})

const cartTotal = computed(() => cart.value.reduce((a, l) => a + l.unit_price * l.qty, 0))
const cartUnits = computed(() => cart.value.reduce((a, l) => a + l.qty, 0))

function addToCart(p: Product) {
  const found = cart.value.find(l => l.product_id === p.id)
  const max = Number(p.stock)
  if (found) {
    if (found.qty < max) found.qty++
    else toast.error(`Stock máximo: ${max}`)
    return
  }
  cart.value.push({
    product_id: p.id,
    name: p.name,
    unit_price: num(p.price),
    qty: 1,
    stock: max
  })
}

function inc(l: CartLine) { if (l.qty < l.stock) l.qty++; else toast.error(`Stock máximo: ${l.stock}`) }
function dec(l: CartLine) { if (l.qty > 1) l.qty--; else removeLine(l) }
function removeLine(l: CartLine) { cart.value = cart.value.filter(x => x !== l) }
function clearCart() { cart.value = [] }

async function submit() {
  if (!cart.value.length) return
  submitting.value = true
  let ok = 0
  const failures: string[] = []
  const remaining: CartLine[] = []
  for (const line of cart.value.slice()) {
    try {
      await sales.create(line.product_id, line.qty)
      ok++
    } catch (e: any) {
      failures.push(`${line.name}: ${apiError(e)}`)
      remaining.push(line)
    }
  }
  submitting.value = false
  cart.value = remaining
  if (ok) toast.success(`${ok} venta(s) registradas`)
  if (failures.length) toast.error(failures.join(' · '))
}

const filteredHistory = computed(() => {
  let arr = sales.byProduct(filterProduct.value)
  if (filterDate.value) {
    arr = arr.filter(s => (s.created_at || '').slice(0, 10) === filterDate.value)
  }
  return arr
})

const totalFiltered = computed(() => filteredHistory.value.reduce((a, s) => a + num(s.total_price), 0))
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Ventas</h1>
      <div class="sub">Punto de venta y actividad reciente.</div>
    </div>
    <button class="ghost" @click="refreshAll">
      <Icon name="refresh" /> Refrescar
    </button>
  </div>

  <div class="pos-grid">
    <section class="pos-list">
      <div class="card toolbar">
        <div class="search">
          <Icon name="search" :size="16" />
          <input v-model="search" placeholder="Buscar producto disponible…" />
        </div>
        <span class="muted" style="font-size: 13px;">{{ available.length }} disponibles</span>
      </div>

      <div v-if="!available.length" class="card empty">
        <Icon name="package" :size="36" />
        <h4>Sin productos disponibles</h4>
        <p>Crea productos con stock para vender.</p>
      </div>

      <div v-else class="prod-grid">
        <button v-for="p in available" :key="p.id" class="psell" @click="addToCart(p)">
          <div class="psell-img" :style="p.image_url ? `background-image: url(${p.image_url})` : ''">
            <span v-if="!p.image_url">{{ p.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="psell-body">
            <div class="psell-name">{{ p.name }}</div>
            <div class="muted" style="font-size: 11px;">{{ p.category || '—' }}</div>
            <div class="psell-foot">
              <strong>{{ money(p.price) }}</strong>
              <span class="badge ok">{{ p.stock }} ud.</span>
            </div>
          </div>
        </button>
      </div>
    </section>

    <aside class="card pos-cart">
      <div class="cart-head">
        <div>
          <h3 style="display: flex; gap: 8px; align-items: center;">
            <Icon name="cart" /> Carrito
          </h3>
          <p class="muted" style="font-size: 12px; margin: 2px 0 0;">
            {{ cartUnits }} ud. · {{ cart.length }} línea{{ cart.length === 1 ? '' : 's' }}
          </p>
        </div>
        <button v-if="cart.length" class="icon" @click="clearCart" title="Vaciar">
          <Icon name="trash" />
        </button>
      </div>

      <div v-if="!cart.length" class="empty small" style="padding: 32px 12px;">
        <Icon name="bag" :size="32" />
        <p>Toca un producto para añadirlo al carrito.</p>
      </div>

      <ul v-else class="cart-list">
        <li v-for="l in cart" :key="l.product_id" class="cart-line">
          <div class="cl-info">
            <div class="cl-name">{{ l.name }}</div>
            <div class="muted" style="font-size: 12px;">{{ money(l.unit_price) }} c/u</div>
          </div>
          <div class="qty-pill">
            <button class="icon" @click="dec(l)">−</button>
            <span>{{ l.qty }}</span>
            <button class="icon" @click="inc(l)">+</button>
          </div>
          <strong class="cl-total">{{ money(l.qty * l.unit_price) }}</strong>
        </li>
      </ul>

      <div class="cart-foot">
        <div class="line"><span class="muted">Unidades</span><strong>{{ cartUnits }}</strong></div>
        <div class="line big"><span>Total</span><strong>{{ money(cartTotal) }}</strong></div>
        <button @click="submit" :disabled="!cart.length || submitting" class="pay">
          <span v-if="submitting" class="spinner" style="margin-right: 8px;"></span>
          <Icon v-else name="zap" />
          {{ submitting ? 'Procesando…' : 'Confirmar venta' }}
        </button>
      </div>
    </aside>
  </div>

  <section class="card" style="margin-top: 20px;">
    <div class="row" style="justify-content: space-between; flex-wrap: wrap;">
      <div>
        <h3>Histórico de ventas</h3>
        <p class="muted" style="font-size: 12px; margin: 2px 0 0;">
          Todas las ventas de tu empresa. Mostrando {{ filteredHistory.length }} de {{ sales.items.length }}.
        </p>
      </div>
      <div class="row" style="gap: 8px;">
        <select v-model="filterProduct" style="width: 200px;">
          <option :value="null">Todos los productos</option>
          <option v-for="p in products.items" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input type="date" v-model="filterDate" style="width: 160px;" />
      </div>
    </div>

    <div v-if="!filteredHistory.length" class="empty">
      <Icon name="cart" :size="32" />
      <h4>Sin ventas</h4>
      <p>Registra una venta para verla aquí.</p>
    </div>

    <div v-else class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Fecha</th><th>Producto</th><th>Vendedor</th>
            <th>Cant.</th><th>P.U.</th><th style="text-align:right;">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in filteredHistory" :key="s.id">
            <td>{{ fmtDate(s.created_at) }}</td>
            <td>{{ s.product_name }}</td>
            <td class="muted">{{ s.seller_name || '—' }}</td>
            <td>{{ s.quantity }}</td>
            <td>{{ money(s.unit_price) }}</td>
            <td style="text-align: right; font-weight: 600;">{{ money(s.total_price) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="muted" style="text-align: right;">Total filtrado</td>
            <td style="text-align: right; font-weight: 600;">{{ money(totalFiltered) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
</template>

<style scoped>
.pos-grid {
  display: grid; gap: 16px;
  grid-template-columns: 1fr 360px;
  align-items: flex-start;
}
@media (max-width: 980px) { .pos-grid { grid-template-columns: 1fr; } }

.toolbar {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; margin-bottom: 12px;
}
.search {
  flex: 1;
  display: flex; align-items: center; gap: 8px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0 12px;
  background: var(--surface-2);
  color: var(--text-muted);
}
.search input { border: none; background: transparent; padding: 9px 0; flex: 1; }
.search input:focus { box-shadow: none; }

.prod-grid {
  display: grid; gap: 10px;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}
.psell {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 0; overflow: hidden;
  text-align: left; color: var(--text);
  cursor: pointer;
  display: flex; flex-direction: column;
  transition: transform .12s, box-shadow .12s, border-color .12s;
}
.psell:hover {
  transform: translateY(-2px);
  border-color: var(--primary);
  box-shadow: 0 8px 20px rgba(79, 70, 229, .12);
}
.psell:active { transform: translateY(0); }
.psell-img {
  height: 90px; background-size: cover; background-position: center;
  background-color: var(--surface-2);
  display: grid; place-items: center;
  font-size: 32px; font-weight: 700;
  background-image: linear-gradient(135deg, #eef2ff 0%, #fce7f3 100%);
  color: var(--primary);
}
.psell-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; }
.psell-name { font-weight: 600; font-size: 13px; line-height: 1.3; }
.psell-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
.psell-foot strong { font-size: 14px; }

.pos-cart {
  position: sticky; top: 80px;
  display: flex; flex-direction: column; gap: 12px;
  padding: 0;
  max-height: calc(100vh - 100px);
}
@media (max-width: 980px) {
  .pos-cart { position: static; max-height: none; }
}
.cart-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 18px 18px 8px;
}
.cart-head h3 { font-size: 16px; }

.cart-list {
  list-style: none; padding: 0 8px; margin: 0;
  display: flex; flex-direction: column; gap: 4px;
  overflow-y: auto; flex: 1;
}
.cart-line {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px; align-items: center;
  padding: 10px;
  border-radius: var(--radius-sm);
}
.cart-line:hover { background: var(--surface-2); }
.cl-name { font-weight: 500; font-size: 14px; }
.cl-total { color: var(--primary); font-size: 14px; }

.qty-pill {
  display: inline-flex; align-items: center;
  background: var(--surface-2);
  border-radius: 999px; padding: 2px;
  border: 1px solid var(--border);
}
.qty-pill .icon {
  width: 24px; height: 24px; padding: 0;
  display: grid; place-items: center;
  border-radius: 50%; font-weight: 600;
}
.qty-pill .icon:hover { background: var(--surface); color: var(--primary); }
.qty-pill span { padding: 0 10px; font-weight: 600; font-size: 13px; min-width: 28px; text-align: center; }

.cart-foot {
  border-top: 1px solid var(--border);
  padding: 14px 18px;
  background: var(--surface-2);
  display: flex; flex-direction: column; gap: 8px;
}
.cart-foot .line { display: flex; justify-content: space-between; font-size: 13px; }
.cart-foot .line.big { font-size: 16px; padding-top: 8px; border-top: 1px dashed var(--border); }
.cart-foot .line.big strong { color: var(--primary); }

.pay {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  font-weight: 600;
  margin-top: 4px;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .35);
}
.pay:hover:not(:disabled) {
  background: linear-gradient(135deg, #4f46e5, #4338ca);
}

tfoot td { background: var(--surface-2); }
</style>
