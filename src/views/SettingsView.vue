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
          <span v-if="syncing" class="sync-spinner">↻</span>
          {{ syncing ? 'Sincronizzazione...' : 'Sincronizza ora' }}
        </button>
        <button class="btn btn-outline btn-block" @click="testWrite" :disabled="testing" style="margin-top:8px">
          {{ testing ? 'Test in corso...' : 'Test scrittura' }}
        </button>
        <p v-if="testResult" :class="testResult.ok ? 'test-ok' : 'test-err'">{{ testResult.msg }}</p>
      </template>
    </div>

    <!-- 2) Server backend loghi -->
    <div class="card settings-card">
      <h3>Server backend</h3>
      <p class="section-desc">Collegati al server che fornisce i loghi personalizzati per i negozi.</p>
      <button class="btn btn-outline btn-block" @click="discoverServer" :disabled="discovering">
        {{ discovering ? 'Ricerca in corso...' : 'Collegati al server backend di default' }}
      </button>
      <p v-if="serverUrl" class="info-row" style="margin-top:8px">
        <span>Server:</span>
        <span class="tag">{{ serverUrl }}</span>
      </p>
      <p v-if="discoverResult" :class="discoverResult.ok ? 'test-ok' : 'test-err'">{{ discoverResult.msg }}</p>
      <details style="margin-top:8px">
        <summary style="font-size:12px;color:var(--text-secondary);cursor:pointer">Inserisci manualmente</summary>
        <div class="input-group" style="margin-top:8px">
          <input v-model="manualUrl" type="url" placeholder="https://mioserver.com/carte" class="input" />
          <button class="btn btn-primary btn-block" @click="saveManualUrl" style="margin-top:8px">Salva</button>
        </div>
      </details>
    </div>

    <!-- 3) Backup locale -->
    <div class="card settings-card">
      <h3>Backup locale</h3>
      <p class="section-desc">Esporta tutte le tue carte in un file JSON che puoi salvare o condividere. Puoi anche importare un backup precedente.</p>
      <div class="backup-row">
        <button class="btn btn-primary btn-block" @click="exportBackup" :disabled="exporting">
          {{ exporting ? 'Esportazione...' : 'Esporta backup JSON' }}
        </button>
        <button class="btn btn-outline btn-block" @click="triggerImport">
          Importa backup
        </button>
        <input v-if="!Capacitor.isNativePlatform()" ref="importInput" type="file" accept=".json" @change="importBackupFromInput" hidden />
      </div>
      <p v-if="backupResult" :class="backupResult.ok ? 'test-ok' : 'test-err'" v-html="backupResult.msg"></p>
      <p class="backup-path" v-if="backupPath">Cartella download: <code>{{ backupPath }}</code></p>
    </div>

    <!-- 4) Cache applicazione -->
    <div class="card settings-card">
      <h3>Cache applicazione</h3>
      <p class="section-desc">Cancella la cache senza eliminare le carte salvate localmente.</p>
      <button class="btn btn-warning btn-block" @click="clearCache" :disabled="clearing">
        {{ clearing ? 'Cancellazione...' : 'Cancella cache e ricarica' }}
      </button>
    </div>

    <!-- 4) Log errori -->
    <div v-if="errorLog.length" class="card settings-card">
      <h3>Log errori</h3>
      <p class="section-desc">Errori registrati durante l'utilizzo dell'app.</p>
      <div v-for="(entry, i) in errorLog" :key="i" class="error-log-entry">
        <div class="error-log-time">{{ formatLogTime(entry.t) }}</div>
        <pre class="error-log-msg">{{ entry.msg }}</pre>
      </div>
      <button class="btn btn-outline btn-block" @click="copyErrorLog" style="margin-top:8px">Copia log</button>
      <button class="btn btn-outline btn-block" @click="clearErrorLog" style="margin-top:4px">Cancella log</button>
    </div>

    <!-- 5) Informazioni -->
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
import { isSupabaseConfigured, getSupabaseClient } from '../services/supabase.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'
import { httpFetch } from '../services/http.js'
import { Capacitor } from '@capacitor/core'
import { saveToDownloads, pickJsonFile } from '../services/filePicker.js'

const store = useAppStore()

