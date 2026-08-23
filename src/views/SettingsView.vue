<template>
  <div class="settings">
    <!-- 0) Importa le tessere da altre App -->
    <div class="card settings-card">
      <div class="settings-header">
        <svg class="settings-header-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" fill="#28a745"/></svg>
        <h3>Importa le tessere da altre App</h3>
      </div>
      <p class="section-desc"><strong>FidAPPti</strong> ti permette di importare i dati delle tue tessere da applicazioni precedentemente utilizzate in modo semplice ed intuitivo.</p>
      <button class="btn btn-primary btn-block" @click="showImportInfo = true">
        Scopri come fare!
      </button>
    </div>

    <!-- 1) Backup Tessere Fedeltà -->
    <div class="card settings-card">
      <div class="settings-header">
        <svg class="settings-header-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zM18 6h-5c-1.1 0-2 .9-2 2v2.28c-.6.35-1 .98-1 1.72 0 1.1.9 2 2 2s2-.9 2-2c0-.74-.4-1.38-1-1.72V8h3v8H8V8h2V6H6v12h12V6z"/></svg>
        <h3>Backup Tessere Fedeltà</h3>
      </div>
      <p class="section-desc">Crea una copia del tuo archivio tessere fedeltà o ripristina una copia esistente (ad esempio se hai cambiato telefono o se vuoi condividere le tue tessere con un familiare).</p>
      <div class="backup-row">
        <button class="btn btn-primary btn-block" @click="exportCardsBackup" :disabled="exportingCards">
          {{ exportingCards ? 'Esportazione...' : 'Esporta' }}
        </button>
        <button class="btn btn-outline btn-block" @click="triggerImport('cards')">
          Importa
        </button>
        <input v-if="!Capacitor.isNativePlatform()" ref="importCardsInput" type="file" accept=".json" @change="importBackupFromInput($event, 'cards')" hidden />
      </div>
      <p v-if="cardsBackupResult" :class="cardsBackupResult.ok ? 'test-ok clickable' : 'test-err'" v-html="cardsBackupResult.msg" @click="cardsBackupResult?.ok && openBackupFolder()"></p>
    </div>

    <!-- 2) Backup Biglietti da Visita -->
    <div class="card settings-card">
      <div class="settings-header">
        <svg class="settings-header-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <h3>Backup Biglietti da Visita</h3>
      </div>
      <p class="section-desc">Crea una copia dei tuoi Biglietti da Visita o ripristina una copia esistente (ad esempio se hai cambiato telefono o se vuoi averne una copia anche sul telefono di lavoro o personale).</p>
      <div class="backup-row">
        <button class="btn btn-primary btn-block" @click="exportBcBackup" :disabled="exportingBc">
          {{ exportingBc ? 'Esportazione...' : 'Esporta' }}
        </button>
        <button class="btn btn-outline btn-block" @click="triggerImport('bc')">
          Importa
        </button>
        <input v-if="!Capacitor.isNativePlatform()" ref="importBcInput" type="file" accept=".json" @change="importBackupFromInput($event, 'bc')" hidden />
      </div>
      <p v-if="bcBackupResult" :class="bcBackupResult.ok ? 'test-ok clickable' : 'test-err'" v-html="bcBackupResult.msg" @click="bcBackupResult?.ok && openBackupFolder()"></p>
    </div>

    <!-- 3) Personalizza condivisione .vcf -->
    <div class="card settings-card">
      <div class="settings-header">
        <svg class="settings-header-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
        <h3>Personalizza condivisione Biglietti da Visita</h3>
      </div>
      <p class="section-desc">Imposta un messaggio personalizzato che verrà aggiunto al tuo biglietto da visita virtuale durante una condivisione. Puoi usare i segnaposto <code>&lt;cognome&gt;</code>, <code>&lt;nome&gt;</code> e <code>&lt;azienda&gt;</code> all'interno del messaggio e verranno sostituiti con i dati di contatto reali presenti nel biglietto da visita. Se lasci vuoto questo spazio, verrà utilizzato un messaggio predefinito.</p>
      <textarea v-model="vcfShareMessage" class="vcf-msg-input" rows="4" maxlength="500" :placeholder="vcfShareDefault"></textarea>
      <div class="backup-row" style="margin-top:8px">
        <button class="btn btn-primary btn-block" @click="saveVcfShareMessage" :disabled="savingVcfMessage">
          {{ savingVcfMessage ? 'Salvataggio...' : 'Salva' }}
        </button>
        <button class="btn btn-outline btn-block" @click="resetVcfShareMessage" :disabled="savingVcfMessage || !vcfShareMessage.trim()">
          Ripristina predefinito
        </button>
      </div>
      <p v-if="vcfShareSaved" class="test-ok">{{ vcfShareSaved }}</p>
    </div>

    <!-- 4) Server backend -->
    <div class="card settings-card">
      <h3>Server backend</h3>
      <p class="section-desc">Collegati al server che fornisce i loghi personalizzati per i negozi.</p>
      <button class="btn btn-outline btn-block" @click="discoverServer" :disabled="discovering">
        {{ discovering ? 'Ricerca in corso...' : 'Collegati al server backend di default' }}
      </button>
      <button class="btn btn-outline btn-block" @click="refreshLogos" :disabled="refreshingLogos" style="margin-top:8px">
        {{ refreshingLogos ? 'Aggiornamento in corso...' : 'Aggiorna loghi dai negozi' }}
      </button>
      <p v-if="refreshLogosResult" :class="refreshLogosResult.ok ? 'test-ok' : 'test-err'">{{ refreshLogosResult.msg }}</p>
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

    <!-- 3) Sincronizzazione cloud -->
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

    <!-- 4) Cache applicazione -->
    <div class="card settings-card">
      <h3>Cache applicazione</h3>
      <p class="section-desc">Cancella la cache senza eliminare le carte salvate localmente.</p>
      <button class="btn btn-warning btn-block" @click="clearCache" :disabled="clearing">
        {{ clearing ? 'Cancellazione...' : 'Cancella cache e ricarica' }}
      </button>
    </div>

    <!-- 5) Test fotocamera -->
    <div class="card settings-card">
      <h3>Test fotocamera</h3>
      <p class="section-desc">Elenca tutte le fotocamere del dispositivo per identificare quella posteriore.</p>
      <button class="btn btn-outline btn-block" @click="enumerateCameras" :disabled="cameraTesting">
        {{ cameraTesting ? 'Ricerca...' : 'Elenca fotocamere' }}
      </button>
      <div v-if="cameraList.length" class="camera-list" style="margin-top:12px">
        <div v-for="(cam, i) in cameraList" :key="cam.id" class="camera-item" :class="{ 'camera-active': cam.id === savedCameraId }">
          <div class="camera-info">
            <strong>{{ cam.label || 'Camera ' + (i + 1) }}</strong>
            <span class="camera-id">ID: {{ cam.id }}</span>
          </div>
          <div class="camera-actions">
            <button class="btn btn-outline btn-sm" @click="testCamera(cam.id, i)">
              {{ testingCameraIndex === i ? 'Apertura...' : 'Testa' }}
            </button>
            <button v-if="cam.id !== savedCameraId" class="btn btn-primary btn-sm" @click="setPreferredCamera(cam.id)">
              Usa questa
            </button>
            <span v-else class="tag-online">Attiva</span>
          </div>
          <video v-if="testingCameraIndex === i" :ref="el => { if (el) testVideoEl = el }" autoplay playsinline muted style="width:100%;border-radius:8px;margin-top:8px;max-height:200px;object-fit:cover"></video>
        </div>
      </div>
      <p v-if="savedCameraId" class="test-ok" style="margin-top:8px">Camera selezionata: ID {{ savedCameraId }}</p>
    </div>

    <!-- 6) Log errori -->
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

    <!-- 7) Informazioni -->
    <div class="card settings-card">
      <h3>Informazioni</h3>
      <div class="info-row">
        <span>Versione</span>
        <span>1.3.1</span>
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

    <!-- Import guide popup -->
    <Teleport to="body">
      <div v-if="showImportInfo" class="ig-overlay" @click.self="showImportInfo = false">
        <div class="ig-popup">
          <div class="ig-icon">
            <svg viewBox="0 0 24 24" width="44" height="44">
              <circle cx="12" cy="12" r="12" fill="#28a745"/>
              <text x="12" y="17" text-anchor="middle" fill="#fff" font-size="16" font-weight="700" font-family="sans-serif">i</text>
            </svg>
          </div>
          <h3 class="ig-title">Come importare le tessere da altre applicazioni</h3>
          <div class="ig-text ig-scroll">
            <p>Per importare le tessere che utilizzavi su altre applicazioni devi procedere in questo modo:</p>
            <ol>
              <li>Apri la vecchia applicazione e fai uno screenshot dei codici a barre delle tessere che vuoi importare in <strong>FidAPPti</strong>;</li>
              <li>In <strong>FidAPPti</strong> tocca l'icona rotonda in basso a destra con il simbolo "+" ;</li>
              <li>Inserisci il nome del negozio che vuoi importare. Se esiste un logo ad esso associato verrà mostrato subito;</li>
              <li>Tocca l'icona del codice a barre;</li>
              <li>Tocca il tasto "Importa da altra App". Se non fosse visibile fai prima un tap su "Ferma fotocamera";</li>
              <li>Seleziona l'immagine relativa alla tessera da importare;</li>
              <li>Inserisci eventuali dati aggiuntivi (nome del titolare della tessera o altre informazioni non sensibili);</li>
              <li>Salva.</li>
            </ol>
          </div>
          <button class="ig-btn" @click="showImportInfo = false">Ho capito!</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAppStore } from '../stores/app.js'
