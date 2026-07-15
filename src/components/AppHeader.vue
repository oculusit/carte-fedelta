<template>
  <header class="app-header">
    <div class="header-inner">
      <a class="header-logo" href="#" @click.prevent="$router.push('/')" title="Home">
        <img v-if="logoOk" :src="logoSrc" alt="FidAPPti" class="header-logo-img" @error="logoOk = false" />
        <svg v-else class="header-logo-svg" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2"/></svg>
      </a>
      <button class="header-back" v-if="showBack" @click="goBack">
        <svg class="back-chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <h1 class="header-title">{{ title }}</h1>
      <div class="header-actions">
        <span v-if="syncConfigured" class="sync-indicator" :class="{ syncing: store.syncing }" :title="syncTitle">
          <span v-if="store.syncing" class="sync-spinner">↻</span>
          <span v-else>{{ syncLabel }}</span>
        </span>
        <button class="header-btn" @click="clearAndReload" title="Ricarica" :disabled="reloading">
          <span class="icon header-reload-icon" :class="{ spinning: reloading }">↻</span>
        </button>
        <button class="header-btn" @click="$router.push('/settings')" title="Impostazioni">
          <span class="icon">⚙</span>
        </button>
      </div>
    </div>
    <div v-if="!store.isOnline" class="offline-banner">
      <span>◉ Offline — le modifiche verranno sincronizzate automaticamente</span>
    </div>
  </header>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'
import { isSupabaseConfigured } from '../services/supabase.js'

const route = useRoute()
const router = useRouter()
const store = useAppStore()

const logoOk = ref(true)
const logoSrc = './fidappti-logo.png'
const reloading = ref(false)

async function clearAndReload() {
  reloading.value = true
  try {
    const keys = await caches.keys()
    await Promise.all(keys.map(k => caches.delete(k)))
    const regs = await navigator.serviceWorker.getRegistrations()
    await Promise.all(regs.map(r => r.unregister()))
  } catch (e) {
    console.warn('Cache clear error:', e)
  }
  window.location.reload()
}

const title = computed(() => {
  if (route.name === 'dashboard') return store.appName || 'FidAPPti'
  const specific = {
    'card-new': 'Nuova Carta',
    'card-detail': 'Dettaglio Carta',
    'card-edit': 'Modifica Carta',
    settings: 'Impostazioni',
  }
  return specific[route.name] || store.appName || 'FidAPPti'
})

const showBack = computed(() => {
  return route.name !== 'dashboard'
})

const syncConfigured = computed(() => isSupabaseConfigured())
const syncLabel = computed(() => {
  if (!store.isOnline) return 'offline'
  return 'cloud'
})
const syncTitle = computed(() => {
  if (!store.isOnline) return 'Offline — le modifiche verranno sincronizzate quando torni online'
  if (!syncConfigured.value) return 'Sincronizzazione non configurata'
  return 'Sincronizzazione attiva'
})

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/')
  }
}
</script>

<style scoped>
.app-header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: var(--primary);
  color: white;
  padding: 0 16px;
  height: var(--header-height);
  display: flex;
  align-items: center;
}

.header-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  max-width: 600px;
  margin: 0 auto;
}

.header-back {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.back-chevron {
  flex-shrink: 0;
}

.header-title {
  font-size: 18px;
  font-weight: 600;
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
  line-height: 0;
  flex-shrink: 0;
}

.header-logo-img,
.header-logo-svg {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  object-fit: contain;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}

.header-btn {
  background: none;
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  opacity: 0.9;
}

.icon {
  display: inline-flex;
}

.sync-indicator {
  font-size: 11px;
  background: rgba(255,255,255,0.2);
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
}
.sync-indicator.syncing {
  background: rgba(255,255,255,0.35);
}
.sync-spinner {
  display: inline-block;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.header-reload-icon {
  display: inline-block;
}

.header-reload-icon.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.offline-banner {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--danger);
  color: white;
  text-align: center;
  font-size: 12px;
  padding: 4px 12px;
  z-index: 99;
}
</style>
