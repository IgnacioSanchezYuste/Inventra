import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Llama a `fn` periódicamente mientras la pestaña esté visible.
 * También dispara `fn` cuando la pestaña vuelve al foco.
 */
export function useAutoRefresh(fn: () => void | Promise<void>, intervalMs = 20000) {
  let handle: number | null = null

  const tick = () => { if (!document.hidden) fn() }

  onMounted(() => {
    handle = window.setInterval(tick, intervalMs)
    document.addEventListener('visibilitychange', tick)
  })

  onBeforeUnmount(() => {
    if (handle) { clearInterval(handle); handle = null }
    document.removeEventListener('visibilitychange', tick)
  })
}
