<template>
  <div class="admin-users">
    <div class="page-header">
      <h2>Gestione Utenti</h2>
      <button v-if="isAdmin" class="btn btn-primary btn-sm add-user-btn" @click="showCreateModal = true">+ Aggiungi utente</button>
    </div>

    <div v-if="loading" class="loading">Caricamento utenti...</div>

    <div v-else class="users-list">
      <div
        v-for="user in users"
        :key="user.id"
        class="card user-card"
        :class="{ 'user-inactive': !user.is_active }"
      >
        <div class="user-row" @click="toggleExpand(user)">
          <div class="user-info">
            <div class="user-email">
              {{ user.email }}
              <span v-if="user.is_admin" class="badge badge-admin">admin</span>
              <span v-if="user.is_moderator" class="badge badge-moderator">mod</span>
              <span v-if="!user.is_active" class="badge badge-disabled">disabilitato</span>
              <span v-if="user.status === 'pending'" class="badge badge-pending">in attesa</span>
              <span v-if="user.status === 'rejected'" class="badge badge-rejected">rifiutato</span>
            </div>
            <div class="user-meta">
              <span>{{ user.card_count || 0 }} tessere</span>
              <span v-if="user['2fa_enabled']" class="tag-2fa">2FA attivo</span>
              <span v-if="user.email_confirmed_at" class="tag-confirmed">email confermata</span>
              <span class="user-date">{{ formatDate(user.created_at) }}</span>
            </div>
          </div>
          <span class="expand-arrow">{{ expanded[user.id] ? '&#9660;' : '&#9658;' }}</span>
        </div>

        <div v-if="expanded[user.id]" class="user-details">
          <div v-if="loadingCards[user.id]" class="loading">Caricamento tessere...</div>

          <div v-else-if="userCards[user.id] && userCards[user.id].length" class="user-cards">
            <div
              v-for="card in userCards[user.id]"
              :key="card.id"
              class="card-item"
            >
              <div class="card-item-store">{{ card.store_name }}</div>
              <div v-if="card.holder_name" class="card-item-holder">{{ card.holder_name }}</div>
            </div>
          </div>
          <div v-else class="no-cards">Nessuna tessera associata</div>

          <div class="user-actions">
            <button
              v-if="!isLastAdmin(user) || !user.is_active"
              class="btn btn-sm"
              :class="user.is_active ? 'btn-warning' : 'btn-success'"
              @click="toggleActive(user)"
            >
              {{ user.is_active ? 'Disabilita' : 'Attiva' }}
            </button>
            <button
              v-if="user.status === 'pending'"
              class="btn btn-sm btn-success"
              @click="approveUser(user)"
            >
              Approva
            </button>
            <button
              v-if="user.status === 'pending'"
              class="btn btn-sm btn-danger"
              @click="rejectUser(user)"
            >
              Rifiuta
            </button>
            <button
              v-if="!user.is_admin"
              class="btn btn-sm"
              :class="user.is_moderator ? 'btn-outline' : 'btn-primary'"
              @click="toggleModerator(user)"
            >
              {{ user.is_moderator ? 'Rimuovi moderatore' : 'Nomina moderatore' }}
            </button>
            <button
              v-if="!user.is_admin"
              class="btn btn-sm btn-primary"
              @click="nominaAdmin(user)"
            >
              Nomina admin
            </button>
            <button
              v-if="user.is_admin && !isLastAdmin(user)"
              class="btn btn-sm btn-warning"
              @click="rimuoviAdmin(user)"
            >
              Rimuovi admin
            </button>
            <button
              v-if="user['2fa_enabled']"
              class="btn btn-sm btn-outline"
              @click="reset2fa(user)"
            >
              Reset 2FA
            </button>
            <button
              class="btn btn-sm btn-outline"
              @click="openChangePassword(user)"
            >
              Cambia password
            </button>
            <button
              v-if="!isLastAdmin(user)"
              class="btn btn-sm btn-danger"
              @click="confirmDelete(user)"
            >
              Elimina
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!loading && !users.length" class="empty">
      Nessun utente registrato.
    </div>

    <!-- Create user modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false; createEmail = ''; createPass = ''; createNotify = true">
      <div class="modal">
        <h3>Aggiungi utente</h3>
        <div class="input-group">
          <label>Email</label>
          <input v-model="createEmail" type="email" placeholder="nuovo@utente.it" class="input" />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="createPass" type="password" placeholder="Minimo 6 caratteri" class="input" minlength="6" />
        </div>
        <label class="checkbox-label">
          <input v-model="createNotify" type="checkbox" />
          <span>Invia notifica email con le credenziali</span>
        </label>
        <div class="modal-actions">
          <button class="btn btn-ghost" @click="showCreateModal = false; createEmail = ''; createPass = ''; createNotify = true">Annulla</button>
          <button class="btn btn-primary" :disabled="!createEmail || !createPass || createPass.length < 6" @click="executeCreateUser">
            {{ creating ? 'Creazione...' : 'Crea utente' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="deleting" class="modal-overlay" @click.self="deleting = null; deleteConfirmText = ''">
      <div class="modal">
        <h3>Elimina utente</h3>
        <p>Sei sicuro di voler eliminare <strong>{{ deleting.email }}</strong>?</p>
        <p v-if="deleting.is_admin" class="modal-warning">Attenzione: stai eliminando un amministratore!</p>
        <p class="modal-warning">Verranno eliminate anche {{ deleting.card_count || 0 }} tessere associate. Operazione irreversibile.</p>
        <p class="modal-warning">Digita <strong>CONFERMA</strong> per procedere:</p>
        <input v-model="deleteConfirmText" type="text" placeholder="CONFERMA" class="input confirm-input" />
        <div class="modal-actions">
          <button class="btn btn-ghost" @click="deleting = null; deleteConfirmText = ''">Annulla</button>
          <button
            class="btn btn-danger"
            :disabled="deleteConfirmText !== 'CONFERMA'"
            @click="executeDelete"
          >
            Elimina definitivamente
          </button>
        </div>
      </div>
    </div>

    <!-- Admin action confirmation modal (disable/remove-admin) -->
    <div v-if="confirmingAdmin" class="modal-overlay" @click.self="confirmingAdmin = null; adminConfirmText = ''">
      <div class="modal">
        <h3>{{
          confirmingAdmin.action === 'disable' ? 'Disabilita amministratore' :
          confirmingAdmin.action === 'remove-admin' ? 'Rimuovi amministratore' :
          'Elimina amministratore'
        }}</h3>
        <p>Stai per {{
          confirmingAdmin.action === 'disable' ? 'disabilitare' :
          confirmingAdmin.action === 'remove-admin' ? 'rimuovere i permessi di admin a' :
          'eliminare'
        }} <strong>{{ confirmingAdmin.user.email }}</strong>.</p>
        <p class="modal-warning">Questa azione è irreversibile. Digita <strong>CONFERMA</strong> per procedere:</p>
        <input v-model="adminConfirmText" type="text" placeholder="CONFERMA" class="input confirm-input" />
        <div class="modal-actions">
          <button class="btn btn-ghost" @click="confirmingAdmin = null; adminConfirmText = ''">Annulla</button>
          <button
            class="btn btn-danger"
            :disabled="adminConfirmText !== 'CONFERMA'"
            @click="executeAdminAction"
          >
            {{ confirmingAdmin?.action === 'disable' ? 'Disabilita' : confirmingAdmin?.action === 'remove-admin' ? 'Rimuovi admin' : 'Elimina' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Change password modal -->
    <div v-if="changingPassword" class="modal-overlay" @click.self="changingPassword = null; changePassNew = ''; changePassConfirm = ''">
      <div class="modal">
        <h3>Cambia password</h3>
        <p>Nuova password per <strong>{{ changingPassword.email }}</strong></p>
        <div class="input-group">
          <label>Nuova password</label>
          <input v-model="changePassNew" type="password" placeholder="Minimo 6 caratteri" class="input" minlength="6" />
        </div>
        <div class="input-group">
          <label>Conferma password</label>
          <input v-model="changePassConfirm" type="password" placeholder="Ripeti la password" class="input" minlength="6" />
        </div>
        <div class="modal-actions">
          <button class="btn btn-ghost" @click="changingPassword = null; changePassNew = ''; changePassConfirm = ''">Annulla</button>
          <button class="btn btn-primary" :disabled="!changePassNew || changePassNew.length < 6 || changePassNew !== changePassConfirm" @click="executeChangePassword">
            Salva password
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { toast } from '../services/toast.js'

const router = useRouter()
const users = ref([])
const loading = ref(true)
const expanded = ref({})
const userCards = ref({})
const loadingCards = ref({})
const confirmingAdmin = ref(null)
const adminConfirmText = ref('')
const deleting = ref(null)
const deleteConfirmText = ref('')
const changingPassword = ref(null)
const changePassNew = ref('')
const changePassConfirm = ref('')
const showCreateModal = ref(false)
const createEmail = ref('')
const createPass = ref('')
const createNotify = ref(true)
const creating = ref(false)
const isAdmin = computed(() => auth.isAdmin())

const isLastAdmin = computed(() => (user) => {
  if (!user.is_admin) return false
  const admins = users.value.filter(u => u.is_admin)
  return admins.length <= 1
})

onMounted(() => {
  if (!auth.canModerateUsers()) {
    router.push('/')
    return
  }
  loadUsers()
})

async function executeCreateUser() {
  if (!createEmail.value || !createPass.value || createPass.value.length < 6) return
  creating.value = true
  try {
    await api.users.create({
      email: createEmail.value,
      password: createPass.value,
      notify: createNotify.value,
    })
    toast.show('Utente creato con successo', 'success')
    showCreateModal.value = false
    createEmail.value = ''
    createPass.value = ''
    createNotify.value = true
    loadUsers()
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    creating.value = false
  }
}

async function loadUsers() {
  try {
    users.value = await api.users.getAll()
  } catch (e) {
    toast.show('Errore caricamento: ' + e.message, 'error')
  } finally {
    loading.value = false
  }
}

async function toggleExpand(user) {
  if (!expanded.value[user.id]) {
    expanded.value[user.id] = true
    loadUserCards(user.id)
  } else {
    expanded.value[user.id] = false
  }
}

async function loadUserCards(userId) {
  loadingCards.value[userId] = true
  try {
    userCards.value[userId] = await api.users.getCards(userId)
  } catch (e) {
    toast.show('Errore caricamento tessere: ' + e.message, 'error')
  } finally {
    loadingCards.value[userId] = false
  }
}

async function toggleActive(user) {
  if (user.is_active && user.is_admin && !isLastAdmin.value(user)) {
    confirmingAdmin.value = { user, action: 'disable' }
    adminConfirmText.value = ''
    return
  }
  try {
    await api.users.update(user.id, { is_active: !user.is_active })
    user.is_active = !user.is_active
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function approveUser(user) {
  try {
    await api.users.update(user.id, { status: 'approved' })
    user.status = 'approved'
    user.is_active = 1
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function rejectUser(user) {
  if (!confirm('Rifiutare la registrazione di ' + user.email + '?')) return
  try {
    await api.users.update(user.id, { status: 'rejected' })
    user.status = 'rejected'
    user.is_active = 0
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function nominaAdmin(user) {
  if (!confirm('Nominare ' + user.email + ' come amministratore?')) return
  try {
    await api.users.update(user.id, { is_admin: true })
    user.is_admin = true
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

function rimuoviAdmin(user) {
  confirmingAdmin.value = { user, action: 'remove-admin' }
  adminConfirmText.value = ''
}

async function executeAdminAction() {
  const { user, action } = confirmingAdmin.value
  confirmingAdmin.value = null
  adminConfirmText.value = ''
  try {
    if (action === 'disable') {
      await api.users.update(user.id, { is_active: false })
      user.is_active = false
    } else if (action === 'remove-admin') {
      await api.users.update(user.id, { is_admin: false })
      user.is_admin = false
    } else {
      await api.users.delete(user.id)
      users.value = users.value.filter(u => u.id !== user.id)
    }
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

async function reset2fa(user) {
  if (!confirm('Resettare il 2FA per ' + user.email + '?')) return
  try {
    await api.users.update(user.id, { reset_2fa: true })
    user['2fa_enabled'] = false
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

function openChangePassword(user) {
  changingPassword.value = user
  changePassNew.value = ''
  changePassConfirm.value = ''
}

async function executeChangePassword() {
  if (!changePassNew.value || changePassNew.value.length < 6 || changePassNew.value !== changePassConfirm.value) return
  try {
    await api.users.update(changingPassword.value.id, { password: changePassNew.value })
        toast.show('Password cambiata con successo per ' + changingPassword.value.email, 'success')
    changingPassword.value = null
    changePassNew.value = ''
    changePassConfirm.value = ''
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

function confirmDelete(user) {
  if (user.is_admin && !isLastAdmin.value(user)) {
    confirmingAdmin.value = { user, action: 'delete' }
    adminConfirmText.value = ''
    return
  }
  deleting.value = user
  deleteConfirmText.value = ''
}

async function executeDelete() {
  try {
    await api.users.delete(deleting.value.id)
    users.value = users.value.filter(u => u.id !== deleting.value.id)
    deleting.value = null
    deleteConfirmText.value = ''
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' })
}

</script>

<style scoped>
.admin-users {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 4px;
}

.page-header h2 {
  font-size: 18px;
  margin: 0;
}

.add-user-btn {
  margin-left: auto;
}

.loading, .empty {
  text-align: center;
  color: var(--text-secondary);
  padding: 32px 0;
}

.user-card {
  padding: 0;
  overflow: hidden;
}

.user-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.15s;
}

.user-row:hover {
  background: rgba(0,0,0,0.02);
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-email {
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.user-meta {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 4px;
  display: flex;
  gap: 12px;
  align-items: center;
}

.expand-arrow {
  color: var(--text-secondary);
  font-size: 10px;
  margin-left: 12px;
}

.user-inactive {
  opacity: 0.55;
}

.badge {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 8px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.badge-admin { background: #e8f5e9; color: #2e7d32; }
.badge-moderator { background: #e3f2fd; color: #1565c0; }
.badge-disabled { background: #fbe9e7; color: #c62828; }
.badge-pending { background: #fff8e1; color: #f57f17; }
.badge-rejected { background: #fbe9e7; color: #c62828; }

.tag-2fa {
  font-size: 11px;
  color: var(--success);
  font-weight: 600;
}

.tag-confirmed {
  font-size: 11px;
  color: var(--primary);
  font-weight: 600;
}

.user-date {
  color: var(--text-secondary);
}

.user-details {
  border-top: 1px solid var(--border);
  padding: 12px 16px;
}

.user-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.card-item {
  padding: 8px 10px;
  background: var(--bg);
  border-radius: var(--radius);
  font-size: 13px;
}

.card-item-store {
  font-weight: 600;
}

.card-item-holder {
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.no-cards {
  font-size: 12px;
  color: var(--text-secondary);
  padding: 8px 0;
  margin-bottom: 12px;
  font-style: italic;
}

.user-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.user-actions .btn-sm {
  font-size: 12px;
  padding: 4px 10px;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  padding: 20px;
}

.modal {
  background: var(--card-bg, #fff);
  border-radius: var(--radius);
  padding: 24px;
  max-width: 420px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.modal h3 {
  margin: 0 0 12px;
  font-size: 16px;
}

.modal p {
  font-size: 13px;
  margin: 0 0 8px;
  color: var(--text-secondary);
}

.modal-warning {
  color: var(--danger) !important;
  font-weight: 600;
  font-size: 12px !important;
}

.confirm-input {
  width: 100%;
  margin: 8px 0 16px;
  box-sizing: border-box;
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.btn-icon {
  font-size: 18px;
  padding: 4px 8px;
}

.btn-sm {
  font-size: 12px;
  padding: 6px 12px;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: var(--text-secondary);
  margin: 8px 0;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin-top: 2px;
  flex-shrink: 0;
}

.input-group {
  margin-bottom: 12px;
}

.input-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 4px;
  color: var(--text-secondary);
}

.input-group .input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  background: var(--card-bg);
  color: inherit;
  box-sizing: border-box;
}
</style>
