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
        <span>VibeCoded by <a href="https://oculus.it" target="_blank" rel="noopener">Oculus.it</a> - Versione 1.2.0</span>
      </div>
      <span v-if="store.encryptionSeedSet" class="footer-encryption">Crittografia Attiva</span>
    </footer>
    <ToastContainer />
    <UpdateChecker />

    <button v-if="showFab" class="fab" @click="goToNewCard" title="Nuova carta">+</button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useRouter } from 'vue-router'
import { useAppStore } from './stores/app.js'
import AppHeader from './components/AppHeader.vue'
import ToastContainer from './components/ToastContainer.vue'
import UpdateChecker from './components/UpdateChecker.vue'

const store = useAppStore()
const route = useRoute()
const router = useRouter()

const showFab = computed(() => {
  if (route.path.startsWith('/admin/')) return false
  if (route.name && (route.name === 'card-new' || route.name === 'card-edit' || route.name === 'card-detail' || route.name === 'settings')) return false
  return true
})

function goToNewCard() {
  router.push('/card/new')
}

onMounted(async () => {
  store.loadCards()
  store.loadMissingLogos()

  if (window.Capacitor?.isNativePlatform?.()) {
    try {
      const { App } = await import('@capacitor/app')
      App.addListener('backButton', (data) => {
        if (route.name === 'dashboard') {
          App.exitApp()
        } else {
          router.back()
        }
      })
    } catch {}
  }
})
</script>

<style>
.app {
  max-width: 600px;
  margin: 0 auto;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  position: relative;
  background: var(--bg);
}

.main-content {
  flex: 1;
  padding: 16px;
  padding-bottom: 72px;
}

.app-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  text-align: center;
  font-size: 11px;
  color: var(--text-secondary);
  padding: 10px 16px;
  background: var(--card-bg);
  border-top: 1px solid var(--border);
  z-index: 50;
  max-width: 600px;
  margin: 0 auto;
}

.app-footer a {
  color: var(--primary);
  text-decoration: none;
}

.footer-sep {
  margin: 0 4px;
}

.footer-links {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.footer-encryption {
  display: block;
  margin-top: 2px;
  color: var(--success);
  font-weight: 600;
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
