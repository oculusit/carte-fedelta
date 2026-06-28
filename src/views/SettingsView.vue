<template>
  <div class="settings">
    <!-- 1) Sincronizzazione cloud -->
    <div class="card settings-card">
      <h3>Sincronizzazione cloud</h3>
      <p class="section-desc">Configura Supabase per sincronizzare le tue carte su tutti i dispositivi. I dati restano privati.</p>
      <button class="btn btn-outline btn-block" @click="$router.push('/supabase-setup')">
        Configura sincronizzazione
      </button>
    </div>

    <!-- 2) Cache applicazione -->
    <div class="card settings-card">
      <h3>Cache applicazione</h3>
      <p class="section-desc">Cancella la cache senza eliminare le carte salvate localmente.</p>
      <button class="btn btn-warning btn-block" @click="clearCache" :disabled="clearing">
        {{ clearing ? 'Cancellazione...' : 'Cancella cache e ricarica' }}
      </button>
    </div>

    <!-- 3) Informazioni -->
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
import { ref, computed } from 'vue'
import { useAppStore } from '../stores/app.js'
import { isSupabaseConfigured } from '../services/supabase.js'

const store = useAppStore()

const clearing = ref(false)
const syncConfigured = computed(() => isSupabaseConfigured())

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

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.tag-online { color: var(--success); font-weight: 600; }
.tag-offline { color: var(--danger); font-weight: 600; }
</style>