import { useBusinessCardsStore } from '../stores/businessCards.js'
import { isSupabaseConfigured, getSupabaseClient } from '../services/supabase.js'
import { settingsDb } from '../services/db.js'
import { VCF_SHARE_DEFAULT_MESSAGE, VCF_SHARE_MESSAGE_KEY } from '../services/vcard.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'
import { httpFetch } from '../services/http.js'
import { Capacitor } from '@capacitor/core'
import { saveToDownloads, pickJsonFile, openDownloadsFolder, shareFile } from '../services/filePicker.js'

const store = useAppStore()
const bcStore = useBusinessCardsStore()

const clearing = ref(false)
const syncing = ref(false)
const testing = ref(false)
const testResult = ref(null)
const cloudCount = ref(-1)
const syncConfigured = computed(() => isSupabaseConfigured())
const serverUrl = ref(localStorage.getItem('server_url') || '')
const discovering = ref(false)
const discoverResult = ref(null)
const refreshingLogos = ref(false)
const refreshLogosResult = ref(null)
const manualUrl = ref('')
const exportingCards = ref(false)
const cardsBackupResult = ref(null)
const importCardsInput = ref(null)
const exportingBc = ref(false)
const bcBackupResult = ref(null)
const importBcInput = ref(null)
const errorLog = ref([])
const vcfShareMessage = ref('')
const vcfShareDefault = VCF_SHARE_DEFAULT_MESSAGE.replace('{nome}', '<cognome> <nome>')
const savingVcfMessage = ref(false)
const vcfShareSaved = ref('')
const showImportInfo = ref(false)

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

