<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCompanyStore } from '../store/company'
import { useAuthStore } from '../store/auth'
import { useToast } from '../utils/toast'
import { fmtDate } from '../utils/format'
import { apiError } from '../api/http'
import Icon from '../components/Icon.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import { useAutoRefresh } from '../utils/useAutoRefresh'

const company = useCompanyStore()
const auth = useAuthStore()
const toast = useToast()

const inviteEmail = ref('')
const inviteRole = ref<'manager' | 'user'>('user')
const inviting = ref(false)

const removingId = ref<number | null>(null)
const revokingId = ref<number | null>(null)
const memberToRemove = ref<{ id: number; name: string } | null>(null)

onMounted(() => company.fetchAll())
useAutoRefresh(() => company.fetchAll(), 20000)

const pending = computed(() => company.invitations.filter(i => i.status === 'pending'))
const history = computed(() => company.invitations.filter(i => i.status !== 'pending'))

async function invite() {
  if (!inviteEmail.value.trim()) return
  inviting.value = true
  try {
    const r = await company.invite(inviteEmail.value.trim().toLowerCase(), inviteRole.value)
    toast.success(r.auto_assigned ? 'Usuario añadido directamente al equipo' : 'Invitación creada')
    inviteEmail.value = ''
  } catch (e) { toast.error(apiError(e)) }
  finally { inviting.value = false }
}

async function revoke(id: number) {
  revokingId.value = id
  try { await company.revoke(id); toast.success('Invitación revocada') }
  catch (e) { toast.error(apiError(e)) }
  finally { revokingId.value = null }
}

async function confirmRemove() {
  if (!memberToRemove.value) return
  const id = memberToRemove.value.id
  removingId.value = id
  try {
    await company.removeMember(id)
    toast.success('Miembro removido del equipo')
    memberToRemove.value = null
  } catch (e) { toast.error(apiError(e)) }
  finally { removingId.value = null }
}

function copyEmail(email: string) {
  navigator.clipboard?.writeText(email)
  toast.success('Email copiado')
}

const roleColor = (r: string) => r === 'admin' ? 'brand' : r === 'manager' ? 'ok' : ''
const statusColor = (s: string) => s === 'pending' ? 'warn' : s === 'accepted' ? 'ok' : 'bad'
</script>

