<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useProductsStore } from '../store/products'
import { useAuthStore } from '../store/auth'
import { useToast } from '../utils/toast'
import { money, num } from '../utils/format'
import { apiError } from '../api/http'
import StockBadge from '../components/StockBadge.vue'
import ProductFormModal from '../components/ProductFormModal.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import Icon from '../components/Icon.vue'
import { useAutoRefresh } from '../utils/useAutoRefresh'
import type { Product } from '../api/types'
import type { NewProduct } from '../api/products'

const products = useProductsStore()
const auth = useAuthStore()
const toast = useToast()

type SortKey = 'recent' | 'price_desc' | 'price_asc' | 'stock_asc' | 'name'

const search = ref('')
const view = ref<'cards' | 'table'>('cards')
const sortKey = ref<SortKey>('recent')
const activeCat = ref<string>('all')

const showForm = ref(false)
const editing = ref<Product | null>(null)
const saving = ref(false)
const confirming = ref<Product | null>(null)
const removing = ref(false)

const categories = computed(() => {
  const set = new Set<string>()
  for (const p of products.items) if (p.category) set.add(p.category)
  return ['all', ...[...set].sort()]
})

const filtered = computed(() => {
  let arr = products.items.slice()
  if (activeCat.value !== 'all') arr = arr.filter(p => p.category === activeCat.value)
  const q = search.value.trim().toLowerCase()
  if (q) arr = arr.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.category || '').toLowerCase().includes(q) ||
    (p.description || '').toLowerCase().includes(q)
  )
  switch (sortKey.value) {
    case 'price_desc': arr.sort((a, b) => num(b.price) - num(a.price)); break
    case 'price_asc':  arr.sort((a, b) => num(a.price) - num(b.price)); break
    case 'stock_asc':  arr.sort((a, b) => Number(a.stock) - Number(b.stock)); break
    case 'name':       arr.sort((a, b) => a.name.localeCompare(b.name)); break
    default:           arr.sort((a, b) => b.id - a.id)
  }
  return arr
})

const stats = computed(() => {
  const total = products.items.length
  const stockTotal = products.items.reduce((a, p) => a + Number(p.stock), 0)
  const value = products.items.reduce((a, p) => a + num(p.price) * Number(p.stock), 0)
  const low = products.items.filter(p => Number(p.stock) < 5).length
  return { total, stockTotal, value, low }
})

onMounted(() => products.fetchAll(true))
useAutoRefresh(() => products.fetchAll(true), 15000)

function openNew() { editing.value = null; showForm.value = true }
function openEdit(p: Product) { editing.value = p; showForm.value = true }

async function save(payload: NewProduct) {
  saving.value = true
  try {
    if (editing.value) {
      await products.update(editing.value.id, payload)
      toast.success('Producto actualizado')
    } else {
      await products.create(payload)
      toast.success('Producto creado')
    }
    showForm.value = false
  } catch (e) { toast.error(apiError(e)) }
  finally { saving.value = false }
}

