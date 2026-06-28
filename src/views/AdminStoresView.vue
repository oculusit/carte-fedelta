<template>
  <div class="admin-stores">
    <div class="page-header">
      <h2>Gestione negozi</h2>
      <button class="btn btn-primary" @click="openNewForm" v-if="!showForm">+ Nuovo negozio</button>
    </div>

    <div v-if="error" class="test-result error">{{ error }}</div>
    <div v-if="success" class="test-result success">{{ success }}</div>

    <div v-if="showForm" class="card form-card">
      <h3>{{ editingId ? 'Modifica negozio' : 'Nuovo negozio' }}</h3>
      <div class="input-group">
        <label>Nome negozio *</label>
        <input v-model="form.name" type="text" placeholder="es. Conad" />
      </div>
      <div class="input-group">
        <label>Logo del negozio</label>
        <p class="input-hint">Ritaglia un'immagine in formato orizzontale (8:5.5). Trascina per posizionare, zoom per ingrandire.</p>
        <LogoCropper ref="cropperRef" :model-value="form.logo_data" @change="onLogoChange" />
      </div>
      <div v-if="editingId" class="input-group">
        <label>Note admin</label>
        <textarea v-model="form.admin_notes" rows="2" placeholder="Note interne..."></textarea>
      </div>
      <div class="form-actions">
        <button class="btn btn-outline" @click="cancelForm">Annulla</button>
        <button class="btn btn-success" @click="saveStore" :disabled="saving">
          {{ saving ? 'Salvataggio...' : editingId ? 'Aggiorna' : 'Crea' }}
        </button>
      </div>
    </div>

    <div class="section">
      <h3>Nuovi negozi o Senza logo ({{ needsAttentionStores.length }})</h3>
      <div v-if="needsAttentionStores.length === 0" class="empty-state">Nessun negozio da sistemare</div>
      <div v-else v-for="s in needsAttentionStores" :key="s.id" :class="['store-item', s.status === 'pending' ? 'pending' : 'attention']">
        <div class="store-logo">
          <div class="mini-logo mini-logo-default">{{ s.name.charAt(0).toUpperCase() }}</div>
        </div>
        <div class="store-info">
          <span class="store-name">{{ s.name }}</span>
          <span class="store-meta" v-if="s.status === 'pending'">Proposto da {{ s.created_by_email || 'utente sconosciuto' }}</span>
          <span class="store-meta" v-else>Senza logo</span>
          <span :class="['logo-badge', s.has_logo ? 'logo-ok' : 'logo-missing']">
            {{ s.has_logo ? 'Logo OK!' : 'Manca logo!' }}
          </span>
          <span v-if="s.has_logo" :class="['size-badge', s.logo_size_kb < 10 ? 'size-ok' : 'size-heavy']">{{ s.logo_size_kb }} KB</span>
        </div>
        <div class="store-actions">
          <button v-if="s.status === 'pending'" class="btn btn-sm btn-success" @click="approveStore(s)">Approva</button>
          <button class="btn-icon" title="Modifica" @click="editStore(s)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
          <button v-if="isAdmin" class="btn-icon btn-icon-danger" title="Elimina" @click="deleteStore(s)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
        </div>
      </div>
    </div>

    <div v-if="orphanStores.length > 0" class="section">
      <h3>Negozi da creare ({{ orphanStores.length }})</h3>
      <p class="section-desc">Questi nomi negozio esistono nelle carte ma non nell'archivio negozi.</p>
      <div v-for="name in orphanStores" :key="name" class="store-item attention">
        <div class="store-logo">
          <div class="mini-logo mini-logo-default">{{ name.charAt(0).toUpperCase() }}</div>
        </div>
        <div class="store-info">
          <span class="store-name">{{ name }}</span>
          <span class="store-meta">Non ancora in archivio</span>
        </div>
        <div class="store-actions">
          <button class="btn btn-sm btn-primary" @click="createOrphanStore(name)">Crea negozio</button>
        </div>
      </div>
    </div>

    <div class="section">
      <h3>Negozi a posto ({{ approvedStores.length }})</h3>
      <div v-if="approvedStores.length === 0" class="empty-state">Nessun negozio</div>
      <div v-else v-for="s in approvedStores" :key="s.id" class="store-item">
        <div class="store-logo">
          <div class="mini-logo mini-logo-default">{{ s.name.charAt(0).toUpperCase() }}</div>
        </div>
        <div class="store-info">
          <span class="store-name">{{ s.name }}</span>
          <span class="store-meta" v-if="s.created_by_email">da {{ s.created_by_email }}</span>
          <span class="logo-badge logo-ok">Logo OK!</span>
          <span v-if="s.has_logo" :class="['size-badge', s.logo_size_kb < 10 ? 'size-ok' : 'size-heavy']">{{ s.logo_size_kb }} KB</span>
        </div>
        <div class="store-actions">
          <button class="btn-icon" title="Modifica" @click="editStore(s)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
          <button v-if="isAdmin" class="btn-icon btn-icon-danger" title="Elimina" @click="deleteStore(s)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { useAppStore } from '../stores/app.js'
