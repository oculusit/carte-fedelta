<template>
  <div class="detail">
    <div v-if="loading" class="loading-state">Caricamento...</div>

    <div v-else-if="!card" class="empty-state">
      <h3>Biglietto non trovato</h3>
      <button class="btn btn-primary" style="margin-top:12px" @click="$router.push({ path: '/', query: { t: 'business' } })">Torna alla lista</button>
    </div>

    <template v-else>
      <div class="card-preview" :style="{ borderLeftColor: card.color || '#1a73e8' }">
        <div class="avatar-lg" :style="{ background: card.color || '#1a73e8' }">
          <img v-if="card.avatar_data" :src="card.avatar_data" :alt="fullName" class="avatar-lg-img" />
          <span v-else class="avatar-lg-initials">{{ initials }}</span>
        </div>
        <div class="card-preview-info">
          <h2>{{ fullName }}</h2>
          <p v-if="card.org" class="holder">{{ card.org }}</p>
          <p v-if="card.role" class="holder role">{{ card.role }}</p>
        </div>
      </div>

      <div class="detail-section">
        <h3 class="section-label">QR Code vCard</h3>
        <BarcodeDisplay :code="vcardQrText" type="QR" />
        <p class="barcode-hint">Chi scansiona il QR può salvare il tuo biglietto direttamente nei propri contatti.</p>
      </div>

      <div class="detail-section card-data">
        <div class="data-row" v-if="card.phone_personal">
          <span class="data-label">Telefono personale</span>
          <span class="data-value" @click="copy(card.phone_personal)">{{ card.phone_personal }}</span>
        </div>
        <div class="data-row" v-if="card.phone_business">
          <span class="data-label">Telefono business</span>
          <span class="data-value" @click="copy(card.phone_business)">{{ card.phone_business }}</span>
        </div>
        <div class="data-row" v-if="card.email">
          <span class="data-label">E-mail</span>
          <span class="data-value" @click="copy(card.email)">{{ card.email }}</span>
        </div>
        <div class="data-row" v-if="card.website">
          <span class="data-label">Sito web</span>
          <span class="data-value" @click="openWebsite">{{ card.website }}</span>
        </div>
        <div class="data-row" v-if="addressText">
          <span class="data-label">Indirizzo</span>
          <span class="data-value">{{ addressText }}</span>
        </div>
        <div class="data-row" v-if="card.notes">
          <span class="data-label">Note</span>
          <span class="data-value">{{ card.notes }}</span>
        </div>
      </div>

      <div class="detail-actions">
        <button class="btn btn-primary btn-block" @click="shareVCard" :disabled="sharing">
          {{ sharing ? 'Condivisione...' : 'Condividi biglietto (.vcf)' }}
        </button>
        <button
          v-if="nfcVisible"
          class="btn btn-primary btn-block"
          @click="toggleNfcShare"
          :disabled="nfcShareBusy"
        >
          {{ nfcShareActive ? '⏹ Ferma condivisione NFC' : '📶 Condividi via NFC' }}
        </button>
        <p v-if="nfcShareActive" class="barcode-hint">
          Appoggia l'altro smartphone (NFC attivo, schermo acceso): riceverà il biglietto e
          proporrà di salvarlo nei contatti. L'avviso "Nuovo tag raccolto / Tag vuoto" su
          <strong>questo</strong> telefono è normale: sta solo leggendo l'altro telefono, non blocca l'invio.
          <template v-if="nfcShareSize"> — biglietto pronto ({{ nfcShareSize }} byte)</template>
        </p>
        <button
          v-if="nfcVisible"
          class="btn btn-outline btn-block"
          @click="writeNfc"
          :disabled="nfcBusy || nfcShareActive"
        >
          {{ nfcLabel }}
        </button>
        <div class="row-buttons">
          <button class="btn-icon" title="Modifica" @click="$router.push(`/business-card/${card.id}/edit`)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn-icon btn-icon-danger" title="Elimina" @click="confirmDelete">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
          </button>
        </div>
      </div>

      <div v-if="nfcError" class="nfc-error">{{ nfcError }}</div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBusinessCardsStore } from '../stores/businessCards.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'
