<script setup lang="ts">
defineProps<{ title: string }>()
const emit = defineEmits<{ (e: 'close'): void }>()
function backdrop(ev: MouseEvent) {
  if (ev.target === ev.currentTarget) emit('close')
}
</script>

<template>
  <div class="modal-backdrop" @click="backdrop">
    <div class="modal" role="dialog" aria-modal="true">
      <div class="modal-head">
        <h3>{{ title }}</h3>
        <button class="icon" @click="emit('close')" aria-label="Cerrar">✕</button>
      </div>
      <div class="modal-body"><slot /></div>
      <div class="modal-foot" v-if="$slots.actions"><slot name="actions" /></div>
    </div>
  </div>
</template>