<template>
  <div class="page-head">
    <div>
      <h1>Empresa</h1>
      <div class="sub">Gestiona miembros e invitaciones de tu equipo.</div>
    </div>
    <button class="ghost" @click="company.fetchAll()">
      <Icon name="refresh" /> Refrescar
    </button>
  </div>

  <section class="company-card card" v-if="company.company">
    <div class="cc-icon">{{ company.company.name.charAt(0).toUpperCase() }}</div>
    <div style="flex: 1;">
      <div class="muted" style="font-size: 12px; text-transform: uppercase; letter-spacing: .04em;">Empresa</div>
      <h2 style="margin: 4px 0;">{{ company.company.name }}</h2>
      <div class="muted" style="font-size: 13px;">
        Creada el {{ fmtDate(company.company.created_at) }} · Admin: <strong>{{ company.company.admin_name }}</strong>
      </div>
    </div>
    <div class="badge brand">{{ company.members.length }} miembros</div>
  </section>

  <div class="grid">
    <!-- Invitar -->
    <section class="card">
      <h3>Invitar nuevo miembro</h3>
      <p class="muted" style="font-size: 13px; margin: 4px 0 14px;">
        Envía una invitación por email. Si la persona ya tiene cuenta sin empresa se añade al instante.
      </p>
      <form @submit.prevent="invite" class="col">
        <div>
          <label>Email del invitado</label>
          <input v-model="inviteEmail" type="email" required placeholder="ejemplo@correo.com" />
        </div>
        <div>
          <label>Rol</label>
          <select v-model="inviteRole">
            <option value="user">Usuario · puede vender y ver productos</option>
            <option value="manager">Manager · además crea/edita productos y ve analítica</option>
          </select>
        </div>
        <button type="submit" :disabled="inviting">
          <span v-if="inviting" class="spinner" style="margin-right: 8px;"></span>
          <Icon v-else name="plus" />
          Enviar invitación
        </button>
      </form>

      <div v-if="pending.length" style="margin-top: 22px;">
        <h4 style="font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); margin-bottom: 10px;">
          Invitaciones pendientes ({{ pending.length }})
        </h4>
        <ul class="inv-list">
          <li v-for="i in pending" :key="i.id">
            <div class="grow">
              <div style="font-weight: 500;">{{ i.email }}</div>
              <div class="muted" style="font-size: 12px;">
                <span class="badge" :class="roleColor(i.role)">{{ i.role }}</span>
                · enviada {{ fmtDate(i.created_at) }}
              </div>
            </div>
            <button class="icon" @click="copyEmail(i.email)" title="Copiar email"><Icon name="check" /></button>
            <button class="icon" @click="revoke(i.id)" :disabled="revokingId === i.id" title="Revocar">
              <Icon name="trash" />
            </button>
          </li>
        </ul>
      </div>
    </section>

    <!-- Miembros -->
    <section class="card">
      <h3>Miembros del equipo</h3>
      <p class="muted" style="font-size: 13px; margin: 4px 0 14px;">
        {{ company.members.length }} persona{{ company.members.length === 1 ? '' : 's' }} con acceso.
      </p>

      <div v-if="!company.members.length" class="empty small">
        <p>Aún no hay miembros además de ti.</p>
      </div>

      <ul class="member-list">
        <li v-for="m in company.members" :key="m.id">
          <div class="avatar">{{ m.name.charAt(0).toUpperCase() }}</div>
          <div class="grow">
            <div style="font-weight: 500;">{{ m.name }}<span v-if="m.id === auth.user?.id" class="muted"> (tú)</span></div>
            <div class="muted" style="font-size: 12px;">{{ m.email }}</div>
          </div>
          <span class="badge" :class="roleColor(m.role)">{{ m.role }}</span>
          <button
            v-if="m.role !== 'admin' && m.id !== auth.user?.id"
            class="icon" :disabled="removingId === m.id"
            @click="memberToRemove = { id: m.id, name: m.name }"
            title="Expulsar"
          >
            <Icon name="trash" />
          </button>
        </li>
      </ul>
    </section>
  </div>

  <section v-if="history.length" class="card" style="margin-top: 16px;">
    <h3>Historial de invitaciones</h3>
    <div class="table-wrap" style="margin-top: 10px;">
    <table>
      <thead>
        <tr><th>Email</th><th>Rol</th><th>Estado</th><th>Creada</th><th>Resuelta</th></tr>
      </thead>
      <tbody>
        <tr v-for="i in history" :key="i.id">
          <td>{{ i.email }}</td>
          <td><span class="badge" :class="roleColor(i.role)">{{ i.role }}</span></td>
          <td><span class="badge" :class="statusColor(i.status)">{{ i.status }}</span></td>
          <td class="muted">{{ fmtDate(i.created_at) }}</td>
          <td class="muted">{{ fmtDate(i.accepted_at || '') }}</td>
        </tr>
      </tbody>
    </table>
    </div>
  </section>

  <ConfirmModal
    v-if="memberToRemove"
    title="Expulsar miembro"
    :message="`¿Quitar a ${memberToRemove.name} del equipo? Perderá acceso a los productos y ventas de la empresa.`"
    :loading="removingId !== null"
    danger
    @close="memberToRemove = null"
    @confirm="confirmRemove"
  />
</template>

<style scoped>
.company-card {
  display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
  margin-bottom: 16px;
  background: linear-gradient(135deg, var(--surface) 0%, var(--primary-soft) 200%);
}
.cc-icon {
  width: 56px; height: 56px; border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: grid; place-items: center;
  font-size: 22px; font-weight: 700;
  box-shadow: 0 6px 18px rgba(79, 70, 229, .35);
}

.grid {
  display: grid; gap: 16px;
  grid-template-columns: 1fr 1fr;
}
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

.inv-list, .member-list {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 4px;
}
.inv-list li, .member-list li {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 8px; border-radius: var(--radius-sm);
  transition: background .1s;
}
.inv-list li:hover, .member-list li:hover { background: var(--surface-2); }

.avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-weight: 600; font-size: 13px;
  flex-shrink: 0;
}

.empty.small { padding: 20px 12px; text-align: center; color: var(--text-muted); }
.empty.small p { margin: 0; font-size: 13px; }
</style>