async function doRemove() {
  if (!confirming.value) return
  removing.value = true
  try {
    await products.remove(confirming.value.id)
    toast.success('Producto eliminado')
    confirming.value = null
  } catch (e) { toast.error(apiError(e)) }
  finally { removing.value = false }
}
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Productos</h1>
      <div class="sub">{{ filtered.length }} de {{ products.items.length }} en catálogo</div>
    </div>
    <div class="row">
      <button class="ghost" @click="products.fetchAll(true)" title="Refrescar">
        <Icon name="refresh" />
      </button>
      <button v-if="auth.canManage" @click="openNew">
        <Icon name="plus" /> Nuevo producto
      </button>
      <span v-else class="badge warn" title="Rol actual sin permiso de creación">
        Rol {{ auth.role }} · sin permiso para crear
      </span>
    </div>
  </div>

  <div class="grid-stats">
    <div class="mini-stat">
      <div class="ms-ico g-indigo"><Icon name="package" /></div>
      <div>
        <div class="ms-v">{{ stats.total }}</div>
        <div class="ms-l">Total productos</div>
      </div>
    </div>
    <div class="mini-stat">
      <div class="ms-ico g-green"><Icon name="bag" /></div>
      <div>
        <div class="ms-v">{{ stats.stockTotal }}</div>
        <div class="ms-l">Unidades en stock</div>
      </div>
    </div>
    <div class="mini-stat">
      <div class="ms-ico g-amber"><Icon name="euro" /></div>
      <div>
        <div class="ms-v">{{ money(stats.value) }}</div>
        <div class="ms-l">Valor de inventario</div>
      </div>
    </div>
    <div class="mini-stat">
      <div class="ms-ico g-pink"><Icon name="alert" /></div>
      <div>
        <div class="ms-v">{{ stats.low }}</div>
        <div class="ms-l">Con stock bajo</div>
      </div>
    </div>
  </div>

  <div class="toolbar card">
    <div class="search">
      <Icon name="search" :size="16" />
      <input v-model="search" placeholder="Buscar producto, descripción o categoría…" />
    </div>
    <select v-model="sortKey" class="sort">
      <option value="recent">Más recientes</option>
      <option value="price_desc">Precio (mayor)</option>
      <option value="price_asc">Precio (menor)</option>
      <option value="stock_asc">Stock (bajo primero)</option>
      <option value="name">Nombre A–Z</option>
    </select>
    <div class="seg">
      <button class="icon" :class="{ active: view === 'cards' }" @click="view = 'cards'" title="Tarjetas">
        <Icon name="grid" />
      </button>
      <button class="icon" :class="{ active: view === 'table' }" @click="view = 'table'" title="Tabla">
        <Icon name="list" />
      </button>
    </div>
  </div>

  <div v-if="categories.length > 1" class="pills">
    <button
      v-for="c in categories" :key="c"
      class="pill" :class="{ active: activeCat === c }"
      @click="activeCat = c"
    >
      {{ c === 'all' ? 'Todos' : c }}
    </button>
  </div>

  <div v-if="products.loading && !products.items.length" class="card empty">
    <div class="spinner" style="margin: 0 auto 8px;"></div>
    Cargando productos…
  </div>

  <div v-else-if="!filtered.length" class="card empty">
    <Icon name="package" :size="36" />
    <h4>{{ products.items.length ? 'Sin resultados' : 'Sin productos' }}</h4>
    <p v-if="products.items.length">Prueba con otro filtro.</p>
    <p v-else-if="auth.canManage">Crea tu primer producto para empezar.</p>
    <p v-else>Tu rol ({{ auth.role }}) no permite crear productos. Pide a un manager o admin que añada productos.</p>
    <button v-if="auth.canManage && !products.items.length" @click="openNew" class="subtle" style="margin-top: 12px;">
      <Icon name="plus" /> Crear producto
    </button>
  </div>

  <div v-else-if="view === 'cards'" class="grid-cards">
    <article v-for="p in filtered" :key="p.id" class="prod-card">
      <div class="img" :style="p.image_url ? `background-image: url(${p.image_url})` : ''">
        <span v-if="!p.image_url" class="ph">{{ p.name.charAt(0).toUpperCase() }}</span>
        <StockBadge :stock="p.stock" class="stock-pin" />
      </div>
      <div class="body">
        <div class="cat" v-if="p.category || (auth.isAdmin && p.owner_name)">
          {{ p.category }}<span v-if="auth.isAdmin && p.owner_name"> · {{ p.owner_name }}</span>
        </div>
        <h3 class="name">{{ p.name }}</h3>
        <p class="desc" v-if="p.description">{{ p.description }}</p>
        <div class="foot">
          <span class="price">{{ money(p.price) }}</span>
          <div class="actions" v-if="auth.canManage">
            <button class="icon" @click="openEdit(p)" title="Editar"><Icon name="edit" /></button>
            <button v-if="auth.isAdmin" class="icon" @click="confirming = p" title="Eliminar">
              <Icon name="trash" />
            </button>
          </div>
        </div>
      </div>
    </article>
  </div>

  <div v-else class="card table-wrap" style="padding: 0;">
    <table>
      <thead>
        <tr>
          <th>Producto</th>
          <th v-if="auth.isAdmin">Dueño</th>
          <th>Categoría</th><th>Precio</th><th>Coste</th><th>Stock</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in filtered" :key="p.id">
          <td>
            <div style="display:flex; gap:10px; align-items:center;">
              <div class="thumb" :style="p.image_url ? `background-image: url(${p.image_url})` : ''">
                <span v-if="!p.image_url">{{ p.name.charAt(0).toUpperCase() }}</span>
              </div>
              <div>
                <div style="font-weight: 500;">{{ p.name }}</div>
                <div class="muted" style="font-size: 12px;">{{ p.description || '—' }}</div>
              </div>
            </div>
          </td>
          <td v-if="auth.isAdmin" class="muted">{{ p.owner_name || '—' }}</td>
          <td>{{ p.category || '—' }}</td>
          <td>{{ money(p.price) }}</td>
          <td class="muted">{{ money(p.cost) }}</td>
          <td><StockBadge :stock="p.stock" /></td>
          <td style="text-align: right; white-space: nowrap;">
            <button v-if="auth.canManage" class="icon" @click="openEdit(p)" title="Editar"><Icon name="edit" /></button>
            <button v-if="auth.isAdmin" class="icon" @click="confirming = p" title="Eliminar"><Icon name="trash" /></button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <ProductFormModal
    v-if="showForm"
    :product="editing"
    :saving="saving"
    @close="showForm = false"
    @submit="save"
  />

  <ConfirmModal
    v-if="confirming"
    title="Eliminar producto"
    :message="`¿Eliminar “${confirming.name}”? Esta acción no se puede deshacer.`"
    :loading="removing"
    danger
    @close="confirming = null"
    @confirm="doRemove"
  />
