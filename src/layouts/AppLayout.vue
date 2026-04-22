<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'
import ToastStack from '../components/ToastStack.vue'
import Icon from '../components/Icon.vue'

const auth = useAuthStore()
const router = useRouter()
const open = ref(false)

const items = computed(() => {
  const base = [
    { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
    { to: '/products', label: 'Productos', icon: 'package' },
    { to: '/sales', label: 'Ventas', icon: 'cart' }
  ]
  if (auth.canManage) base.push({ to: '/analytics', label: 'Analítica', icon: 'chart' })
  if (auth.isAdmin)   base.push({ to: '/company', label: 'Empresa', icon: 'user' })
  return base
})

const initials = computed(() => {
  const n = auth.user?.name || '?'
  return n.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
})

onMounted(() => {
  // Refresca user en cada montaje del layout para captar invitaciones recién aceptadas
  auth.refreshMe()
})

function logout() { auth.logout(); router.push('/login') }
</script>

<template>
  <div class="layout">
    <aside :class="{ open }">
      <div class="brand">
        <div class="logo">I</div>
        <div>
          <div class="name">Inventra</div>
          <div class="tag">{{ auth.user?.company_name || 'Sin empresa' }}</div>
        </div>
      </div>
      <nav>
        <RouterLink v-for="i in items" :key="i.to" :to="i.to" @click="open = false">
          <Icon :name="i.icon" :size="18" />
          <span>{{ i.label }}</span>
        </RouterLink>
      </nav>
      <div class="side-foot">
        <div class="role-pill">
          <span class="dot"></span> {{ auth.user?.role }}
        </div>
        <div class="muted" style="font-size: 11px; margin-top: 6px;">Inventra v1.0</div>
      </div>
    </aside>

    <div v-if="open" class="overlay" @click="open = false"></div>

    <div class="main">
      <header class="topbar">
        <button class="icon hamb" @click="open = !open" aria-label="Menú">
          <Icon name="menu" />
        </button>
        <div class="company-pill" v-if="auth.user?.company_name">
          <Icon name="package" :size="14" />
          {{ auth.user.company_name }}
        </div>
        <div class="grow"></div>
        <div class="user">
          <div class="who">
            <div class="n">{{ auth.user?.name }}</div>
            <div class="r">{{ auth.user?.role }}</div>
          </div>
          <div class="avatar">{{ initials }}</div>
          <button class="ghost logout-btn" @click="logout" title="Cerrar sesión">
            <Icon name="logout" />
            <span class="logout-label">Salir</span>
          </button>
        </div>
      </header>

      <main class="content">
        <RouterView v-slot="{ Component, route }">
          <transition name="fade">
            <component :is="Component" :key="route.fullPath" />
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
  z-index: 30;
}
.brand { display: flex; gap: 10px; align-items: center; padding: 0 6px 8px; }
.logo {
  width: 38px; height: 38px; border-radius: 11px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center;
  font-weight: 700; font-size: 17px;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .4);
}
.name { font-weight: 600; color: var(--text); font-size: 15px; }
.tag { font-size: 11px; color: var(--text-muted); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

nav { display: flex; flex-direction: column; gap: 2px; }
nav a {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: var(--radius-sm);
  color: var(--text-muted); font-weight: 500;
  transition: background .12s, color .12s;
}
nav a:hover { background: var(--surface-2); color: var(--text); }
nav a.router-link-active {
  background: var(--primary-soft); color: var(--primary);
}
nav a.router-link-active svg { color: var(--primary); }

.side-foot { margin-top: auto; padding: 8px; }
.role-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--success-soft); color: #047857;
  padding: 4px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 600; text-transform: capitalize;
}
.role-pill .dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--success);
  box-shadow: 0 0 0 0 rgba(16, 185, 129, .5);
  animation: ping 2s infinite;
}
@keyframes ping {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, .6); }
  70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

.topbar {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 24px;
  background: rgba(255, 255, 255, .85);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 20;
}
.hamb { display: none; }
.company-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--primary-soft); color: var(--primary);
  padding: 5px 11px; border-radius: 999px;
  font-size: 12px; font-weight: 600;
}

.user { display: flex; align-items: center; gap: 12px; }
.avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  display: grid; place-items: center; font-weight: 600; font-size: 13px;
}
.who { text-align: right; }
.who .n { font-weight: 500; font-size: 13px; line-height: 1.2; }
.who .r { font-size: 11px; color: var(--text-muted); text-transform: capitalize; }
.logout-btn { display: inline-flex; gap: 6px; align-items: center; padding: 8px 12px; }

.content { padding: 24px; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }

.fade-enter-active { transition: opacity .15s ease, transform .15s ease; }
.fade-leave-active { display: none; }
.fade-enter-from { opacity: 0; transform: translateY(4px); }

.overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, .35);
  z-index: 25;
  animation: fade .15s;
}
@keyframes fade { from { opacity: 0 } to { opacity: 1 } }

@media (max-width: 900px) {
  aside {
    position: fixed; left: 0; top: 0;
    transform: translateX(-100%); transition: transform .2s;
    z-index: 40; box-shadow: var(--shadow-lg);
  }
  aside.open { transform: none; }
  .hamb { display: inline-flex; }
  .who { display: none; }
  .logout-label { display: none; }
  .company-pill { display: none; }
  .content { padding: 16px; }
}
@media (max-width: 600px) {
  .topbar { padding: 10px 14px; gap: 8px; }
  .content { padding: 12px; }
  .avatar { width: 32px; height: 32px; }
}
</style>
