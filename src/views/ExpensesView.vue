<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExpensesStore } from '../store/expenses'
import { useAuthStore } from '../store/auth'
import { useToast } from '../utils/toast'
import { money, fmtDate, num } from '../utils/format'
import { apiError } from '../api/http'
import { useAutoRefresh } from '../utils/useAutoRefresh'
import Icon from '../components/Icon.vue'
import ExpenseFormModal from '../components/ExpenseFormModal.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import type { Expense } from '../api/types'
import type { NewExpense } from '../api/expenses'

const expenses = useExpensesStore()
const auth = useAuthStore()
const toast = useToast()

const search = ref('')
const filterCategory = ref<string>('all')
const filterRange = ref<'all' | 'this_month' | 'last_month' | 'this_year' | 'custom'>('this_month')
const customFrom = ref('')
const customTo = ref('')

const showForm = ref(false)
const editing = ref<Expense | null>(null)
const confirming = ref<Expense | null>(null)

function refreshAll() { expenses.fetchAll(true) }
onMounted(refreshAll)
useAutoRefresh(refreshAll, 20000)

const categories = computed(() => {
  const set = new Set<string>()
  for (const e of expenses.items) if (e.category && e.category.trim()) set.add(e.category.trim())
  return ['all', ...[...set].sort((a, b) => a.localeCompare(b))]
})

function dateRangeBounds(): { from: string; to: string } | null {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
  switch (filterRange.value) {
    case 'this_month': {
      const from = new Date(today.getFullYear(), today.getMonth(), 1)
      const to   = new Date(today.getFullYear(), today.getMonth() + 1, 0)
      return { from: fmt(from), to: fmt(to) }
    }
    case 'last_month': {
      const from = new Date(today.getFullYear(), today.getMonth() - 1, 1)
      const to   = new Date(today.getFullYear(), today.getMonth(), 0)
      return { from: fmt(from), to: fmt(to) }
    }
    case 'this_year': {
      const from = new Date(today.getFullYear(), 0, 1)
      const to   = new Date(today.getFullYear(), 11, 31)
      return { from: fmt(from), to: fmt(to) }
    }
    case 'custom': {
      if (!customFrom.value && !customTo.value) return null
      return { from: customFrom.value || '0000-01-01', to: customTo.value || '9999-12-31' }
    }
    default: return null
  }
}

const filtered = computed(() => {
  let arr = expenses.items.slice()
  const q = search.value.trim().toLowerCase()
  if (q) arr = arr.filter(e =>
    e.description.toLowerCase().includes(q) ||
    (e.category || '').toLowerCase().includes(q) ||
    (e.user_name || '').toLowerCase().includes(q)
  )
  if (filterCategory.value !== 'all') {
    arr = arr.filter(e => (e.category || '') === filterCategory.value)
  }
  const range = dateRangeBounds()
  if (range) arr = arr.filter(e => {
    const d = (e.expense_date || '').slice(0, 10)
    return d >= range.from && d <= range.to
  })
  return arr
})

const filteredTotal = computed(() => filtered.value.reduce((a, e) => a + num(e.amount), 0))

const monthRange = (() => {
  const today = new Date()
  const from = new Date(today.getFullYear(), today.getMonth(), 1)
  const to   = new Date(today.getFullYear(), today.getMonth() + 1, 0)
  const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
  return { from: fmt(from), to: fmt(to) }
})()

const totalThisMonth = computed(() =>
  expenses.items
    .filter(e => {
      const d = (e.expense_date || '').slice(0, 10)
      return d >= monthRange.from && d <= monthRange.to
    })
    .reduce((a, e) => a + num(e.amount), 0)
)

const topCategory = computed(() => {
  const list = expenses.byCategory
  return list[0] || null
})

const distinctCategoriesCount = computed(() => {
  const set = new Set<string>()
  for (const e of expenses.items) {
    set.add((e.category && e.category.trim()) || 'Sin categoría')
  }
  return set.size
})

