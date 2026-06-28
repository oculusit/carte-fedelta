<template>
  <div class="app">
    <AppHeader />
    <main class="main-content">
      <router-view />
    </main>
    <footer class="app-footer">
      <div class="footer-links">
        <a href="#/privacy" target="_blank">Privacy</a>
        <span class="footer-sep">·</span>
        <span>VibeCoded by <a href="https://oculus.it" target="_blank" rel="noopener">Oculus.it</a> - Versione 1.0.0</span>
      </div>
      <span v-if="store.encryptionSeedSet" class="footer-encryption">Crittografia Attiva</span>
    </footer>
    <ToastContainer />

    <button v-if="showFab" class="fab" @click="goToNewCard" title="Nuova carta">+</button>

  <div v-if="showInstallBanner" class="install-banner">
    <span class="install-banner-text">Installa l'app per un accesso più rapido</span>
    <button class="btn btn-sm btn-primary" @click="installPwa">Installa</button>
    <button class="install-dismiss" @click="dismissInstallBanner" aria-label="Chiudi">×</button>
  </div>

    <div v-if="showInstallModal" class="install-modal-overlay">
      <div class="install-modal card">
        <h3>Installa Carte Fedeltà</h3>
        <p class="install-modal-text">
          Per installare l'app, usa il menu del tuo browser:<br>
          <strong>Chrome/Safari</strong>: menu → "Aggiungi alla schermata Home"
        </p>
        <div class="install-modal-actions">
          <button class="btn btn-primary" @click="showInstallModal = false">OK</button>
        </div>
      </div>
    </div>

    <div v-if="pendingInvitation" class="invite-modal-overlay">
      <div class="invite-modal card">
        <h3>Invito gruppo famiglia</h3>
        <p class="invite-modal-text">
          Sei stato invitato ad usare <strong>{{ store.appName || 'Carte Fedeltà' }}</strong> in condivisione con il gruppo <strong>{{ pendingInvitation.name }}</strong>.
          Se confermerai la volontà di condividere le carte con il gruppo potrai vedere le carte pubbliche che verranno condivise da tutti.
          Se non vuoi puoi declinare l'invito ora.
        </p>
        <div class="invite-modal-actions">
          <button class="btn btn-success" @click="acceptInvitation" :disabled="inviteLoading">
            {{ inviteLoading ? '...' : 'Accetta' }}
          </button>
          <button class="btn btn-danger" @click="declineInvitation" :disabled="inviteLoading">
            {{ inviteLoading ? '...' : 'Declina' }}
          </button>
          <button class="btn btn-outline" @click="ignoreInvitation">Ignora</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useRouter } from 'vue-router'
import { useAppStore } from './stores/app.js'
import { api } from './services/api.js'
import { auth } from './services/auth.js'
import { toast } from './services/toast.js'
import { isSupabaseConfigured, getSupabaseClient } from './services/supabase.js'
import AppHeader from './components/AppHeader.vue'
import ToastContainer from './components/ToastContainer.vue'

const store = useAppStore()
const route = useRoute()
const router = useRouter()
const pendingInvitation = ref(null)
const inviteLoading = ref(false)
const showInstallBanner = ref(false)
const showInstallModal = ref(false)

function checkInstallPrompt() {
  if (window.__pwaInstallPrompt && !window.matchMedia('(display-mode: standalone)').matches) {
    showInstallBanner.value = true
  }
}

async function installPwa() {
  const prompt = window.__pwaInstallPrompt
  if (prompt) {
    prompt.prompt()
    const choice = await prompt.userChoice
    if (choice.outcome === 'accepted') {
      showInstallBanner.value = false
    }
    return
  }
  if ('install' in navigator) {
    try {
      await navigator.install()
      showInstallBanner.value = false
      return
    } catch {}
  }
  showInstallModal.value = true
}

function dismissInstallBanner() {
  showInstallBanner.value = false
  showInstallModal.value = false
  localStorage.setItem('pwa_install_dismissed', '1')
}

function resetDismissInstall() {
  localStorage.removeItem('pwa_install_dismissed')
}

function detectPwaCapability() {
  if (window.matchMedia('(display-mode: standalone)').matches) return
  if (localStorage.getItem('pwa_install_dismissed')) return
  if (window.__pwaInstallPrompt || 'install' in navigator) {
    showInstallBanner.value = true
    return
  }
  navigator.serviceWorker.getRegistration().then(reg => {
    if (reg && reg.active) {
      showInstallBanner.value = true
    }
  }).catch(() => {})
}

