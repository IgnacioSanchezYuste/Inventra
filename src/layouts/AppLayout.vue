<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'
import ToastStack from '../components/ToastStack.vue'

const auth = useAuthStore()
const router = useRouter()
const open = ref(false)

const items = computed(() => [
  { to: '/dashboard', label: 'Dashboard', icon: '◐' },
  { to: '/products', label: 'Productos', icon: '▤' },
  { to: '/sales', label: 'Ventas', icon: '⇅' },
  ...(auth.canManage ? [{ to: '/analytics', label: 'Analítica', icon: '▰' }] : [])
])

const initials = computed(() => {
  const n = auth.user?.name || '?'
  return n.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
})

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="layout">
    <aside :class="{ open }">
      <div class="brand">
        <div class="logo">I</div>
        <div>
          <div class="name">Inventra</div>
          <div class="tag">Inventory · Sales</div>
        </div>
      </div>
      <nav>
        <RouterLink v-for="i in items" :key="i.to" :to="i.to" @click="open = false">
          <span class="ico">{{ i.icon }}</span> {{ i.label }}
        </RouterLink>
      </nav>
      <div class="side-foot muted">v1.0 · {{ auth.user?.role }}</div>
    </aside>

    <div class="main">
      <header class="topbar">
        <button class="icon hamb" @click="open = !open" aria-label="Menú">☰</button>
        <div class="grow"></div>
        <div class="user">
          <div class="avatar">{{ initials }}</div>
          <div class="who">
            <div class="n">{{ auth.user?.name }}</div>
            <div class="r">{{ auth.user?.role }}</div>
          </div>
          <button class="ghost" @click="logout">Salir</button>
        </div>
      </header>

      <main class="content">
        <RouterView v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </RouterView>
      </main>
    </div>

    <ToastStack />
  </div>
</template>

<style scoped>
.layout { display: flex; min-height: 100vh; }

aside {
  width: 240px; flex-shrink: 0;
  background: var(--surface);
  border-right: 1px solid var(--border);
  padding: 22px 14px 16px;
  display: flex; flex-direction: column; gap: 18px;
  position: sticky; top: 0; height: 100vh;
}
.brand { display: flex; gap: 10px; align-items: center; padding: 0 6px 8px; }
.logo {
  width: 36px; height: 36px; border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center;
  font-weight: 700; font-size: 16px;
  box-shadow: 0 4px 12px rgba(79, 70, 229, .35);
}
.name { font-weight: 600; color: var(--text); }
.tag { font-size: 11px; color: var(--text-muted); }

nav { display: flex; flex-direction: column; gap: 2px; }
nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: var(--radius-sm);
  color: var(--text); font-weight: 500;
  transition: background .12s, color .12s;
}
nav a:hover { background: var(--surface-2); color: var(--primary); }
nav a.router-link-active { background: var(--primary-soft); color: var(--primary); }
.ico { width: 18px; display: inline-block; text-align: center; opacity: .85; }
.side-foot { margin-top: auto; padding: 8px; font-size: 12px; }

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

.topbar {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 10;
}
.hamb { display: none; }

.user { display: flex; align-items: center; gap: 12px; }
.avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-weight: 600; font-size: 13px;
}
.who .n { font-weight: 500; font-size: 13px; }
.who .r { font-size: 11px; color: var(--text-muted); text-transform: capitalize; }

.content { padding: 24px; flex: 1; }

.fade-enter-active, .fade-leave-active { transition: opacity .15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 900px) {
  aside {
    position: fixed; left: 0; top: 0;
    transform: translateX(-100%); transition: transform .2s;
    z-index: 50; box-shadow: var(--shadow-lg);
  }
  aside.open { transform: none; }
  .hamb { display: inline-flex; }
  .who { display: none; }
}
</style>
