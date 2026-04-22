import { reactive } from 'vue'

export type ToastKind = 'info' | 'success' | 'error'
interface Toast { id: number; msg: string; kind: ToastKind }

const state = reactive<{ items: Toast[] }>({ items: [] })
let seq = 1

export function useToast() {
  return {
    items: state.items,
    show(msg: string, kind: ToastKind = 'info', ms = 3500) {
      const t: Toast = { id: seq++, msg, kind }
      state.items.push(t)
      setTimeout(() => {
        const i = state.items.findIndex(x => x.id === t.id)
        if (i >= 0) state.items.splice(i, 1)
      }, ms)
    },
    success(m: string) { this.show(m, 'success') },
    error(m: string) { this.show(m, 'error', 5000) }
  }
}
