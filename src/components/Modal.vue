<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import Icon from './Icon.vue'

defineProps<{ title: string }>()
const emit = defineEmits<{ (e: 'close'): void }>()

function backdrop(ev: MouseEvent) {
  if (ev.target === ev.currentTarget) emit('close')
}
function onKey(e: KeyboardEvent) { if (e.key === 'Escape') emit('close') }

onMounted(() => {
  document.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="modal-backdrop" @click="backdrop">
    <div class="modal" role="dialog" aria-modal="true">
      <div class="modal-head">
        <h3>{{ title }}</h3>
        <button class="icon" @click="emit('close')" aria-label="Cerrar"><Icon name="close" /></button>
      </div>
      <div class="modal-body"><slot /></div>
      <div class="modal-foot" v-if="$slots.actions"><slot name="actions" /></div>
    </div>
  </div>
</template>
