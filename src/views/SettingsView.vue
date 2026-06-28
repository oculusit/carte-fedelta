<template>
  <div class="settings">
    <!-- 1) Sincronizzazione cloud -->
    <div class="card settings-card">
      <h3>Sincronizzazione cloud</h3>
      <p class="section-desc">Configura Supabase per sincronizzare le tue carte su tutti i dispositivi. I dati restano privati.</p>
      <button class="btn btn-outline btn-block" @click="$router.push('/supabase-setup')">
        Configura sincronizzazione
      </button>
      <template v-if="syncConfigured">
        <hr class="divider" />
        <div class="info-row">
          <span>Locale</span>
          <span class="tag">{{ store.cards.length }} carte</span>
        </div>
        <div class="info-row">
          <span>Cloud</span>
          <span :class="cloudCount >= 0 ? 'tag' : 'tag-offline'">
            {{ cloudCount >= 0 ? cloudCount + ' carte' : 'Non disponibile' }}
          </span>
        </div>
        <button class="btn btn-primary btn-block" @click="syncNow" :disabled="syncing" style="margin-top:12px">
          {{ syncing ? 'Sincronizzazione...' : 'Sincronizza ora' }}
        </button>
      </template>
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
        <span>1.1.0</span>
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
import { useAppStore } from '../stores/app.js'
import { isSupabaseConfigured } from '../services/supabase.js'
import { toast } from '../services/toast.js'

const store = useAppStore()

const clearing = ref(false)
const syncing = ref(false)
const cloudCount = ref(-1)
const syncConfigured = computed(() => isSupabaseConfigured())

onMounted(async () => {
  if (syncConfigured.value) {
    cloudCount.value = await store.getCloudCardCount()
  }
})

async function syncNow() {
  syncing.value = true
  try {
    await store.pullFromServer()
    cloudCount.value = await store.getCloudCardCount()
    const local = store.cards.length
    const cloud = cloudCount.value
    if (local === cloud) {
      toast.show(`Sincronizzato: ${local} carte (cloud + locale uguali)`, 'success')
    } else {
      toast.show(`Locale: ${local} · Cloud: ${cloud >= 0 ? cloud : '?'}`, 'info')
    }
  } catch (e) {
    toast.show('Errore sincronizzazione: ' + (e.message || e), 'error')
  } finally {
    syncing.value = false
  }
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