const clearing = ref(false)
const syncing = ref(false)
const testing = ref(false)
const testResult = ref(null)
const cloudCount = ref(-1)
const syncConfigured = computed(() => isSupabaseConfigured())
const serverUrl = ref(localStorage.getItem('server_url') || '')
const discovering = ref(false)
const discoverResult = ref(null)
const manualUrl = ref('')
const exporting = ref(false)
const backupResult = ref(null)
const importInput = ref(null)
const backupPath = ref('')
const errorLog = ref([])

function loadErrorLog() {
  try {
    errorLog.value = JSON.parse(localStorage.getItem('error_log') || '[]').reverse()
  } catch { errorLog.value = [] }
}
function formatLogTime(ts) {
  return new Date(ts).toLocaleString('it-IT')
}
function copyErrorLog() {
  var text = errorLog.value.map(function(e) { return '[' + formatLogTime(e.t) + '] ' + e.type + '\n' + e.msg }).join('\n\n---\n\n')
  copyToClipboard(text).then(function() { toast.show('Log copiato', 'success') }).catch(function() { toast.show('Errore copia', 'error') })
}
function clearErrorLog() {
  localStorage.removeItem('error_log')
  errorLog.value = []
  toast.show('Log cancellato', 'success')
}

function saveManualUrl() {
  const val = manualUrl.value.replace(/\/+$/, '')
  if (val) {
    localStorage.setItem('server_url', val)
    serverUrl.value = val
    toast.show('URL server salvato', 'success')
  } else {
    localStorage.removeItem('server_url')
    serverUrl.value = ''
    toast.show('URL rimosso, uso percorso relativo', 'info')
  }
}

async function discoverServer() {
  discovering.value = true
  discoverResult.value = null
  const errors = []
  for (const host of ['https://fidappti.altervista.org', 'https://fidappti.altervista.org/api']) {
    const url = host + '/discover'
    try {
      const res = await httpFetch(url, { timeout: 10000 })
      if (!res.ok) {
        const body = await res.text().catch(() => '')
        errors.push(url + ' → HTTP ' + res.status + ': ' + body.slice(0, 200))
        continue
      }
      const data = await res.json()
      if (data?.server_url) {
        localStorage.setItem('server_url', data.server_url)
        serverUrl.value = data.server_url
        discoverResult.value = { ok: true, msg: 'Server trovato: ' + data.server_url }
        discovering.value = false
        return
      }
      errors.push(url + ' → JSON senza server_url: ' + JSON.stringify(data))
    } catch (e) {
      errors.push(url + ' → ' + (e.name || 'Error') + ': ' + (e.message || e))
    }
  }
  discoverResult.value = { ok: false, msg: 'Server non trovato.\n' + errors.join('\n') }
  discovering.value = false
}

