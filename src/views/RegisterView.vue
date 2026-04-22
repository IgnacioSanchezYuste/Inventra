<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'
import type { Role } from '../api/types'
import Icon from '../components/Icon.vue'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const role = ref<Role>('admin')
const companyName = ref('')

const roleInfo: Record<Role, { title: string; desc: string; icon: string }> = {
  admin:   { title: 'Soy admin', desc: 'Voy a crear una empresa nueva. Podré invitar a managers y users.', icon: 'package' },
  manager: { title: 'Soy manager', desc: 'Me unirá un admin. Podré crear/editar productos y registrar ventas.', icon: 'edit' },
  user:    { title: 'Soy user', desc: 'Me unirá un admin. Podré ver productos y registrar ventas.', icon: 'user' }
}

const needsCompany = computed(() => role.value === 'admin')

async function submit() {
  const payload: any = {
    name: name.value.trim(),
    email: email.value.trim(),
    password: password.value,
    role: role.value
  }
  if (needsCompany.value) payload.company_name = companyName.value.trim()

  if (await auth.register(payload)) {
    router.push(auth.hasCompany ? '/dashboard' : '/onboarding')
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
      <p class="muted" style="margin: 4px 0 16px;">Empieza en menos de un minuto.</p>

      <div class="role-choices">
        <button
          v-for="r in (['admin','manager','user'] as Role[])"
          :key="r"
          type="button"
          class="role-card"
          :class="{ active: role === r }"
          @click="role = r"
        >
          <Icon :name="roleInfo[r].icon" :size="18" />
          <div>
            <strong>{{ roleInfo[r].title }}</strong>
            <span>{{ roleInfo[r].desc }}</span>
          </div>
        </button>
      </div>

      <form @submit.prevent="submit" class="col" style="margin-top: 16px;">
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

        <transition name="fade">
          <div v-if="needsCompany">
            <label>Nombre de la empresa</label>
            <input v-model="companyName" required placeholder="Mi Empresa S.L." />
            <div class="muted" style="font-size: 12px; margin-top: 6px;">
              Se creará la empresa y serás su admin. Después podrás invitar a tu equipo.
            </div>
          </div>
        </transition>

        <div v-if="!needsCompany" class="info-banner">
          <Icon name="alert" :size="16" />
          <span>Tras registrarte verás una pantalla de espera hasta que un admin te invite por email.</span>
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
.auth-card { width: 100%; max-width: 520px; padding: 32px; }
.brand-mark { display: flex; align-items: center; gap: 12px; }
.brand-mark .logo {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 18px;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .4);
}
.title { font-weight: 600; font-size: 18px; }

.role-choices { display: flex; flex-direction: column; gap: 8px; }
.role-card {
  display: flex; align-items: flex-start; gap: 12px;
  width: 100%; padding: 12px 14px;
  background: var(--surface); color: var(--text);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  text-align: left;
  transition: border-color .12s, background .12s;
}
.role-card:hover { background: var(--surface-2); }
.role-card.active {
  background: var(--primary-soft);
  border-color: var(--primary);
}
.role-card svg { color: var(--primary); margin-top: 2px; flex-shrink: 0; }
.role-card strong { display: block; font-size: 14px; }
.role-card span { display: block; font-size: 12px; color: var(--text-muted); margin-top: 2px; }

.info-banner {
  display: flex; gap: 8px; align-items: center;
  background: var(--warning-soft); color: #92400e;
  padding: 10px 12px; border-radius: var(--radius-sm);
  font-size: 13px;
}

.fade-enter-active, .fade-leave-active { transition: opacity .15s, transform .15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