</template>

<style scoped>
.grid-stats {
  display: grid; gap: 12px; margin-bottom: 16px;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}
.mini-stat {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
  box-shadow: var(--shadow-sm);
}
.ms-ico {
  width: 38px; height: 38px; border-radius: 10px;
  display: grid; place-items: center; color: #fff; flex-shrink: 0;
}
.ms-ico.g-indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.ms-ico.g-green { background: linear-gradient(135deg, #10b981, #059669); }
.ms-ico.g-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.ms-ico.g-pink { background: linear-gradient(135deg, #ec4899, #db2777); }
.ms-v { font-size: 18px; font-weight: 700; color: var(--text); }
.ms-l { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; }

.toolbar {
  display: flex; gap: 12px; align-items: center;
  padding: 10px 12px; margin-bottom: 12px;
  flex-wrap: wrap;
}
.toolbar .sort, .toolbar .seg { flex-shrink: 0; }
@media (max-width: 600px) {
  .toolbar .sort { width: 100%; }
  .toolbar .seg { width: 100%; }
  .toolbar .seg .icon { flex: 1; }
}
.search {
  flex: 1; min-width: 220px;
  display: flex; align-items: center; gap: 8px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0 12px;
  background: var(--surface-2);
  color: var(--text-muted);
}
.search input {
  border: none; background: transparent; padding: 9px 0;
  flex: 1;
}
.search input:focus { box-shadow: none; }
.sort { width: 200px; }

.seg { display: flex; gap: 0; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg .icon { border-radius: 0; padding: 7px 10px; }
.seg .active { background: var(--primary-soft); color: var(--primary); }

.pills {
  display: flex; gap: 6px; flex-wrap: wrap;
  margin-bottom: 16px;
}
.pill {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text-muted);
  padding: 6px 12px; font-size: 13px;
  border-radius: 999px;
  text-transform: capitalize;
  font-weight: 500;
}
.pill:hover:not(.active) { background: var(--surface-2); color: var(--text); }
.pill.active {
  background: var(--primary-soft); color: var(--primary);
  border-color: transparent;
}

.prod-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-sm);
  transition: transform .15s, box-shadow .15s, border-color .15s;
}
.prod-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
  border-color: #d4d6df;
}

.img {
  position: relative;
  height: 150px;
  background-color: var(--surface-2);
  background-size: cover; background-position: center;
  display: grid; place-items: center;
  border-bottom: 1px solid var(--border);
}
.img .ph {
  font-size: 46px; font-weight: 700;
  background: linear-gradient(135deg, #6366f1, #ec4899);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  opacity: .7;
}
.stock-pin {
  position: absolute; top: 10px; right: 10px;
  backdrop-filter: blur(8px);
  background: rgba(255, 255, 255, .85) !important;
}

.body { padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
.cat { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 500; }
.name { font-size: 15px; font-weight: 600; margin: 0; }
.desc { font-size: 13px; color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.foot { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 8px; }
.price { font-size: 17px; font-weight: 600; color: var(--text); }
.actions { display: flex; gap: 2px; }

.thumb {
  width: 36px; height: 36px; border-radius: 8px;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-weight: 600;
  background-size: cover; background-position: center;
  flex-shrink: 0;
}
</style>
