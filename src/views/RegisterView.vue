<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'
import type { Role } from '../api/types'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const role = ref<Role>('user')

async function submit() {
  if (await auth.register({ name: name.value.trim(), email: email.value.trim(), password: password.value, role: role.value })) {
    router.push('/dashboard')
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
          <div class="muted">Crea tu cuenta</div>
        </div>
      </div>

      <h2 style="margin-top: 24px;">Registro</h2>
      <p class="muted" style="margin: 4px 0 20px;">Empieza en menos de un minuto.</p>

      <form @submit.prevent="submit" class="col">
        <div>
          <label>Nombre</label>
          <input v-model="name" required autocomplete="name" />
        </div>
        <div>
          <label>Email</label>
          <input v-model="email" type="email" required autocomplete="email" />
        </div>
        <div>
          <label>Contraseña</label>
          <input v-model="password" type="password" minlength="6" required autocomplete="new-password" />
        </div>
        <div>
          <label>Rol</label>
          <select v-model="role">
            <option value="user">Usuario</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div v-if="auth.error" class="badge bad" style="align-self: flex-start;">{{ auth.error }}</div>

        <button :disabled="auth.loading" type="submit" style="margin-top: 4px;">
          <span v-if="auth.loading" class="spinner" style="margin-right: 8px;"></span>
          {{ auth.loading ? 'Creando…' : 'Crear cuenta' }}
        </button>
      </form>

      <div class="muted" style="text-align: center; margin-top: 18px; font-size: 13px;">
        ¿Ya tienes cuenta? <RouterLink to="/login">Inicia sesión</RouterLink>
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
.auth-card { width: 100%; max-width: 460px; padding: 32px; }
.brand-mark { display: flex; align-items: center; gap: 12px; }
.brand-mark .logo {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 18px;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .4);
}
.title { font-weight: 600; font-size: 18px; }
</style>
