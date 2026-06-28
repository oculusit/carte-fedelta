<template>
  <div class="settings">
    <div v-if="canInstall" class="card settings-card">
      <h3>Installa App</h3>
      <p class="section-desc">Aggiungi Carte Fedeltà alla schermata home per un accesso rapido</p>
      <button class="btn btn-primary btn-block" @click="installApp">
        Installa app
      </button>
    </div>

    <!-- 2) Account -->
    <div class="card settings-card">
      <h3>Account</h3>
      <div v-if="loggedIn">
        <p class="account-info">Connesso come {{ auth.getUserEmail() }}</p>
        <button class="btn btn-outline btn-block" @click="logout">Disconnetti</button>

        
      </div>
      <div v-else>
        <p class="account-info">Non connesso</p>
        <button class="btn btn-primary btn-block" @click="$router.push('/login')">Accedi</button>
      </div>
    </div>

    <!-- 3) Famiglia -->
    <div v-if="loggedIn" class="card settings-card">
      <h3>Famiglia</h3>
      <p class="section-desc">Gestisci gruppi famiglia per condividere le carte</p>
      <button class="btn btn-outline btn-block" @click="$router.push('/family')">
        Vai alla gestione
      </button>
    </div>

    <!-- 4) Sincronizzazione cloud -->
    <div class="card settings-card">
      <h3>Sincronizzazione cloud</h3>
      <p class="section-desc">Configura Supabase per sincronizzare le tue carte su tutti i dispositivi. I dati restano privati.</p>
      <button class="btn btn-outline btn-block" @click="$router.push('/supabase-setup')">
        Configura sincronizzazione
      </button>
    </div>

    <!-- 5) Cache applicazione -->
    <div class="card settings-card">
      <h3>Cache applicazione</h3>
      <p class="section-desc">Cancella la cache del Service Worker senza eliminare le carte salvate localmente.</p>
      <button class="btn btn-warning btn-block" @click="clearCache" :disabled="clearing">
        {{ clearing ? 'Cancellazione...' : 'Cancella cache e ricarica' }}
      </button>
    </div>

    <!-- 6) Informazioni -->
    <div class="card settings-card">
      <h3>Informazioni</h3>
      <div class="info-row">
        <span>Versione</span>
        <span>1.0.0</span>
      </div>
      <div class="info-row">
        <span>Stato rete</span>
        <span :class="store.isOnline ? 'tag-online' : 'tag-offline'">
          {{ store.isOnline ? 'Online' : 'Offline' }}
        </span>
      </div>
      <div class="info-row">
        <span>Sincronizzazione</span>
        <span :class="syncConfigured ? 'tag-online' : 'tag-offline'">
          {{ syncConfigured ? 'Configurata' : 'Non configurata' }}
        </span>
      </div>
      <hr class="divider" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'
import { auth } from '../services/auth.js'
import { toast } from '../services/toast.js'
import { isSupabaseConfigured } from '../services/supabase.js'

const router = useRouter()
const store = useAppStore()

const loggedIn = ref(false)
const pwaPrompt = ref(window.__pwaInstallPrompt || null)
const swReady = ref(false)
const canInstall = computed(() => {
  if (window.matchMedia('(display-mode: standalone)').matches) return false
  return !!(pwaPrompt.value || swReady.value || 'install' in navigator)
})
const clearing = ref(false)
const syncConfigured = computed(() => isSupabaseConfigured())

onMounted(async () => {
  loggedIn.value = auth.isLoggedIn()
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    pwaPrompt.value = e
  })
  setTimeout(() => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistration().then(reg => {
        if (reg && reg.active) swReady.value = true
      }).catch(() => {})
    }
  }, 3000)
})

function logout() {
  auth.logout()
  loggedIn.value = false
  router.push('/')
}

async function installApp() {
  if (pwaPrompt.value) {
    pwaPrompt.value.prompt()
    const result = await pwaPrompt.value.userChoice
    if (result.outcome === 'accepted') pwaPrompt.value = null
    return
  }
  if ('install' in navigator) {
    try {
      await navigator.install()
      return
    } catch {}
  }
  toast.show('Per installare, usa il menu del browser: "Aggiungi alla schermata Home"', 'info')
}

