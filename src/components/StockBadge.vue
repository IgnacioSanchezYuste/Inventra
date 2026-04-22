<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ stock: number | string }>()
const n = computed(() => Number(props.stock) || 0)
const cls = computed(() => n.value === 0 ? 'bad' : n.value < 5 ? 'warn' : 'ok')
const label = computed(() => n.value === 0 ? 'Sin stock' : n.value < 5 ? `Bajo · ${n.value}` : `${n.value} ud.`)
</script>

<template>
  <span class="badge" :class="cls">{{ label }}</span>
</template>
