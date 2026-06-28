<template>
  <div class="family">
    <div class="page-header">
      <h2>Gestione Famiglia</h2>
    </div>

    <div v-if="loading" class="loading">Caricamento...</div>

    <template v-if="!loading">
      <!-- Invitations -->
      <div v-if="data.invitations?.length" class="card">
        <h3>Inviti in sospeso</h3>
        <div v-for="inv in data.invitations" :key="inv.id" class="invite-row">
          <span>Invito da <strong>{{ inv.owner_email }}</strong> per il gruppo <strong>{{ inv.name }}</strong></span>
          <div class="invite-actions">
            <button class="btn btn-sm btn-success" @click="accept(inv.id)">Accetta</button>
            <button class="btn btn-sm btn-outline" @click="reject(inv.id)">Rifiuta</button>
          </div>
        </div>
      </div>

      <!-- Create group -->
      <div v-if="!data.owned?.length && !data.member?.length" class="card">
        <h3>Crea un gruppo famiglia</h3>
        <p class="section-desc">Crea un gruppo per condividere le tue carte con altri utenti (massimo 1 gruppo per utente)</p>
        <div class="input-group">
          <input v-model="newGroupName" type="text" placeholder="Nome del gruppo (es. Famiglia Rossi)" @keyup.enter="createGroup" />
        </div>
        <button class="btn btn-primary btn-block" @click="createGroup" :disabled="!newGroupName.trim()">Crea gruppo</button>
      </div>

      <!-- Owned groups -->
      <div v-for="g in data.owned" :key="g.id" class="card">
        <div class="group-header">
          <h3>{{ g.name }}</h3>
          <button class="btn btn-sm btn-danger" @click="deleteGroup(g.id)">Elimina</button>
        </div>
        <p class="group-meta">Proprietario: tu</p>

        <h4>Membri</h4>
        <div class="members-list">
          <div v-for="m in g.members" :key="m.id" class="member-row">
            <span class="member-email">{{ m.email }}</span>
            <span class="status-dot" :class="'status-' + memberStatus(m, g)" :title="memberStatusLabel(m, g)"></span>
            <button v-if="canRemove(m, g)" class="btn-icon-only btn-danger" title="Rimuovi" @click="removeMember(g.id, m.user_id)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
            <button v-if="canReinvite(m, g)" class="btn-icon-only" title="Reinvita" @click="resendInvite(g.id, m.email)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
            <span v-if="isOwner(m, g)" class="owner-icon" title="Proprietario">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
          </div>
          <div class="invite-form">
            <input v-model="inviteEmail" type="email" placeholder="Email da invitare" @keyup.enter="invite(g.id)" />
            <button class="btn btn-sm btn-primary" @click="invite(g.id)" :disabled="!inviteEmail.trim()">Invita</button>
          </div>
        </div>
      </div>

      <!-- Member groups -->
      <div v-for="g in data.member" :key="g.id" class="card">
        <div class="group-header">
          <h3>{{ g.name }}</h3>
          <button class="btn btn-sm btn-outline" @click="leave(g.id)">Abbandona</button>
        </div>
        <p class="group-meta">Membro del gruppo</p>

        <h4>Membri</h4>
        <div class="members-list">
          <div v-for="m in g.members" :key="m.id" class="member-row">
            <span class="member-email">{{ m.email }}</span>
            <span class="status-dot" :class="'status-' + memberStatus(m, g)" :title="memberStatusLabel(m, g)"></span>
          </div>
        </div>
      </div>

      <div v-if="!data.owned?.length && !data.member?.length && !data.invitations?.length" class="card">
        <p class="empty-msg">Non hai ancora gruppi famiglia. Creane uno per condividere le tue carte.</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAppStore } from '../stores/app.js'
import { api } from '../services/api.js'
import { toast } from '../services/toast.js'
import { auth } from '../services/auth.js'

const store = useAppStore()
const loading = ref(true)
const data = ref({ owned: [], member: [], invitations: [] })
const newGroupName = ref('')
const inviteEmail = ref('')
const currentUserId = ref(Number(auth.getUserId()))

onMounted(load)

