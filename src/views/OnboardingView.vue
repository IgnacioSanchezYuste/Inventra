<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/auth'
import { useToast } from '../utils/toast'
import Icon from '../components/Icon.vue'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const checking = ref(false)

async function recheck() {
  checking.value = true
  const u = await auth.refreshMe()
  checking.value = false
  if (u?.company_id) {
    toast.success(`¡Bienvenido a ${u.company_name}!`)
    router.push('/dashboard')
  } else {
    toast.error('Aún no tienes invitación activa.')
  }
}

function logout() { auth.logout(); router.push('/login') }
</script>

<template>
  <div class="onb">
    <div class="onb-card card">
      <div class="logo">I</div>
      <h1>Esperando invitación</h1>
      <p class="muted">
        Hola <strong>{{ auth.user?.name }}</strong>. Tu cuenta como
        <span class="badge brand">{{ auth.user?.role }}</span> está creada,
        pero todavía no perteneces a ninguna empresa.
      </p>

      <div class="steps">
        <div class="step">
          <span class="num">1</span>
          <div class="step-body">
            <strong class="step-title">Comparte tu email con un admin</strong>
            <span class="step-desc muted">{{ auth.user?.email }}</span>
          </div>
        </div>
        <div class="step">
          <span class="num">2</span>
          <div class="step-body">
            <strong class="step-title">El admin te invitará desde su panel</strong>
            <span class="step-desc muted">Le aparecerás como miembro al instante.</span>
          </div>
        </div>
        <div class="step">
          <span class="num">3</span>
          <div class="step-body">
            <strong class="step-title">Pulsa “Comprobar invitación”</strong>
            <span class="step-desc muted">o vuelve a iniciar sesión más tarde.</span>
          </div>
        </div>
      </div>

      <div class="actions">
        <button @click="recheck" :disabled="checking">
          <span v-if="checking" class="spinner" style="margin-right: 8px;"></span>
          <Icon v-else name="refresh" />
          <span>Comprobar invitación</span>
        </button>
        <button class="ghost" @click="logout">
          <Icon name="logout" />
          <span>Salir</span>
        </button>
      </div>

      <p class="muted footer-note">
        Si necesitas crear tu propia empresa, sal y regístrate eligiendo el rol <strong>Admin</strong>.
      </p>
    </div>
  </div>
</template>

<style scoped>
.onb {
  min-height: 100vh;
  display: grid; place-items: center;
  padding: 16px;
  background:
    radial-gradient(800px 400px at 50% 0%, rgba(99, 102, 241, .12), transparent 60%),
    var(--bg);
}
.onb-card {
  width: 100%; max-width: 520px;
  padding: 32px;
  text-align: left;
}
.logo {
  width: 56px; height: 56px; border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 22px;
  box-shadow: 0 8px 22px rgba(79, 70, 229, .4);
  margin-bottom: 16px;
}
h1 { font-size: 22px; margin-bottom: 6px; }

.steps { margin-top: 22px; display: flex; flex-direction: column; gap: 10px; }
.step {
  display: flex; gap: 12px; align-items: center;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface-2);
}
.num {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary); color: #fff;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 13px;
  flex-shrink: 0;
  line-height: 1;
}
.step-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.step-title { font-size: 13px; font-weight: 600; color: var(--text); line-height: 1.3; }
.step-desc { font-size: 12px; word-break: break-word; line-height: 1.3; }

.actions {
  display: flex; gap: 8px; flex-wrap: wrap;
  justify-content: center; margin-top: 18px;
}
.actions button {
  display: inline-flex; gap: 6px; align-items: center; justify-content: center;
  flex: 1 1 180px; min-height: 40px;
}

.footer-note {
  font-size: 12px; margin-top: 22px; text-align: center;
}

@media (max-width: 480px) {
  .onb-card { padding: 22px; }
  h1 { font-size: 19px; }
}
</style>