onMounted(async () => {
  loadErrorLog()
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

async function testWrite() {
  testing.value = true
  testResult.value = null
  const sb = getSupabaseClient()
  if (!sb) {
    testResult.value = { ok: false, msg: 'Client Supabase non inizializzato' }
    testing.value = false
    return
  }
  try {
    const { data, error } = await sb.from('cards').insert({
      id: crypto.randomUUID(),
      store_name: '__test__',
      card_number: '0',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }).select()
    if (error) {
      testResult.value = { ok: false, msg: 'ERRORE: ' + error.message + ' (codice: ' + error.code + ')' }
    } else {
      await sb.from('cards').delete().eq('id', data[0].id)
      testResult.value = { ok: true, msg: 'OK: scrittura e cancellazione riuscite' }
      cloudCount.value = await store.getCloudCardCount()
    }
  } catch (e) {
    testResult.value = { ok: false, msg: 'ERRORE: ' + (e.message || e) }
  } finally {
    testing.value = false
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

async function exportBackup() {
  exporting.value = true
  backupResult.value = null
  backupPath.value = ''
  try {
    const allCards = await store.cards.map(c => ({
      id: c.id,
      store_name: c.store_name,
      card_number: c.card_number,
      holder_name: c.holder_name,
      barcode_type: c.barcode_type,
      logo_type: c.logo_type,
      logo_path: c.logo_path,
      logo_data: c.logo_data,
      notes: c.notes,
      color: c.color,
      is_private: c.is_private,
      is_favorite: c.is_favorite,
      created_at: c.created_at,
      updated_at: c.updated_at,
    }))
    const backup = {
      version: '1.1.0',
      exported_at: new Date().toISOString(),
      cards_count: allCards.length,
      cards: allCards,
    }
    const json = JSON.stringify(backup, null, 2)
    const filename = `fidappti-backup-${new Date().toISOString().slice(0,10)}.json`

    if (Capacitor.isNativePlatform()) {
      const result = await saveToDownloads({ filename, data: json })
      backupResult.value = { ok: true, msg: `Backup esportato: ${allCards.length} carte.<br>Percorso: <code>${result.path}</code>` }
    } else {
      const blob = new Blob([json], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url; a.download = filename; a.click()
      URL.revokeObjectURL(url)
      backupResult.value = { ok: true, msg: `Backup esportato: ${allCards.length} carte.` }
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      backupResult.value = { ok: false, msg: `Errore: ${e.message}` }
    }
  } finally {
    exporting.value = false
  }
}

function downloadBlob(blob, filename, count) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  backupResult.value = { ok: true, msg: `Backup esportato: ${count} carte. File scaricato.` }
}

async function triggerImport() {
  if (Capacitor.isNativePlatform()) {
    await importBackupNative()
  } else {
    importInput.value?.click()
  }
}

async function importBackupNative() {
  backupResult.value = null
  try {
    const result = await pickJsonFile()
    console.log('[import] Native pick OK, fileName:', result.fileName, 'content length:', result.content.length)
    const backup = JSON.parse(result.content)
    if (!backup.cards || !Array.isArray(backup.cards)) {
      backupResult.value = { ok: false, msg: 'File non valido: manca l\'array "cards"' }
      return
    }
    const validCards = backup.cards.filter(c => c.id && c.store_name && c.card_number)
    console.log('[import] Valid cards:', validCards.length, 'of', backup.cards.length)
    await store.importCardsFromBackup(validCards)
    console.log('[import] Import OK')
    backupResult.value = { ok: true, msg: `Importate ${validCards.length} carte da backup` }
  } catch (e) {
    if (e.message && e.message.includes('annullata')) return
    console.error('[import] Native ERROR:', e)
    backupResult.value = { ok: false, msg: 'Errore importazione: ' + (e.message || e) }
  }
}

async function importBackupFromInput(e) {
  const file = e.target.files?.[0]
  if (!file) return
  backupResult.value = null
  try {
    console.log('[import] Reading file:', file.name, 'size:', file.size)
    const text = await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result)
      reader.onerror = () => reject(reader.error)
      reader.readAsText(file)
    })
    console.log('[import] File read OK, length:', text.length)
    const backup = JSON.parse(text)
    if (!backup.cards || !Array.isArray(backup.cards)) {
      backupResult.value = { ok: false, msg: 'File non valido: manca l\'array "cards"' }
      return
    }
    const validCards = backup.cards.filter(c => c.id && c.store_name && c.card_number)
    console.log('[import] Valid cards:', validCards.length, 'of', backup.cards.length)
    await store.importCardsFromBackup(validCards)
    console.log('[import] Import OK')
    backupResult.value = { ok: true, msg: `Importate ${validCards.length} carte da backup` }
  } catch (e) {
    console.error('[import] ERROR:', e)
    backupResult.value = { ok: false, msg: 'Errore importazione: ' + (e.message || e) }
  }
  e.target.value = ''
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
.input-group { display: flex; flex-direction: column; gap: 4px; }
.input { padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); }
.test-ok { margin-top: 8px; font-size: 13px; color: var(--success); word-break: break-all; }
.test-err { margin-top: 8px; font-size: 13px; color: var(--danger); word-break: break-all; }
.sync-spinner { display: inline-block; animation: spin 0.8s linear infinite; margin-right: 6px; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.backup-row { display: flex; gap: 8px; }
.backup-row .btn { flex: 1; }
.error-log-entry { margin-bottom: 12px; padding: 10px; background: var(--bg); border-radius: 8px; border-left: 3px solid var(--danger); }
.error-log-time { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
.error-log-msg { font-size: 12px; white-space: pre-wrap; word-break: break-all; margin: 0; max-height: 120px; overflow: auto; }
</style>