import { buildVCard } from '../services/vcard.js'
import { isNfcNativeAvailable, isNfcSupported, writeVCardToTag, startNfcShare, stopNfcShare } from '../services/nfc.js'
import { Capacitor } from '@capacitor/core'
import { saveToDownloads, shareFile } from '../services/filePicker.js'
import BarcodeDisplay from '../components/BarcodeDisplay.vue'

const route = useRoute()
const router = useRouter()
const bcStore = useBusinessCardsStore()

const card = ref(null)
const loading = ref(true)
const sharing = ref(false)
const nfcBusy = ref(false)
const nfcError = ref('')
const nfcVisible = ref(false)
const nfcShareActive = ref(false)
const nfcShareBusy = ref(false)
const nfcShareSize = ref(0)

const fullName = computed(() => {
  const first = (card.value?.first_name || '').trim()
  const last = (card.value?.last_name || '').trim()
  if (first && last) return `${first} ${last}`
  return first || last || 'Biglietto'
})

const initials = computed(() => {
  const first = (card.value?.first_name || '').trim().charAt(0)
  const last = (card.value?.last_name || '').trim().charAt(0)
  return (first + last || '?').toUpperCase()
})

const vcardText = computed(() => (card.value ? buildVCard(card.value) : ''))
const vcardQrText = computed(() => (card.value ? buildVCard(card.value, '\n') : ''))

const addressText = computed(() => {
  if (!card.value) return ''
  return [card.value.address, card.value.city, card.value.postal_code, card.value.country]
    .map((x) => (x || '').trim())
    .filter(Boolean)
    .join(', ')
})

const nfcLabel = computed(() => {
  if (!nfcBusy.value) return '📶 Scrivi su tag NFC'
  return nfcStatusMsg.value || 'Appoggia il tag NFC...'
})

const nfcStatusMsg = ref('')

async function loadCard() {
  loading.value = true
  card.value = await bcStore.getCard(route.params.id)
  loading.value = false
}

function copy(text) {
  if (!text) return
  copyToClipboard(text).then(() => toast.show('Copiato negli appunti', 'success')).catch(() => toast.show('Errore copia', 'error'))
}