import LogoCropper from '../components/LogoCropper.vue'

const router = useRouter()
const route = useRoute()
const storeApp = useAppStore()
const isAdmin = computed(() => auth.isAdmin())

const stores = ref([])
const orphanStores = ref([])
const storesLoaded = ref(false)
const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const error = ref('')
const success = ref('')
const cropperRef = ref(null)
const predefinedNames = ref(new Set())

const form = ref(emptyForm())

function emptyForm() {
  return { name: '', logo_data: '', admin_notes: '' }
}

function needsAttention(s) {
  return s.status === 'pending' || (s.status === 'approved' && !s.has_logo)
}

const needsAttentionStores = computed(() => stores.value.filter(needsAttention))
const approvedStores = computed(() => stores.value.filter(s => s.status === 'approved' && s.has_logo))

onMounted(async () => {
  if (!auth.canManageStores()) {
    router.push('/')
    return
  }
  try {
    const logos = await api.logos.getPredefined()
    predefinedNames.value = new Set(Object.values(logos).map(l => l.name.trim().toLowerCase()))
  } catch {}
  await loadStores()
  try {
    orphanStores.value = await api.stores.listOrphans()
  } catch (e) {}
  storesLoaded.value = true
})

watch([() => route.query.edit, storesLoaded], ([editName, loaded]) => {
  if (!loaded || !editName || stores.value.length === 0) return
  const target = stores.value.find(s => s.name.toLowerCase() === decodeURIComponent(editName).toLowerCase())
  if (target) {
    editStore(target)
  }
})

async function loadStores() {
  try {
    stores.value = await api.stores.getAll()
  } catch (e) {
    error.value = 'Errore caricamento: ' + e.message
  }
}

function openNewForm() {
  showForm.value = true
  editingId.value = null
  form.value = emptyForm()
  error.value = ''
  success.value = ''
}

function onLogoChange(data) {
  form.value.logo_data = data
}

function cancelForm() {
  showForm.value = false
  editingId.value = null
  form.value = emptyForm()
  error.value = ''
  success.value = ''
}