const suggestedCategories = computed(() => {
  // Las categorías ya usadas en la empresa, ordenadas por frecuencia
  return expenses.byCategory
    .filter(c => c.category !== 'Sin categoría')
    .map(c => c.category)
})

function openNew() {
  editing.value = null
  showForm.value = true
}

function openEdit(e: Expense) {
  editing.value = e
  showForm.value = true
}

async function onSubmit(payload: NewExpense) {
  try {
    if (editing.value) {
      await expenses.update(editing.value.id, payload)
      toast.success('Gasto actualizado')
    } else {
      await expenses.create(payload)
      toast.success('Gasto añadido')
    }
    showForm.value = false
    editing.value = null
  } catch (e: any) {
    toast.error(apiError(e))
  }
}

async function confirmRemove() {
  if (!confirming.value) return
  try {
    await expenses.remove(confirming.value.id)
    toast.success('Gasto eliminado')
    confirming.value = null
  } catch (e: any) {
    toast.error(apiError(e))
  }
}

function rangeLabel() {
  switch (filterRange.value) {
    case 'this_month': return 'Este mes'
    case 'last_month': return 'Mes anterior'
    case 'this_year':  return 'Este año'
    case 'custom':     return 'Personalizado'
    default:           return 'Todo el histórico'
  }
}
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Gastos</h1>
      <div class="sub">Registra y controla los gastos de tu empresa.</div>
    </div>
    <div class="row" style="gap: 8px;">
      <button class="ghost" @click="refreshAll">
        <Icon name="refresh" /> Refrescar
      </button>
      <button @click="openNew">
        <Icon name="plus" /> Nuevo gasto
      </button>
    </div>
  </div>

  <div class="grid-stats">
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="wallet" :size="18" style="color: #b91c1c" /><span class="label">Total acumulado</span></div>
      <div class="value">{{ money(expenses.total) }}</div>
      <div class="delta"><span class="badge bad">{{ expenses.items.length }} gasto(s)</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="calendar" :size="18" style="color: #d97706" /><span class="label">Este mes</span></div>
      <div class="value">{{ money(totalThisMonth) }}</div>
      <div class="delta"><span class="badge warn">{{ rangeLabel() }}</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="receipt" :size="18" style="color: #4f46e5" /><span class="label">Categorías</span></div>
      <div class="value">{{ distinctCategoriesCount }}</div>
      <div class="delta"><span class="badge brand">Diversificación</span></div>
    </div>
    <div class="card kpi">
      <div class="row" style="gap:10px;"><Icon name="trend_up" :size="18" style="color: #db2777" /><span class="label">Categoría top</span></div>
      <div class="value top-cat">{{ topCategory?.category || '—' }}</div>
      <div class="delta"><span class="badge warn" v-if="topCategory">{{ money(topCategory.total) }}</span><span v-else class="badge">Sin gastos</span></div>
    </div>
  </div>

  <section class="card filter-bar">
    <div class="search">
      <Icon name="search" :size="16" />
      <input v-model="search" placeholder="Buscar descripción, categoría, usuario…" />
    </div>

    <select v-model="filterCategory">
      <option value="all">Todas las categorías</option>
      <option v-for="c in categories.filter(c => c !== 'all')" :key="c" :value="c">{{ c }}</option>
    </select>

    <select v-model="filterRange">
      <option value="this_month">Este mes</option>
      <option value="last_month">Mes anterior</option>
      <option value="this_year">Este año</option>
      <option value="all">Todo el histórico</option>
      <option value="custom">Personalizado…</option>
    </select>

    <template v-if="filterRange === 'custom'">
      <input type="date" v-model="customFrom" />
      <input type="date" v-model="customTo" />
    </template>
  </section>

  <section class="card" style="margin-top: 12px;">
    <div class="row" style="justify-content: space-between; flex-wrap: wrap; gap: 8px;">
      <div>
        <h3 style="margin: 0;">Histórico</h3>
        <p class="muted" style="font-size: 12px; margin: 2px 0 0;">
          Mostrando {{ filtered.length }} de {{ expenses.items.length }} · Total filtrado <strong>{{ money(filteredTotal) }}</strong>
        </p>
      </div>
    </div>

    <div v-if="expenses.loading && !expenses.items.length" class="empty">
      <span class="spinner"></span>
      <p>Cargando gastos…</p>
    </div>

    <div v-else-if="!expenses.items.length" class="empty">
      <Icon name="wallet" :size="32" />
      <h4>Aún no hay gastos</h4>
      <p>Empieza añadiendo el primer gasto para llevar el control.</p>
      <button class="subtle" @click="openNew" style="margin-top: 12px;">
        <Icon name="plus" /> Añadir el primer gasto
      </button>
    </div>

    <div v-else-if="!filtered.length" class="empty">
      <Icon name="search" :size="32" />
      <p>No hay gastos que coincidan con el filtro.</p>
    </div>

    <div v-else class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Registrado por</th>
            <th style="text-align: right;">Importe</th>
            <th style="width: 100px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in filtered" :key="e.id">
            <td class="nowrap">{{ fmtDate(e.expense_date) }}</td>
            <td>
              <div class="cell-desc">{{ e.description }}</div>
            </td>
            <td>
              <span v-if="e.category" class="cat-tag">{{ e.category }}</span>
              <span v-else class="muted">—</span>
            </td>
            <td class="muted">{{ e.user_name || '—' }}</td>
            <td class="amount">{{ money(e.amount) }}</td>
            <td style="text-align: right;">
              <button class="icon" @click="openEdit(e)" title="Editar">
                <Icon name="edit" :size="16" />
              </button>
              <button v-if="auth.isAdmin" class="icon danger-btn" @click="confirming = e" title="Eliminar">
                <Icon name="trash" :size="16" />
              </button>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="muted" style="text-align: right;">Total filtrado</td>
            <td class="amount" style="font-weight: 700;">{{ money(filteredTotal) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>

  <ExpenseFormModal
    v-if="showForm"
    :expense="editing"
    :saving="expenses.saving"
    :suggestedCategories="suggestedCategories"
    @close="showForm = false; editing = null"
    @submit="onSubmit"
  />

  <ConfirmModal
    v-if="confirming"
    title="Eliminar gasto"
    :message="`¿Seguro que quieres eliminar “${confirming.description}” por ${money(confirming.amount)}? Esta acción no se puede deshacer.`"
    danger
    :loading="expenses.saving"
    @close="confirming = null"
    @confirm="confirmRemove"
  />
</template>

<style scoped>
.grid-stats {
  display: grid; gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  margin-bottom: 16px;
}
.kpi .label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); font-weight: 500; }
.kpi .value { font-size: 24px; font-weight: 700; letter-spacing: -.02em; }
.kpi .top-cat {
  font-size: 18px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.filter-bar {
  display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
  padding: 12px;
}
.filter-bar .search {
  flex: 1; min-width: 220px;
  display: flex; align-items: center; gap: 8px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 0 12px;
  background: var(--surface-2);
  color: var(--text-muted);
}
.filter-bar .search input {
  border: none; background: transparent; padding: 9px 0; flex: 1;
}
.filter-bar .search input:focus { box-shadow: none; }
.filter-bar select, .filter-bar input[type="date"] {
  min-width: 160px;
}

.empty {
  text-align: center; padding: 36px 16px;
  color: var(--text-muted);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.empty h4 { color: var(--text); margin: 4px 0 0; font-size: 14px; font-weight: 600; }
.empty p { font-size: 13px; margin: 0; }

.cell-desc {
  font-weight: 500;
  max-width: 360px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cat-tag {
  display: inline-block;
  background: var(--primary-soft);
  color: var(--primary);
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
.amount { text-align: right; font-weight: 600; color: #b91c1c; white-space: nowrap; }
.nowrap { white-space: nowrap; }

.danger-btn:hover { color: var(--danger, #dc2626); }

tfoot td { background: var(--surface-2); }

@media (max-width: 600px) {
  .filter-bar select, .filter-bar input[type="date"] { flex: 1; min-width: 140px; }
  .cell-desc { max-width: 200px; }
}
</style>