async function clearCache() {
  clearing.value = true
  try {
    const keys = await caches.keys()
    await Promise.all(keys.map(k => caches.delete(k)))
    const regs = await navigator.serviceWorker.getRegistrations()
    await Promise.all(regs.map(r => r.unregister()))
  } catch (e) {
    console.warn('Cache clear error:', e)
  }
  clearing.value = false
  window.location.reload()
}
</script>

<style scoped>
.settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.settings-card {
  padding: 20px;
}

.settings-card h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.mode-selector {
  display: flex;
  gap: 12px;
}

.mode-option {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 12px;
  border: 2px solid var(--border);
  border-radius: var(--radius);
  background: none;
  cursor: pointer;
  transition: all 0.2s;
}

.mode-option.active {
  border-color: var(--primary);
  background: rgba(26, 115, 232, 0.05);
}

.mode-icon {
  font-size: 28px;
}

.mode-name {
  font-weight: 600;
  font-size: 14px;
}

.mode-desc {
  font-size: 11px;
  color: var(--text-secondary);
  text-align: center;
}

.account-info {
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}

.divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 16px 0;
}

.fa-section h4,
.fa-setup h4 {
  font-size: 14px;
  margin-bottom: 4px;
}

.fa-desc {
  font-size: 12px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}

.qrcode-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 16px 0;
  min-height: 80px;
}

.qr-image {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.qr-loading {
  color: var(--text-secondary);
  font-size: 13px;
}

.fa-secret {
  font-size: 12px;
  color: var(--text-secondary);
  text-align: center;
  margin-bottom: 12px;
  word-break: break-all;
}

.fa-secret code {
  background: #f0f0f0;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.tag-server { color: var(--primary-dark); font-weight: 600; }
.tag-local { color: var(--success); font-weight: 600; }
.tag-online { color: var(--success); font-weight: 600; }
.tag-offline { color: var(--danger); font-weight: 600; }

.logo-preview {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.current-logo {
  width: 80px;
  height: 80px;
  border-radius: 16px;
  object-fit: contain;
  background: var(--bg);
  padding: 8px;
  border: 2px solid var(--border);
}

.btn-ghost {
  background: none;
  border: none;
  color: var(--primary);
  cursor: pointer;
  font-size: 13px;
  padding: 8px;
}

.btn-ghost:hover {
  text-decoration: underline;
}

.btn-ghost:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-sm {
  font-size: 12px;
  padding: 6px 12px;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  cursor: pointer;
  padding: 8px 0;
}

.toggle-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.status-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
  margin-top: 8px;
}

.status-on {
  background: #e6f4ea;
  color: #1e7e34;
}

.status-off {
  background: #fce8e6;
  color: #c5221f;
}

.warning-text {
  font-size: 12px;
  color: #c5221f;
  margin-top: 8px;
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

.input-group input,
.input-group textarea {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  background: var(--bg);
  color: var(--text);
  box-sizing: border-box;
}

.divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 16px 0;
}

.reveal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.reveal-modal {
  width: 90%;
  max-width: 420px;
  padding: 24px;
}

.reveal-modal h3 {
  margin-bottom: 8px;
}

.reveal-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 16px;
}

.reveal-error {
  font-size: 13px;
  color: #c5221f;
  margin-bottom: 8px;
}

.reveal-result {
  margin-top: 16px;
  padding: 12px;
  background: var(--bg);
  border-radius: var(--radius);
}

.reveal-result label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text-secondary);
}

.reveal-seed {
  display: block;
  font-family: monospace;
  font-size: 13px;
  word-break: break-all;
  padding: 8px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 4px;
  margin-bottom: 8px;
}

.reveal-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.fa-hint {
  font-size: 12px;
  color: var(--text-secondary);
  font-style: italic;
}
</style>