const showFab = computed(() => {
  if (route.path.startsWith('/admin/')) return false
  if (route.name && (route.name === 'login' || route.name === 'setup' || route.name === 'admin-setup' || route.name === 'card-new' || route.name === 'card-edit' || route.name === 'card-detail' || route.name === 'settings' || route.name === 'family' || route.name.startsWith('admin-'))) return false
  return true
})

function goToNewCard() {
  if (!auth.isLoggedIn()) {
    router.push('/login')
  } else {
    router.push('/card/new')
  }
}

onMounted(async () => {

  if (navigator.storage && navigator.storage.persist) {
    navigator.storage.persist().catch(() => {})
  }

  detectPwaCapability()
  setTimeout(detectPwaCapability, 3000)

  window.addEventListener('appinstalled', () => { showInstallBanner.value = false })

  try {
    const supabase = getSupabaseClient()
    if (supabase) {
      const { data: { session } } = await supabase.auth.getSession()
      if (session?.user) {
        localStorage.setItem('user_id', session.user.id)
        localStorage.setItem('user_email', session.user.email)
      }
    }
  } catch {}

  if (auth.isLoggedIn()) {
    store.loadCards()
    store.loadMissingLogos()
  }

  api.settings.info().then(r => {
    if (r.app_name) {
      document.title = r.app_name
      store.appName = r.app_name
    }
    store.encryptionSeedSet = !!r.encryption_seed_set
  }).catch(() => {})
  checkInvitations()
})

watch(() => route.path, () => {
  if (auth.isLoggedIn()) checkInvitations()
})

watch(() => store.error, (err) => {
  if (err) toast.show(err, 'error')
})

async function checkInvitations() {
  if (!auth.isLoggedIn() || pendingInvitation.value) return
  const ignored = localStorage.getItem('invite_ignored_until')
  if (ignored && Date.now() < parseInt(ignored)) return
  try {
    const data = await api.family.list()
    if (data.invitations?.length) {
      pendingInvitation.value = data.invitations[0]
    }
  } catch {}
}

async function acceptInvitation() {
  inviteLoading.value = true
  try {
    await api.family.accept(pendingInvitation.value.id)
    toast.show('Invito accettato', 'success')
    pendingInvitation.value = null
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    inviteLoading.value = false
  }
}

async function declineInvitation() {
  inviteLoading.value = true
  try {
    await api.family.reject(pendingInvitation.value.id)
    toast.show('Invito rifiutato', 'success')
    pendingInvitation.value = null
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    inviteLoading.value = false
  }
}

function ignoreInvitation() {
  localStorage.setItem('invite_ignored_until', String(Date.now() + 15 * 60 * 1000))
  pendingInvitation.value = null
}
</script>

<style>
.invite-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.invite-modal {
  max-width: 420px;
  width: 100%;
  padding: 24px;
}

.invite-modal h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.invite-modal-text {
  font-size: 13px;
  line-height: 1.6;
  color: var(--text-secondary);
  margin-bottom: 20px;
}

.invite-modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.invite-modal-actions .btn {
  min-width: 120px;
}

.sync-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.sync-modal {
  max-width: 360px;
  width: 100%;
  padding: 32px 24px;
  text-align: center;
}

.sync-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.sync-text {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.5;
}

.install-banner {
  position: fixed;
  bottom: 72px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  z-index: 600;
  max-width: 400px;
  width: calc(100% - 32px);
}

.install-banner-text {
  flex: 1;
  font-size: 13px;
  color: var(--text);
  line-height: 1.3;
}

.install-dismiss {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: var(--text-secondary);
  padding: 0 2px;
  line-height: 1;
}

.install-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.install-modal {
  max-width: 380px;
  width: 100%;
  padding: 24px;
}

.install-modal h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.install-modal-text {
  font-size: 13px;
  line-height: 1.6;
  color: var(--text-secondary);
  margin-bottom: 20px;
}

.install-modal-actions {
  display: flex;
  justify-content: center;
}

.fab {
  position: fixed;
  bottom: 80px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--primary, #1a73e8);
  color: white;
  border: none;
  font-size: 28px;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  z-index: 500;
  transition: transform 0.15s, box-shadow 0.15s;
}

.fab:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}

.fab:active {
  transform: scale(0.95);
}
</style>
