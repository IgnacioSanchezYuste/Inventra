<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../store/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')

async function submit() {
  if (await auth.login(email.value.trim(), password.value)) {
    const r = (route.query.r as string) || '/dashboard'
    const safe = r.startsWith('/') && !r.startsWith('/login') && !r.startsWith('/register') ? r : '/dashboard'
    router.push(safe)
  }
}
</script>

<template>
  <div class="auth-shell">
    <div class="auth-card card">
      <div class="brand-mark">
        <div class="logo">I</div>
        <div>
          <div class="title">Inventra</div>
          <div class="muted">Inventario · Ventas · Analítica</div>
        </div>
      </div>

      <h2 style="margin-top: 24px;">Iniciar sesión</h2>
      <p class="muted" style="margin: 4px 0 20px;">Bienvenido. Accede para gestionar tu negocio.</p>

      <form @submit.prevent="submit" class="col">
        <div>
          <label>Email</label>
          <input v-model="email" type="email" required autocomplete="email" placeholder="tu@email.com" />
        </div>
        <div>
          <label>Contraseña</label>
          <input v-model="password" type="password" required autocomplete="current-password" />
        </div>

        <div v-if="auth.error" class="badge bad" style="align-self: flex-start;">{{ auth.error }}</div>

        <button :disabled="auth.loading" type="submit" style="margin-top: 4px;">
          <span v-if="auth.loading" class="spinner" style="margin-right: 8px;"></span>
          {{ auth.loading ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>

      <div class="muted" style="text-align: center; margin-top: 18px; font-size: 13px;">
        ¿Aún no tienes cuenta?
        <RouterLink to="/register">Crea una</RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: grid; place-items: center;
  padding: 24px;
  background:
    radial-gradient(800px 400px at 10% 0%, rgba(99, 102, 241, .14), transparent 60%),
    radial-gradient(700px 350px at 100% 100%, rgba(16, 185, 129, .10), transparent 60%),
    var(--bg);
}
.auth-card { width: 100%; max-width: 420px; padding: 32px; }
.brand-mark { display: flex; align-items: center; gap: 12px; }
.brand-mark .logo {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 18px;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .4);
}
.title { font-weight: 600; font-size: 18px; }
</style>
