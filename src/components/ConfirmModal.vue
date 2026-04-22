<script setup lang="ts">
import Modal from './Modal.vue'
defineProps<{ title: string; message: string; danger?: boolean; loading?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'confirm'): void }>()
</script>

<template>
  <Modal :title="title" @close="emit('close')">
    <p style="margin: 0;">{{ message }}</p>
    <template #actions>
      <button class="ghost" @click="emit('close')">Cancelar</button>
      <button :class="danger ? 'danger' : ''" :disabled="loading" @click="emit('confirm')">
        {{ loading ? 'Procesando…' : 'Confirmar' }}
      </button>
    </template>
  </Modal>
</template>
