<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  data: number[]
  color?: string
  height?: number
  fill?: boolean
}>()

const W = 100
const H = 32

const path = computed(() => {
  const raw = (props.data || []).filter(v => Number.isFinite(v))
  const d = raw.length ? raw : [0, 0]
  if (d.length === 1) return `M0 ${H/2} L${W} ${H/2}`
  const max = Math.max(...d, 1)
  const min = Math.min(...d, 0)
  const range = max - min || 1
  const step = W / (d.length - 1)
  return d.map((v, i) => {
    const x = i * step
    const y = H - ((v - min) / range) * (H - 4) - 2
    return `${i === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`
  }).join(' ')
})

const area = computed(() => {
  if (!props.fill) return ''
  return `${path.value} L${W} ${H} L0 ${H} Z`
})
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" :height="height || 32" preserveAspectRatio="none" style="width: 100%; display: block;">
    <path v-if="fill" :d="area" :fill="color || '#4f46e5'" opacity="0.15" />
    <path :d="path" :stroke="color || '#4f46e5'" stroke-width="1.6" fill="none" stroke-linejoin="round" stroke-linecap="round" />
  </svg>
</template>
