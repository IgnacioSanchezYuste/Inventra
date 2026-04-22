<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useProductsStore } from '../store/products'
import { useAuthStore } from '../store/auth'
import { useToast } from '../utils/toast'
import { money } from '../utils/format'
import { apiError } from '../api/http'
import StockBadge from '../components/StockBadge.vue'
import ProductFormModal from '../components/ProductFormModal.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import type { Product } from '../api/types'
import type { NewProduct } from '../api/products'

const products = useProductsStore()
const auth = useAuthStore()
const toast = useToast()

const search = ref('')
const view = ref<'cards' | 'table'>('cards')
const showForm = ref(false)
const editing = ref<Product | null>(null)
const saving = ref(false)
const confirming = ref<Product | null>(null)
const removing = ref(false)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return products.items
  return products.items.filter(p =>
    p.name.toLowerCase().includes(q) ||
    (p.category || '').toLowerCase().includes(q)
  )
})

onMounted(() => products.fetchAll())

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
      <div class="sub">{{ filtered.length }} de {{ products.items.length }} resultados</div>
    </div>
    <div class="row">
      <input v-model="search" placeholder="Buscar nombre o categoría…" style="width: 260px;" />
      <div class="seg">
        <button class="icon" :class="{ active: view === 'cards' }" @click="view = 'cards'" title="Tarjetas">▦</button>
        <button class="icon" :class="{ active: view === 'table' }" @click="view = 'table'" title="Tabla">≡</button>
      </div>
      <button v-if="auth.canManage" @click="openNew">+ Nuevo</button>
    </div>
  </div>

  <div v-if="products.loading && !products.items.length" class="card empty">
    <div class="spinner" style="margin: 0 auto 8px;"></div>
    Cargando productos…
  </div>

  <div v-else-if="!filtered.length" class="card empty">
    <h4>Sin productos</h4>
    <p>Crea tu primer producto para empezar a vender.</p>
    <button v-if="auth.canManage" @click="openNew" class="subtle" style="margin-top: 12px;">+ Crear producto</button>
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
            <button class="icon" @click="openEdit(p)" title="Editar">✎</button>
            <button v-if="auth.isAdmin" class="icon" @click="confirming = p" title="Eliminar">🗑</button>
          </div>
        </div>
      </div>
    </article>
  </div>

  <div v-else class="card" style="padding: 0; overflow: hidden;">
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
          <td style="text-align: right;">
            <button v-if="auth.canManage" class="icon" @click="openEdit(p)" title="Editar">✎</button>
            <button v-if="auth.isAdmin" class="icon" @click="confirming = p" title="Eliminar">🗑</button>
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
    :message="`¿Eliminar &quot;${confirming.name}&quot;? Esta acción no se puede deshacer.`"
    :loading="removing"
    danger
    @close="confirming = null"
    @confirm="doRemove"
  />
</template>

<style scoped>
.seg { display: flex; gap: 0; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg .icon { border-radius: 0; padding: 7px 10px; }
.seg .active { background: var(--primary-soft); color: var(--primary); }

.prod-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-sm);
  transition: transform .15s, box-shadow .15s;
}
.prod-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }

.img {
  position: relative;
  height: 140px;
  background-color: var(--surface-2);
  background-size: cover; background-position: center;
  display: grid; place-items: center;
  border-bottom: 1px solid var(--border);
}
.img .ph {
  font-size: 42px; font-weight: 700; color: var(--primary);
  opacity: .5;
}
.stock-pin { position: absolute; top: 10px; right: 10px; }

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