async function load() {
  loading.value = true
  try {
    data.value = await api.family.list()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    loading.value = false
  }
}

async function createGroup() {
  const name = newGroupName.value.trim()
  if (!name) return
  try {
    await api.family.create(name)
    newGroupName.value = ''
    toast.show('Gruppo creato', 'success')
    await load()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function deleteGroup(id) {
  if (!confirm('Eliminare il gruppo?')) return
  try {
    await api.family.delete(id)
    toast.show('Gruppo eliminato', 'success')
    await load()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function invite(groupId) {
  const email = inviteEmail.value.trim()
  if (!email) return
  try {
    await api.family.invite(groupId, email)
    inviteEmail.value = ''
    toast.show('Invito inviato a ' + email, 'success')
    const updated = await api.family.get(groupId)
    const g = data.value.owned.find(x => x.id === groupId)
    if (g) Object.assign(g, updated)
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function accept(groupId) {
  try {
    await api.family.accept(groupId)
    toast.show('Invito accettato', 'success')
    await load()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function reject(groupId) {
  try {
    await api.family.reject(groupId)
    toast.show('Invito rifiutato', 'success')
    await load()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function leave(groupId) {
  if (!confirm('Abbandonare il gruppo?')) return
  try {
    await api.family.leave(groupId)
    toast.show('Gruppo abbandonato', 'success')
    await load()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function removeMember(groupId, userId) {
  if (!confirm('Rimuovere questo membro?')) return
  try {
    await api.family.removeMember(groupId, userId)
    toast.show('Membro rimosso', 'success')
    const updated = await api.family.get(groupId)
    const g = data.value.owned.find(x => x.id === groupId)
    if (g) Object.assign(g, updated)
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function resendInvite(groupId, email) {
  try {
    await api.family.invite(groupId, email)
    toast.show('Invito reinviato a ' + email, 'success')
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

function memberStatus(m, g) {
  if (m.user_id === g.owner_id) return 'owner'
  return m.status
}

function memberStatusLabel(m, g) {
  if (m.user_id === g.owner_id) return 'Proprietario'
  if (m.status === 'accepted') return 'Membro'
  if (m.status === 'pending') return 'In attesa'
  return 'Non accettato'
}

function canRemove(m, g) {
  return g.owner_id === currentUserId.value && m.status === 'accepted' && m.user_id !== g.owner_id
}

function canReinvite(m, g) {
  return g.owner_id === currentUserId.value && m.status === 'pending'
}

function isOwner(m, g) {
  return m.user_id === g.owner_id
}


</script>

<style scoped>
.family { display: flex; flex-direction: column; gap: 16px; }
.card { padding: 20px; }
.card h3 { font-size: 16px; margin-bottom: 8px; }
.card h4 { font-size: 14px; margin: 12px 0 8px; }
.section-desc { font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; }
.group-header { display: flex; justify-content: space-between; align-items: center; }
.group-meta { font-size: 12px; color: var(--text-secondary); margin-bottom: 8px; }

.members-list { display: flex; flex-direction: column; gap: 6px; }
.member-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  padding: 6px 0;
}
.member-email {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 2px solid rgba(0,0,0,0.12);
}
.status-accepted { background: #2e7d32; }
.status-pending { background: #f5a623; }
.status-rejected { background: #c62828; }
.status-owner { background: #1a73e8; }
.btn-icon-only {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  border-radius: 6px;
  transition: background 0.15s, color 0.15s;
  flex-shrink: 0;
}
.btn-icon-only:hover {
  background: #f0f0f0;
  color: var(--text);
}
.btn-icon-only.btn-danger:hover {
  background: #fde8e8;
  color: var(--danger);
}
.owner-icon {
  padding: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #bbb;
  flex-shrink: 0;
}
.invite-form { display: flex; gap: 8px; margin-top: 8px; }
.invite-form input { flex: 1; }
.invite-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.invite-actions { display: flex; gap: 6px; flex-shrink: 0; }
.empty-msg { text-align: center; color: var(--text-secondary); padding: 24px 0; }
.btn-sm { font-size: 12px; padding: 4px 10px; }
</style>
