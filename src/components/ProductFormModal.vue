<script setup lang="ts">
import { reactive, watch } from 'vue'
import Modal from './Modal.vue'
import type { Product } from '../api/types'
import type { NewProduct } from '../api/products'

const props = defineProps<{ product?: Product | null; saving?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'submit', p: NewProduct): void }>()

const form = reactive<NewProduct>({
  name: '', description: '', image_url: '',
  price: 0, cost: 0, stock: 0, category: ''
})

watch(() => props.product, (p) => {
  if (p) {
    form.name = p.name
    form.description = p.description ?? ''
    form.image_url = p.image_url ?? ''
    form.price = Number(p.price)
    form.cost = Number(p.cost)
    form.stock = Number(p.stock)
    form.category = p.category ?? ''
  }
}, { immediate: true })

function submit() {
  emit('submit', {
    name: form.name.trim(),
    description: (form.description || '').trim() || null,
    image_url: (form.image_url || '').trim() || null,
    price: Number(form.price),
    cost: Number(form.cost),
    stock: Number(form.stock),
    category: (form.category || '').trim() || null
  })
}
</script>

<template>
  <Modal :title="product ? 'Editar producto' : 'Nuevo producto'" @close="emit('close')">
    <form @submit.prevent="submit" class="col">
      <div>
        <label>Nombre</label>
        <input v-model="form.name" required />
      </div>
      <div class="row">
        <div class="grow">
          <label>Categoría</label>
          <input v-model="form.category" placeholder="Opcional" />
        </div>
        <div class="grow">
          <label>Imagen URL</label>
          <input v-model="form.image_url" placeholder="https://…" />
        </div>
      </div>
      <div>
        <label>Descripción</label>
        <textarea v-model="form.description" rows="2"></textarea>
      </div>
      <div class="row">
        <div class="grow">
          <label>Precio (€)</label>
          <input v-model.number="form.price" type="number" min="0" step="0.01" required />
        </div>
        <div class="grow">
          <label>Coste (€)</label>
          <input v-model.number="form.cost" type="number" min="0" step="0.01" required />
        </div>
        <div class="grow">
          <label>Stock</label>
          <input v-model.number="form.stock" type="number" min="0" step="1" required />
        </div>
      </div>
    </form>
    <template #actions>
      <button class="ghost" @click="emit('close')">Cancelar</button>
      <button :disabled="saving" @click="submit">
        <span v-if="saving" class="spinner" style="margin-right: 8px;"></span>
        {{ saving ? 'Guardando…' : 'Guardar' }}
      </button>
    </template>
  </Modal>
</template>
