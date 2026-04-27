<script setup lang="ts">
import { reactive, watch } from 'vue'
import Modal from './Modal.vue'
import type { Expense } from '../api/types'
import type { NewExpense } from '../api/expenses'

const props = defineProps<{
  expense?: Expense | null
  saving?: boolean
  suggestedCategories?: string[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: NewExpense): void
}>()

const today = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const form = reactive<NewExpense>({
  description: '',
  amount: 0,
  expense_date: today(),
  category: ''
})

watch(() => props.expense, (e) => {
  if (e) {
    form.description = e.description
    form.amount = Number(e.amount)
    form.expense_date = (e.expense_date || '').slice(0, 10) || today()
    form.category = e.category ?? ''
  } else {
    form.description = ''
    form.amount = 0
    form.expense_date = today()
    form.category = ''
  }
}, { immediate: true })

function submit() {
  emit('submit', {
    description: form.description.trim(),
    amount: Number(form.amount),
    expense_date: form.expense_date,
    category: (form.category || '').trim() || null
  })
}

function pickCategory(c: string) {
  form.category = c
}
</script>

<template>
  <Modal :title="expense ? 'Editar gasto' : 'Nuevo gasto'" @close="emit('close')">
    <form @submit.prevent="submit" class="col">
      <div>
        <label>Descripción</label>
        <input v-model="form.description" placeholder="Ej. Alquiler de oficina · marzo" required maxlength="255" />
      </div>

      <div class="row two">
        <div class="grow">
          <label>Importe (€)</label>
          <input v-model.number="form.amount" type="number" min="0" step="0.01" required />
        </div>
        <div class="grow">
          <label>Fecha</label>
          <input v-model="form.expense_date" type="date" required />
        </div>
      </div>

      <div>
        <label>Categoría</label>
        <input v-model="form.category" placeholder="Opcional · ej. Alquiler, Salarios, Suministros" maxlength="100" />
        <div v-if="suggestedCategories?.length" class="cat-chips">
          <button
            v-for="c in suggestedCategories.slice(0, 8)"
            :key="c"
            type="button"
            class="chip"
            :class="{ active: form.category === c }"
            @click="pickCategory(c)"
          >{{ c }}</button>
        </div>
      </div>
    </form>
    <template #actions>
      <button class="ghost" @click="emit('close')">Cancelar</button>
      <button :disabled="saving || !form.description.trim() || form.amount < 0" @click="submit">
        <span v-if="saving" class="spinner" style="margin-right: 8px;"></span>
        {{ saving ? 'Guardando…' : (expense ? 'Guardar cambios' : 'Añadir gasto') }}
      </button>
    </template>
  </Modal>
</template>

<style scoped>
.row.two { gap: 8px; flex-wrap: wrap; }
.cat-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.chip {
  background: var(--surface-2);
  color: var(--text-muted);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  cursor: pointer;
  transition: all .12s;
}
.chip:hover { color: var(--text); border-color: #d4d6df; }
.chip.active {
  background: var(--primary-soft);
  color: var(--primary);
  border-color: var(--primary);
  font-weight: 600;
}
</style>