async function editStore(s) {
  editingId.value = s.id
  showForm.value = true
  form.value = {
    name: s.name,
    logo_data: '',
    admin_notes: s.admin_notes || '',
  }
  try {
    const full = await api.stores.get(s.id)
    if (full.logo_data) form.value.logo_data = full.logo_data
  } catch {}
  error.value = ''
  success.value = ''
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function saveStore() {
  if (!form.value.name.trim()) {
    error.value = 'Il nome del negozio è obbligatorio'
    return
  }
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const payload = {
      name: form.value.name,
      logo_type: form.value.logo_data ? 'upload' : 'predefined',
      logo_data: form.value.logo_data,
      logo_path: '',
    }
    if (editingId.value) {
      payload.admin_notes = form.value.admin_notes
      await api.stores.update(editingId.value, payload)
    } else {
      await api.stores.create(payload)
    }
    const wasEdit = editingId.value
    const wasEditWithLogo = wasEdit && form.value.logo_data
    cancelForm()
    await loadStores()
    if (wasEditWithLogo) {
      storeApp.loadCards()
    }
    success.value = wasEdit ? 'Negozio aggiornato' : 'Negozio creato'
    setTimeout(() => success.value = '', 3000)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function createOrphanStore(name) {
  saving.value = true
  try {
    await api.stores.create({ name })
    orphanStores.value = orphanStores.value.filter(n => n !== name)
    await loadStores()
    success.value = 'Negozio creato con successo'
    setTimeout(() => success.value = '', 3000)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function approveStore(s) {
  try {
    await api.stores.approve(s.id, '')
    await loadStores()
    success.value = 'Negozio approvato'
    setTimeout(() => success.value = '', 3000)
  } catch (e) {
    error.value = e.message
  }
}

async function deleteStore(s) {
  if (!confirm(`Eliminare il negozio "${s.name}"?`)) return
  try {
    await api.stores.delete(s.id)
    await loadStores()
  } catch (e) {
    error.value = e.message
  }
}
</script>

<style scoped>
.admin-stores {
  max-width: 700px;
  margin: 0 auto;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.page-header h2 {
  font-size: 20px;
}
.form-card {
  padding: 20px;
  margin-bottom: 20px;
}
.form-card h3 {
  font-size: 16px;
  margin-bottom: 16px;
}
.input-hint {
  font-size: 12px;
  color: var(--text-secondary);
  margin-bottom: 8px;
}
.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}
.section {
  margin-bottom: 24px;
}
.section h3 {
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}
.empty-state {
  color: var(--text-secondary);
  font-size: 14px;
  padding: 16px;
  text-align: center;
}
.store-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--card-bg);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  margin-bottom: 8px;
  border-left: 3px solid var(--success);
}
.store-item.pending {
  border-left-color: #f0ad4e;
}
.store-item.attention {
  border-left-color: #e91e63;
}
.store-logo {
  flex-shrink: 0;
}
.mini-logo {
  width: 36px;
  height: auto;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  color: white;
  background: var(--primary);
  overflow: hidden;
}
.mini-logo-default {
  height: 36px;
  background: var(--primary);
}
.mini-logo img {
  width: 100%;
  height: auto;
  object-fit: cover;
}
.store-info {
  flex: 1;
  min-width: 0;
}
.store-name {
  display: block;
  font-weight: 600;
  font-size: 14px;
}
.store-meta {
  display: block;
  font-size: 11px;
  color: var(--text-secondary);
}
.logo-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 1px 8px;
  border-radius: 6px;
  margin-top: 4px;
}
.logo-ok {
  background: #e8f5e9;
  color: #2e7d32;
}
.logo-missing {
  background: #ffebee;
  color: #c62828;
}
.size-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 1px 8px;
  border-radius: 6px;
  margin-top: 4px;
  margin-left: 4px;
}
.size-ok {
  background: #e8f5e9;
  color: #2e7d32;
}
.size-heavy {
  background: #ffebee;
  color: #c62828;
}
.btn-icon {
  background: none;
  border: 1px solid var(--border);
  border-radius: 6px;
  cursor: pointer;
  padding: 4px 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text);
  transition: background 0.15s;
}
.btn-icon:hover {
  background: #f0f0f0;
}
.btn-icon-danger {
  color: var(--danger);
}
.btn-icon-danger:hover {
  background: #fde8e8;
}
.store-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}
.test-result {
  padding: 12px;
  border-radius: var(--radius);
  font-size: 13px;
  margin-bottom: 16px;
}
.test-result.error {
  background: #fde8e8;
  border: 1px solid #f5c6c6;
  color: #a11a1a;
}
.test-result.success {
  background: #e8fde8;
  border: 1px solid #c6f5c6;
  color: #1a7a1a;
}
</style>