async function saveVcfShareMessage() {
  savingVcfMessage.value = true
  vcfShareSaved.value = ''
  try {
    await settingsDb.set(VCF_SHARE_MESSAGE_KEY, vcfShareMessage.value.trim())
    vcfShareMessage.value = vcfShareMessage.value.trim()
    vcfShareSaved.value = 'Messaggio salvato'
    toast.show('Messaggio salvato', 'success')
  } catch (e) {
    toast.show('Errore salvataggio: ' + (e.message || e), 'error')
  } finally {
    savingVcfMessage.value = false
  }
}

async function resetVcfShareMessage() {
  savingVcfMessage.value = true
  vcfShareSaved.value = ''
  try {
    await settingsDb.set(VCF_SHARE_MESSAGE_KEY, '')
    vcfShareMessage.value = ''
    vcfShareSaved.value = 'Messaggio predefinito ripristinato'
    toast.show('Messaggio predefinito ripristinato', 'success')
  } catch (e) {
    toast.show('Errore: ' + (e.message || e), 'error')
  } finally {
    savingVcfMessage.value = false
  }
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

async function refreshLogos() {
  refreshingLogos.value = true
  refreshLogosResult.value = null
  try {
    await store.updateCardsLogosFromServer(true)
    await store.loadCards()
    refreshLogosResult.value = { ok: true, msg: 'Controllo loghi completato. Le carte con un nuovo logo disponibile sono state aggiornate.' }
  } catch (e) {
    refreshLogosResult.value = { ok: false, msg: 'Errore: ' + (e.message || e) }
  } finally {
    refreshingLogos.value = false
  }
}

onMounted(async () => {
  loadErrorLog()
  if (syncConfigured.value) {
    cloudCount.value = await store.getCloudCardCount()
  }
  try {
    vcfShareMessage.value = (await settingsDb.get(VCF_SHARE_MESSAGE_KEY)) || ''
  } catch {}
})

async function syncNow() {
  syncing.value = true
  try {
    await store.pullFromServer()
    await bcStore.syncMerge()
    cloudCount.value = await store.getCloudCardCount()
    const local = store.cards.length
    const cloud = cloudCount.value
    if (local === cloud) {
      toast.show(`Sincronizzato: ${local} carte + ${bcStore.cards.length} biglietti`, 'success')
    } else {
      toast.show(`Locale: ${local} carte + ${bcStore.cards.length} biglietti · Cloud: ${cloud >= 0 ? cloud : '?'}`, 'info')
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

function cardToBackup(c) {
  return {
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
  }
}

function bcToBackup(c) {
  return {
    id: c.id,
    first_name: c.first_name,
    last_name: c.last_name,
    org: c.org,
    role: c.role,
    phone_personal: c.phone_personal,
    phone_business: c.phone_business,
    email: c.email,
    website: c.website,
    address: c.address,
    city: c.city,
    postal_code: c.postal_code,
    country: c.country,
    notes: c.notes,
    color: c.color,
    avatar_data: c.avatar_data,
    is_favorite: c.is_favorite,
    created_at: c.created_at,
    updated_at: c.updated_at,
  }
}

async function exportBackupData(kind) {
  const isBc = kind === 'bc'
  const allCards = isBc ? [] : store.cards.map(cardToBackup)
  const allBc = isBc ? bcStore.cards.map(bcToBackup) : []
  const backup = {
    version: '1.3.1',
    exported_at: new Date().toISOString(),
    cards_count: allCards.length,
    cards: allCards,
    business_cards_count: allBc.length,
    business_cards: allBc,
  }
  const json = JSON.stringify(backup, null, 2)
  const filename = `${isBc ? 'biglietti' : 'tessere'}-fidappti-backup-${new Date().toISOString().slice(0,10)}.json`

  if (Capacitor.isNativePlatform()) {
    await saveToDownloads({ filename, data: json })
    return {
      ok: true,
      msg: `Backup esportato: ${isBc ? allBc.length + ' biglietti' : allCards.length + ' tessere'}. <span class="clickable-hint">Tocca per aprire la cartella</span>`,
    }
  } else {
    const blob = new Blob([json], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = filename; a.click()
    URL.revokeObjectURL(url)
    return {
      ok: true,
      msg: `Backup esportato: ${isBc ? allBc.length + ' biglietti' : allCards.length + ' tessere'}.`,
    }
  }
}

async function exportCardsBackup() {
  exportingCards.value = true
  cardsBackupResult.value = null
  try {
    cardsBackupResult.value = await exportBackupData('cards')
    try {
      await shareFile({
        filename: `tessere-fidappti-backup-${new Date().toISOString().slice(0,10)}.json`,
        data: JSON.stringify({
          version: '1.3.1',
          exported_at: new Date().toISOString(),
          cards_count: store.cards.length,
          cards: store.cards.map(cardToBackup),
          business_cards_count: 0,
          business_cards: [],
        }, null, 2),
        title: 'Backup FidAPPti',
        text: `Backup con ${store.cards.length} tessere fedeltà`,
      })
    } catch (shareErr) {
      console.warn('Share failed:', shareErr)
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      cardsBackupResult.value = { ok: false, msg: `Errore: ${e.message}` }
    }
  } finally {
    exportingCards.value = false
  }
}

async function exportBcBackup() {
  exportingBc.value = true
  bcBackupResult.value = null
  try {
    bcBackupResult.value = await exportBackupData('bc')
    try {
      await shareFile({
        filename: `biglietti-fidappti-backup-${new Date().toISOString().slice(0,10)}.json`,
        data: JSON.stringify({
          version: '1.3.1',
          exported_at: new Date().toISOString(),
          cards_count: 0,
          cards: [],
          business_cards_count: bcStore.cards.length,
          business_cards: bcStore.cards.map(bcToBackup),
        }, null, 2),
        title: 'Backup FidAPPti',
        text: `Backup con ${bcStore.cards.length} biglietti da visita`,
      })
    } catch (shareErr) {
      console.warn('Share failed:', shareErr)
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      bcBackupResult.value = { ok: false, msg: `Errore: ${e.message}` }
    }
  } finally {
    exportingBc.value = false
  }
}

async function openBackupFolder() {
  if (!Capacitor.isNativePlatform()) return
  try {
    await openDownloadsFolder()
  } catch {
    toast.show('Impossibile aprire la cartella', 'error')
  }
}

async function triggerImport(kind) {
  if (Capacitor.isNativePlatform()) {
    await importBackupNative(kind)
  } else {
    const input = kind === 'bc' ? importBcInput.value : importCardsInput.value
    input?.click()
  }
}

async function importBackupNative(kind) {
  const isBc = kind === 'bc'
  const resultRef = isBc ? bcBackupResult : cardsBackupResult
  resultRef.value = null
  try {
    const result = await pickJsonFile()
    console.log('[import] Native pick OK, fileName:', result.fileName, 'content length:', result.content.length)
    const backup = JSON.parse(result.content)
    const list = isBc ? backup.business_cards : backup.cards
    if (!list || !Array.isArray(list)) {
      resultRef.value = { ok: false, msg: `File non valido: manca l'array "${isBc ? 'business_cards' : 'cards'}"` }
      return
    }
    const valid = isBc
      ? list.filter(c => c.id && (c.first_name || c.last_name || c.email || c.phone_personal))
      : list.filter(c => c.store_name && c.card_number)
    console.log('[import] Valid items:', valid.length, 'of', list.length)
    const res = isBc
      ? await bcStore.importCardsFromBackup(valid)
      : await store.importCardsFromBackup(valid)
    console.log('[import] Import result:', res)
    resultRef.value = isBc
      ? { ok: true, msg: `Importazione completata: <strong>${res.added}</strong> biglietti importati, <strong>${res.updated}</strong> aggiornati` }
      : { ok: true, msg: `Importazione completata: <strong>${res.added}</strong> aggiunte, <strong>${res.updated}</strong> aggiornate, <strong>${res.skipped}</strong> scartate` }
  } catch (e) {
    if (e.message && e.message.includes('annullata')) return
    console.error('[import] Native ERROR:', e)
    resultRef.value = { ok: false, msg: 'Errore importazione: ' + (e.message || e) }
  }
}

async function importBackupFromInput(e, kind) {
  const file = e.target.files?.[0]
  if (!file) return
  const isBc = kind === 'bc'
  const resultRef = isBc ? bcBackupResult : cardsBackupResult
  resultRef.value = null
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
    const list = isBc ? backup.business_cards : backup.cards
    if (!list || !Array.isArray(list)) {
      resultRef.value = { ok: false, msg: `File non valido: manca l'array "${isBc ? 'business_cards' : 'cards'}"` }
      return
    }
    const valid = isBc
      ? list.filter(c => c.id && (c.first_name || c.last_name || c.email || c.phone_personal))
      : list.filter(c => c.store_name && c.card_number)
    console.log('[import] Valid items:', valid.length, 'of', list.length)
    const res = isBc
      ? await bcStore.importCardsFromBackup(valid)
      : await store.importCardsFromBackup(valid)
    console.log('[import] Import result:', res)
    resultRef.value = isBc
      ? { ok: true, msg: `Importazione completata: <strong>${res.added}</strong> biglietti importati, <strong>${res.updated}</strong> aggiornati` }
      : { ok: true, msg: `Importazione completata: <strong>${res.added}</strong> aggiunte, <strong>${res.updated}</strong> aggiornate, <strong>${res.skipped}</strong> scartate` }
  } catch (e) {
    console.error('[import] ERROR:', e)
    resultRef.value = { ok: false, msg: 'Errore importazione: ' + (e.message || e) }
  }
  e.target.value = ''
}

const cameraTesting = ref(false)
const cameraList = ref([])
const testingCameraIndex = ref(-1)
const savedCameraId = ref(localStorage.getItem('preferred_camera_id') || '')
const testVideoEl = ref(null)
let testStream = null

async function enumerateCameras() {
  cameraTesting.value = true
  cameraList.value = []
  try {
    const { Html5Qrcode } = await import('html5-qrcode')
    const cams = await Html5Qrcode.getCameras()
    cameraList.value = cams || []
    if (!cams || cams.length === 0) {
      toast.show('Nessuna fotocamera trovata', 'error')
    }
  } catch (e) {
    toast.show('Errore enumerazione: ' + (e.message || e), 'error')
  } finally {
    cameraTesting.value = false
  }
}

async function testCamera(cameraId, index) {
  stopTestCamera()
  testingCameraIndex.value = index
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { deviceId: { exact: cameraId }, width: { ideal: 640 }, height: { ideal: 480 } }
    })
    testStream = stream
    await new Promise(r => setTimeout(r, 100))
    if (testVideoEl.value) {
      testVideoEl.value.srcObject = stream
    }
    setTimeout(stopTestCamera, 4000)
  } catch (e) {
    toast.show('Errore apertura camera: ' + (e.message || e), 'error')
    testingCameraIndex.value = -1
  }
}

function stopTestCamera() {
  if (testStream) {
    testStream.getTracks().forEach(t => t.stop())
    testStream = null
  }
  testingCameraIndex.value = -1
}

function setPreferredCamera(cameraId) {
  localStorage.setItem('preferred_camera_id', cameraId)
  savedCameraId.value = cameraId
  toast.show('Camera salvata come preferita', 'success')
  stopTestCamera()
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
}

.settings-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.settings-header-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  fill: #b8860b;
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
.vcf-msg-input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); resize: vertical; box-sizing: border-box; line-height: 1.4; }
.test-ok { margin-top: 8px; font-size: 13px; color: var(--success); word-break: break-all; }
.test-ok.clickable { cursor: pointer; text-decoration: underline; }
.clickable-hint { font-size: 11px; color: var(--text-secondary); }
.test-err { margin-top: 8px; font-size: 13px; color: var(--danger); word-break: break-all; }
.sync-spinner { display: inline-block; animation: spin 0.8s linear infinite; margin-right: 6px; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.backup-row { display: flex; gap: 8px; }
.backup-row .btn { flex: 1; }
.error-log-entry { margin-bottom: 12px; padding: 10px; background: var(--bg); border-radius: 8px; border-left: 3px solid var(--danger); }
.error-log-time { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
.error-log-msg { font-size: 12px; white-space: pre-wrap; word-break: break-all; margin: 0; max-height: 120px; overflow: auto; }
.camera-item { padding: 10px; background: var(--bg); border-radius: 8px; margin-bottom: 8px; border-left: 3px solid var(--border); }
.camera-item.camera-active { border-left-color: var(--success); }
.camera-info { display: flex; flex-direction: column; gap: 2px; }
.camera-id { font-size: 11px; color: var(--text-secondary); font-family: monospace; }
.camera-actions { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
.btn-sm { padding: 4px 12px; font-size: 12px; }
.ig-overlay {
  position: fixed;
  inset: 0;
  z-index: 10001;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.ig-popup {
  background: #fff;
  border-radius: 16px;
  padding: 28px 24px 24px;
  max-width: 400px;
  width: 100%;
  text-align: center;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
  max-height: 85vh;
  display: flex;
  flex-direction: column;
}
.ig-icon { margin-bottom: 12px; flex-shrink: 0; }
.ig-title {
  font-size: 17px;
  font-weight: 700;
  color: #333;
  margin-bottom: 12px;
  flex-shrink: 0;
}
.ig-scroll {
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  text-align: left;
  flex: 1;
  min-height: 0;
}
.ig-scroll p {
  font-size: 14px;
  color: #555;
  line-height: 1.55;
  margin-bottom: 10px;
}
.ig-scroll ol {
  font-size: 14px;
  color: #555;
  line-height: 1.6;
  padding-left: 20px;
  margin: 0;
}
.ig-scroll ol li {
  margin-bottom: 8px;
}
.ig-btn {
  display: block;
  width: 100%;
  padding: 14px;
  background: #28a745;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;
  margin-top: 16px;
}
.ig-btn:active { background: #218838; }
</style>
