<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import Modal from './Modal.vue'
import Icon from './Icon.vue'
import type { Product } from '../api/types'
import type { NewProduct } from '../api/products'
import { uploadsApi } from '../api/uploads'
import { useToast } from '../utils/toast'
import { apiError } from '../api/http'

const props = defineProps<{ product?: Product | null; saving?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'submit', p: NewProduct): void }>()

const toast = useToast()

const form = reactive<NewProduct>({
  name: '', description: '', image_url: '',
  price: 0, cost: 0, stock: 0, category: ''
})

const fileInput = ref<HTMLInputElement | null>(null)
const cameraInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const progress = ref(0)
const localPreview = ref<string | null>(null)

watch(() => props.product, (p) => {
  if (p) {
    form.name = p.name
    form.description = p.description ?? ''
    form.image_url = p.image_url ?? ''
    form.price = Number(p.price)
    form.cost = Number(p.cost)
    form.stock = Number(p.stock)
    form.category = p.category ?? ''
  } else {
    form.name = ''; form.description = ''; form.image_url = ''
    form.price = 0; form.cost = 0; form.stock = 0; form.category = ''
  }
  localPreview.value = null
}, { immediate: true })

async function handleFile(input: HTMLInputElement | null) {
  const file = input?.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    toast.error('Imagen demasiado grande (máx 5 MB)')
    if (input) input.value = ''
    return
  }
  // Preview inmediato
  const reader = new FileReader()
  reader.onload = e => { localPreview.value = e.target?.result as string }
  reader.readAsDataURL(file)

  uploading.value = true; progress.value = 0
  try {
    const r = await uploadsApi.productImage(file, p => progress.value = p)
    form.image_url = r.url
    toast.success('Imagen subida')
  } catch (e) {
    toast.error(apiError(e))
    localPreview.value = null
  } finally {
    uploading.value = false
    if (input) input.value = ''
  }
}

function clearImage() {
  form.image_url = ''
  localPreview.value = null
  if (fileInput.value) fileInput.value.value = ''
  if (cameraInput.value) cameraInput.value.value = ''
}

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

const previewSrc = () => localPreview.value || form.image_url || ''
</script>

<template>
  <Modal :title="product ? 'Editar producto' : 'Nuevo producto'" @close="emit('close')">
    <form @submit.prevent="submit" class="col">
      <div>
        <label>Nombre</label>
        <input v-model="form.name" required />
      </div>

      <div>
        <label>Imagen del producto</label>
        <div class="img-uploader">
          <div class="img-box">
            <img v-if="previewSrc()" :src="previewSrc()" alt="" />
            <div v-else class="img-placeholder">
              <Icon name="package" :size="32" />
              <span>Sin imagen</span>
            </div>
            <button v-if="previewSrc() && !uploading" type="button" class="img-remove" @click="clearImage" title="Quitar">
              <Icon name="close" :size="14" />
            </button>
            <div v-if="uploading" class="img-progress">
              <div class="img-progress-bar" :style="{ width: progress + '%' }"></div>
              <span>{{ progress }}%</span>
            </div>
          </div>
          <div class="img-actions">
            <input ref="fileInput" type="file" accept="image/*" hidden @change="handleFile(fileInput)" />
            <input ref="cameraInput" type="file" accept="image/*" capture="environment" hidden @change="handleFile(cameraInput)" />
            <button type="button" class="ghost" :disabled="uploading" @click="fileInput?.click()">
              <Icon name="package" /> Archivo
            </button>
            <button type="button" class="ghost cam" :disabled="uploading" @click="cameraInput?.click()">
              <Icon name="zap" /> Cámara
            </button>
          </div>
          <input
            v-model="form.image_url"
            placeholder="o pega una URL externa…"
            :disabled="uploading"
            style="margin-top: 6px;"
          />
        </div>
      </div>

      <div class="row two">
        <div class="grow">
          <label>Categoría</label>
          <input v-model="form.category" placeholder="Opcional" />
        </div>
      </div>

      <div>
        <label>Descripción</label>
        <textarea v-model="form.description" rows="2"></textarea>
      </div>
      <div class="row three">
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
      <button :disabled="saving || uploading" @click="submit">
        <span v-if="saving" class="spinner" style="margin-right: 8px;"></span>
        {{ saving ? 'Guardando…' : 'Guardar' }}
      </button>
    </template>
  </Modal>
</template>

<style scoped>
.img-uploader { display: flex; flex-direction: column; gap: 8px; }
.img-box {
  position: relative;
  width: 100%;
  height: 160px;
  border: 1px dashed var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface-2);
  overflow: hidden;
  display: grid; place-items: center;
}
.img-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-placeholder {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  color: var(--text-muted);
  font-size: 12px;
}
.img-remove {
  position: absolute; top: 8px; right: 8px;
  background: rgba(15, 23, 42, .65);
  color: #fff; border: none;
  width: 26px; height: 26px;
  border-radius: 50%;
  display: grid; place-items: center;
  cursor: pointer;
  padding: 0;
  transition: background .12s;
}
.img-remove:hover { background: rgba(15, 23, 42, .85); }
.img-progress {
  position: absolute; inset: 0;
  background: rgba(255,255,255,.85);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; font-size: 12px; font-weight: 600; color: var(--primary);
}
.img-progress-bar {
  height: 4px; background: var(--primary); border-radius: 2px;
  transition: width .15s;
  max-width: 60%; min-width: 20%;
}
.img-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.img-actions button { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.cam { /* cámara visible siempre, en móvil abrirá la app de cámara */ }
.row.two, .row.three { gap: 8px; flex-wrap: wrap; }
@media (max-width: 480px) {
  .row.three { flex-direction: column; }
  .row.three .grow { width: 100%; }
}
</style>
