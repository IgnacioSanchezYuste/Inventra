<script setup lang="ts">
import { useToast } from '../utils/toast'
const { items } = useToast()
</script>

<template>
  <div class="toast-stack">
    <div v-for="t in items" :key="t.id" class="toast" :class="t.kind">{{ t.msg }}</div>
  </div>
</template>