function openWebsite() {
  const url = card.value?.website || ''
  if (!url) return
  window.open(/^https?:\/\//i.test(url) ? url : 'https://' + url, '_blank', 'noopener')
}

async function shareVCard() {
  sharing.value = true
  nfcError.value = ''
  try {
    const filename = `vcard-${(card.value.last_name || 'contatto').toLowerCase().replace(/\s+/g, '')}.vcf`
    if (Capacitor.isNativePlatform()) {
      await shareFile({ filename, data: vcardText.value, title: fullName.value, text: `Biglietto da visita di ${fullName.value}`, mimeType: 'text/vcard' })
    } else if (navigator.share && navigator.canShare?.({ files: [new File([vcardText.value], filename, { type: 'text/vcard' })] })) {
      await navigator.share({ files: [new File([vcardText.value], filename, { type: 'text/vcard' })], title: fullName.value, text: `Biglietto da visita di ${fullName.value}` })
    } else {
      const blob = new Blob([vcardText.value], { type: 'text/vcard' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      a.click()
      URL.revokeObjectURL(url)
      toast.show('File .vcf scaricato', 'success')
    }
  } catch (e) {
    if (e?.message !== 'Share canceled' && !String(e?.message || '').includes('annullata')) {
      toast.show('Errore condivisione: ' + (e.message || e), 'error')
    }
  } finally {
    sharing.value = false
  }
}

async function writeNfc() {
  nfcError.value = ''
  nfcBusy.value = true
  nfcStatusMsg.value = ''
  try {
    await writeVCardToTag(vcardText.value, (stage) => {
      const map = {
        'appoggia': 'Appoggia il tag NFC da scrivere...',
        'trovato': 'Tag trovato, scrittura...',
        'scritto': 'Scrittura completata!',
      }
      nfcStatusMsg.value = map[stage] || stage
    })
    toast.show('Biglietto scritto sul tag NFC', 'success')
  } catch (e) {
    const msg = e?.message || 'Scrittura NFC non riuscita'
    nfcError.value = msg
    if (e?.code === 'NFC_UNAVAILABLE') {
      toast.show('NFC non disponibile o disattivato', 'error')
    } else {
      toast.show(msg, 'error')
    }
  } finally {
    nfcBusy.value = false
    nfcStatusMsg.value = ''
  }
}

async function confirmDelete() {
  if (!confirm(`Eliminare il biglietto di ${fullName.value}? Verrà cancellato definitivamente.`)) return
  await bcStore.deleteCard(card.value.id)
  router.push({ path: '/', query: { t: 'business' } })
}

let nfcCheckTimer = null
let nfcShareTimer = null

const NFC_SHARE_TIMEOUT = 60000

async function toggleNfcShare() {
  nfcError.value = ''
  if (nfcShareActive.value) {
    await stopNfcShareSession()
    return
  }
  nfcShareBusy.value = true
  try {
    const result = await startNfcShare(vcardText.value)
    nfcShareActive.value = true
    nfcShareSize.value = result?.size || 0
    toast.show('Condivisione NFC attiva: appoggia l\'altro smartphone', 'success')
    nfcShareTimer = setTimeout(() => {
      stopNfcShareSession()
      toast.show('Condivisione NFC terminata', 'info')
    }, NFC_SHARE_TIMEOUT)
  } catch (e) {
    const msg = e?.message || 'Condivisione NFC non riuscita'
    nfcError.value = msg
    toast.show(msg, 'error')
  } finally {
    nfcShareBusy.value = false
  }
}

async function stopNfcShareSession() {
  if (nfcShareTimer) {
    clearTimeout(nfcShareTimer)
    nfcShareTimer = null
  }
  if (!nfcShareActive.value) return
  nfcShareActive.value = false
  nfcShareSize.value = 0
  try {
    await stopNfcShare()
  } catch {}
}

onMounted(async () => {
  loadCard()
  if (isNfcNativeAvailable()) {
    const check = async () => {
      nfcVisible.value = await isNfcSupported()
    }
    check()
    nfcCheckTimer = setInterval(check, 5000)
  }
})

onUnmounted(() => {
  if (nfcCheckTimer) clearInterval(nfcCheckTimer)
  stopNfcShareSession()
})
</script>

<style scoped>
.loading-state, .empty-state {
  text-align: center;
  padding: 48px;
  color: var(--text-secondary);
}

.card-preview {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  border-left: 4px solid var(--primary);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: var(--shadow);
}

.avatar-lg {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 28px;
  font-weight: 700;
  overflow: hidden;
  flex-shrink: 0;
}

.avatar-lg-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.card-preview-info h2 {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 4px;
}

.card-preview-info .holder {
  font-size: 14px;
  color: var(--text-secondary);
}

.card-preview-info .role {
  font-size: 13px;
}

.detail-section {
  margin-top: 16px;
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 20px;
  box-shadow: var(--shadow);
}

.section-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-secondary);
  text-align: center;
  margin-bottom: 8px;
}

.barcode-hint {
  font-size: 11px;
  color: var(--text-secondary);
  text-align: center;
  margin-top: 8px;
  line-height: 1.4;
}

.data-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}

.data-row:last-child {
  border-bottom: none;
}

.data-label {
  font-size: 13px;
  color: var(--text-secondary);
}

.data-value {
  font-size: 14px;
  font-weight: 500;
  text-align: right;
  max-width: 60%;
  word-break: break-all;
  cursor: pointer;
}

.detail-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
  padding: 0 4px;
}

.row-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--card-bg);
  cursor: pointer;
  color: var(--text);
  transition: all 0.15s;
}

.btn-icon:hover {
  background: var(--bg);
}

.btn-icon-danger {
  color: var(--danger);
  border-color: var(--danger);
}

.btn-icon-danger:hover {
  background: #fff5f5;
}

.nfc-error {
  margin-top: 12px;
  padding: 10px 12px;
  background: #fff5f5;
  border: 1px solid var(--danger);
  color: var(--danger);
  border-radius: var(--radius-sm);
  font-size: 13px;
}
</style>
