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
        <span
          class="star"
          :class="{ starred: card.is_favorite }"
          @click="toggleFavorite"
          title="Preferiti"
        >{{ card.is_favorite ? '★' : '☆' }}</span>
      </div>

      <div class="detail-section">
        <h3 class="section-label">QR Code vCard</h3>
        <BarcodeDisplay :code="vcardQrText" type="QR" />
        <p class="barcode-hint">Chi scansiona il QR può salvare il tuo biglietto direttamente nei propri contatti.</p>

        <div class="action-row">
          <button class="action-btn" @click="shareVCard" :disabled="sharing" title="Condividi biglietto (.vcf)">
            <span class="action-icons">
              <svg class="action-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
            </span>
            <span class="action-label">{{ sharing ? 'Invio...' : 'Condividi .vcf' }}</span>
          </button>

          <button v-if="nfcVisible" class="action-btn" @click="toggleNfcShare" :disabled="nfcShareBusy" title="Condividi il biglietto tramite NFC (appoggia l'altro smartphone)">
            <span class="action-icons">
              <svg class="action-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
              <svg class="action-icon action-icon-small" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zM18 6h-5c-1.1 0-2 .9-2 2v2.28c-.6.35-1 .98-1 1.72 0 1.1.9 2 2 2s2-.9 2-2c0-.74-.4-1.38-1-1.72V8h3v8H8V8h2V6H6v12h12V6z"/></svg>
            </span>
            <span class="action-label">{{ nfcShareActive ? 'Ferma NFC' : 'Via NFC' }}</span>
          </button>

          <button v-if="nfcVisible" class="action-btn" @click="writeNfc" :disabled="nfcBusy || nfcShareActive" title="Scrivi il biglietto su un tag NFC fisico">
            <span class="action-icons">
              <svg class="action-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
              <svg class="action-icon action-icon-small" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H4V4h16v16zM18 6h-5c-1.1 0-2 .9-2 2v2.28c-.6.35-1 .98-1 1.72 0 1.1.9 2 2 2s2-.9 2-2c0-.74-.4-1.38-1-1.72V8h3v8H8V8h2V6H6v12h12V6z"/></svg>
            </span>
            <span class="action-label">{{ nfcLabel }}</span>
          </button>
        </div>

        <div v-if="countdown > 0" class="screen-timer" @click.stop="resetWakeLock" title="Clicca per ripristinare lo schermo acceso per altri 2 minuti">
          <span class="screen-timer-icon">⏱</span>
          <span class="screen-timer-text">{{ formattedCountdown }}</span>
        </div>
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

      <div class="row-buttons">
        <button class="btn-icon" title="Modifica" @click="$router.push(`/business-card/${card.id}/edit`)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="btn-icon btn-icon-danger" title="Elimina" @click="confirmDelete">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
      </div>

      <div v-if="nfcError" class="nfc-error">{{ nfcError }}</div>
    </template>

    <div v-if="nfcShareActive && nfcInstructionsOpen" class="nfc-overlay">
      <div class="nfc-overlay-card">
        <h3 class="nfc-overlay-title">📤 Condivisione NFC attiva</h3>
        <p class="nfc-overlay-subtitle">Passa il biglietto da visita a un altro telefono</p>

        <div class="nfc-illustration" aria-hidden="true">
          <svg viewBox="0 0 400 205" class="nfc-svg">
            <path class="nfc-wave nfc-wave-1" d="M212.0 79.2 A24 24 0 0 1 212.0 120.8" />
            <path class="nfc-wave nfc-wave-2" d="M222.0 61.9 A44 44 0 0 1 222.0 138.1" />
            <path class="nfc-wave nfc-wave-3" d="M232.0 44.6 A64 64 0 0 1 232.0 155.4" />
            <path class="nfc-wave nfc-wave-1" d="M188.0 120.8 A24 24 0 0 1 188.0 79.2" />
            <path class="nfc-wave nfc-wave-2" d="M178.0 138.1 A44 44 0 0 1 178.0 61.9" />
            <path class="nfc-wave nfc-wave-3" d="M168.0 155.4 A64 64 0 0 1 168.0 44.6" />

            <rect class="nfc-phone-screen" x="36" y="42" width="8" height="136" rx="4" />
            <rect class="nfc-phone-body" x="30" y="35" width="80" height="150" rx="14" />
            <rect class="nfc-phone-cam" x="110" y="70" width="9" height="20" rx="4" />

            <rect class="nfc-phone-cam" x="281" y="70" width="9" height="20" rx="4" />
            <rect class="nfc-phone-body" x="290" y="35" width="80" height="150" rx="14" />
            <rect class="nfc-phone-screen" x="356" y="42" width="8" height="136" rx="4" />

            <text class="nfc-phone-label" x="70" y="200">Questo telefono</text>
            <text class="nfc-phone-label" x="330" y="200">Altro telefono</text>
          </svg>
        </div>

        <ol class="nfc-steps">
          <li>Sblocca l'altro telefono e <strong>tienilo con lo schermo acceso</strong>.</li>
          <li>Appoggia i due telefoni <strong>schiena contro schiena</strong>, allineando le scocche posteriori.</li>
          <li>L'altro telefono riceverà il biglietto e proporrà di <strong>salvarlo nei contatti</strong>.</li>
        </ol>

        <p v-if="nfcShareSize" class="nfc-overlay-note">Biglietto pronto — {{ nfcShareSize }} byte</p>
        <p class="nfc-overlay-note">Se sull'altro telefono compare "Nuovo tag raccolto / Tag vuoto" è normale: sta solo leggendo il tuo telefono, non blocca l'invio.</p>

        <div class="nfc-overlay-actions">
          <button class="btn btn-primary btn-block" @click="stopNfcShareSession">Ferma NFC</button>
        </div>
      </div>
    </div>

    <div v-if="nfcBusy" class="nfc-overlay">
      <div class="nfc-overlay-card">
        <h3 class="nfc-overlay-title">📝 Scrittura su tag NFC</h3>
        <p class="nfc-overlay-subtitle">Salva il biglietto da visita su un tag NFC fisico</p>

        <div class="nfc-illustration" aria-hidden="true">
          <svg viewBox="0 0 400 205" class="nfc-svg">
            <path class="nfc-wave nfc-wave-1" d="M212.0 79.2 A24 24 0 0 1 212.0 120.8" />
            <path class="nfc-wave nfc-wave-2" d="M222.0 61.9 A44 44 0 0 1 222.0 138.1" />
            <path class="nfc-wave nfc-wave-3" d="M232.0 44.6 A64 64 0 0 1 232.0 155.4" />
            <path class="nfc-wave nfc-wave-1" d="M188.0 120.8 A24 24 0 0 1 188.0 79.2" />
            <path class="nfc-wave nfc-wave-2" d="M178.0 138.1 A44 44 0 0 1 178.0 61.9" />
            <path class="nfc-wave nfc-wave-3" d="M168.0 155.4 A64 64 0 0 1 168.0 44.6" />

            <rect class="nfc-phone-screen" x="36" y="42" width="8" height="136" rx="4" />
            <rect class="nfc-phone-body" x="30" y="35" width="80" height="150" rx="14" />
            <rect class="nfc-phone-cam" x="110" y="70" width="9" height="20" rx="4" />

            <rect class="nfc-tag-body" x="290" y="85" width="90" height="60" rx="8" />
            <rect class="nfc-tag-antenna" x="296" y="91" width="78" height="48" rx="7" />
            <rect class="nfc-tag-antenna" x="302" y="97" width="66" height="36" rx="6" />
            <rect class="nfc-tag-chip" x="308" y="100" width="14" height="14" rx="2" />

            <text class="nfc-phone-label" x="70" y="200">Questo telefono</text>
            <text class="nfc-phone-label" x="335" y="200">Tag NFC</text>
          </svg>
        </div>

        <ol class="nfc-steps">
          <li>Un <strong>tag NFC</strong> è una piccola etichetta o card: chi la tocca con lo smartphone riceve il contenuto, senza installare nulla.</li>
          <li>Tieni il tag appoggiato su una superficie e <strong>accosta il retro del telefono</strong> (zona NFC, di solito in alto).</li>
          <li>Attendi la conferma: il tag ora contiene il biglietto da visita.</li>
        </ol>

        <p v-if="nfcStatusMsg" class="nfc-status">{{ nfcStatusMsg }}</p>

        <p class="nfc-warn-note">⚠️ Versione non ancora testata su tag reali. Se provi la scrittura, sarebbe interessante ricevere un feedback su come va (modello di tag, esito, eventuali errori): ci aiuterà a sistemarla.</p>

        <div class="nfc-overlay-actions">
          <button class="btn btn-primary btn-block" @click="stopNfcWrite">Ferma NFC</button>
        </div>
      </div>
    </div>

    <div v-if="nfcDisabledOverlay" class="nfc-overlay" @click.self="cancelNfcDisabledPrompt">
      <div class="nfc-overlay-card">
        <h3 class="nfc-overlay-title">⚠️ Attenzione! NFC non è attivo.</h3>
        <p class="nfc-confirm-question">Vuoi attivarlo ora?</p>

        <div class="nfc-overlay-actions">
          <button class="btn btn-primary" @click="confirmEnableNfc">Sì</button>
          <button class="btn btn-outline" @click="cancelNfcDisabledPrompt">No</button>
        </div>

        <p class="nfc-overlay-note">Scegliendo Sì verrai reindirizzato alle impostazioni di sistema dove poter attivare il sensore NFC.</p>
      </div>
    </div>

    <div v-if="nfcUnavailableOverlay" class="nfc-overlay" @click.self="closeNfcUnavailable">
      <div class="nfc-overlay-card">
        <h3 class="nfc-overlay-title">⚠️ Attenzione!</h3>
        <p class="nfc-unavailable-msg">Questo dispositivo non dispone di connessione NFC. Usa la condivisione tramite file VCARD (.vcf) oppure tramite scansione del QR-Code.</p>

        <div class="nfc-overlay-actions">
          <button class="btn btn-primary btn-block" @click="closeNfcUnavailable">OK</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBusinessCardsStore } from '../stores/businessCards.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'
import { buildVCard, buildVCardForNfc, buildVcfShareText, VCF_SHARE_MESSAGE_KEY, VCF_FOOTER, VCF_FOOTER_VCARD } from '../services/vcard.js'
import { settingsDb } from '../services/db.js'
import { isNfcNativeAvailable, isNfcSupported, getNfcStatus, openNfcSettings, writeVCardToTag, startNfcShare, stopNfcShare } from '../services/nfc.js'
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
const nfcInstructionsOpen = ref(false)
const nfcDisabledOverlay = ref(false)
const nfcUnavailableOverlay = ref(false)
const nfcWriteCancel = ref(null)

const WAKE_LOCK_TIMEOUT = 120000
let wakeLockSentinel = null
let wakeLockTimer = null
let countdownInterval = null
const countdown = ref(0)

let previousBrightness = 1
let brightnessPlugin = null

const formattedCountdown = computed(() => {
  const mins = Math.floor(countdown.value / 60)
  const secs = countdown.value % 60
  return mins + ':' + secs.toString().padStart(2, '0')
})

const fullName = computed(() => {
  const first = (card.value?.first_name || '').trim()
  const last = (card.value?.last_name || '').trim()
  if (first && last) return `${last} ${first}`
  return first || last || 'Biglietto'
})

const initials = computed(() => {
  const first = (card.value?.first_name || '').trim().charAt(0)
  const last = (card.value?.last_name || '').trim().charAt(0)
  return (first + last || '?').toUpperCase()
})

const vcardText = computed(() => (card.value ? buildVCard(card.value) : ''))
const vcardQrText = computed(() => {
  if (!card.value) return ''
  const bc = { ...card.value, notes: [card.value.notes, VCF_FOOTER_VCARD].filter(Boolean).join(' | ') }
  return buildVCard(bc, '\r\n', { includePhoto: false })
})
const vcardTagText = computed(() => {
  if (!card.value) return ''
  const bc = { ...card.value, notes: [card.value.notes, VCF_FOOTER_VCARD].filter(Boolean).join(' | ') }
  return buildVCard(bc, '\r\n', { includePhoto: false })
})

const addressText = computed(() => {
  if (!card.value) return ''
  return [card.value.address, card.value.city, card.value.postal_code, card.value.country]
    .map((x) => (x || '').trim())
    .filter(Boolean)
    .join(', ')
})

const nfcLabel = computed(() => {
  if (!nfcBusy.value) return 'Scrivi su tag'
  return nfcStatusMsg.value || 'Appoggia il tag...'
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

async function toggleFavorite() {
  const next = card.value.is_favorite ? 0 : 1
  await bcStore.updateCard(card.value.id, { is_favorite: next })
  card.value.is_favorite = next
  toast.show(next ? 'Aggiunto ai preferiti' : 'Rimosso dai preferiti', 'success')
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
    const customMessage = await settingsDb.get(VCF_SHARE_MESSAGE_KEY)
    const shareText = buildVcfShareText(card.value, customMessage)
    if (Capacitor.isNativePlatform()) {
      await shareFile({ filename, data: vcardText.value, title: fullName.value, text: shareText, mimeType: 'text/vcard' })
    } else if (navigator.share && navigator.canShare?.({ files: [new File([vcardText.value], filename, { type: 'text/vcard' })] })) {
      await navigator.share({ files: [new File([vcardText.value], filename, { type: 'text/vcard' })], title: fullName.value, text: shareText })
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

async function checkNfcBeforeUse() {
  const status = await getNfcStatus()
  if (status === 'NO_NFC') return 'no-nfc'
  if (status === 'NFC_DISABLED') return 'disabled'
  return 'ok'
}

function cancelNfcDisabledPrompt() {
  nfcDisabledOverlay.value = false
}

async function confirmEnableNfc() {
  nfcDisabledOverlay.value = false
  try {
    await openNfcSettings()
  } catch (e) {
    toast.show('Impossibile aprire le impostazioni NFC', 'error')
  }
}

function closeNfcUnavailable() {
  nfcUnavailableOverlay.value = false
}

async function writeNfc() {
  nfcError.value = ''
  const guard = await checkNfcBeforeUse()
  if (guard === 'no-nfc') {
    nfcUnavailableOverlay.value = true
    return
  }
  if (guard === 'disabled') {
    nfcDisabledOverlay.value = true
    return
  }
  nfcBusy.value = true
  nfcStatusMsg.value = ''
  try {
    await writeVCardToTag(vcardTagText.value, (stage) => {
      const map = {
        'appoggia': 'Appoggia il tag NFC da scrivere...',
        'trovato': 'Tag trovato, scrittura...',
        'scritto': 'Scrittura completata!',
      }
      nfcStatusMsg.value = map[stage] || stage
    }, (cancel) => {
      nfcWriteCancel.value = cancel
    })
    toast.show('Biglietto scritto sul tag NFC', 'success')
  } catch (e) {
    if (e?.code === 'NFC_CANCELLED') return
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
    nfcWriteCancel.value = null
  }
}

async function stopNfcWrite() {
  const cancel = nfcWriteCancel.value
  if (cancel) await cancel()
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
  const guard = await checkNfcBeforeUse()
  if (guard === 'no-nfc') {
    nfcUnavailableOverlay.value = true
    return
  }
  if (guard === 'disabled') {
    nfcDisabledOverlay.value = true
    return
  }
  nfcShareBusy.value = true
  try {
    const vcard = await buildVCardForNfc(card.value)
    const result = await startNfcShare(vcard)
    nfcShareActive.value = true
    nfcShareSize.value = result?.size || 0
    nfcInstructionsOpen.value = true
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

function startCountdown() {
  stopCountdown()
  countdown.value = WAKE_LOCK_TIMEOUT / 1000
  countdownInterval = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      countdown.value = 0
      stopCountdown()
      restoreBrightnessAndSleep()
    }
  }, 1000)
}

function stopCountdown() {
  if (countdownInterval) {
    clearInterval(countdownInterval)
    countdownInterval = null
  }
}

async function acquireWakeLock() {
  try {
    if ('wakeLock' in navigator) {
      wakeLockSentinel = await navigator.wakeLock.request('screen')
      wakeLockTimer = setTimeout(releaseWakeLock, WAKE_LOCK_TIMEOUT)
      startCountdown()
    }
  } catch {
  }
}

function releaseWakeLock() {
  stopCountdown()
  countdown.value = 0
  if (wakeLockTimer) {
    clearTimeout(wakeLockTimer)
    wakeLockTimer = null
  }
  if (wakeLockSentinel) {
    wakeLockSentinel.release()
    wakeLockSentinel = null
  }
}

async function restoreBrightnessAndSleep() {
  releaseWakeLock()
  if (brightnessPlugin) {
    try {
      await brightnessPlugin.setBrightness({ brightness: previousBrightness })
      console.log('Brightness restored to', previousBrightness, '(auto after 2 min)')
    } catch (e) {
      console.warn('Brightness restore error:', e)
    }
  }
}

function resetWakeLock() {
  releaseWakeLock()
  acquireWakeLock()
}

onMounted(async () => {
  loadCard()
  acquireWakeLock()
  try {
    const { CapgoBrightness } = await import('@capgo/capacitor-brightness')
    brightnessPlugin = CapgoBrightness
    const { brightness: current } = await CapgoBrightness.getBrightness()
    previousBrightness = current
    await CapgoBrightness.setBrightness({ brightness: 1 })
    console.log('Brightness set to 1, previous was', current)
  } catch (e) {
    console.log('Brightness not available:', e?.message || e)
  }
  if (isNfcNativeAvailable()) {
    const check = async () => {
      nfcVisible.value = await isNfcSupported()
    }
    check()
    nfcCheckTimer = setInterval(check, 5000)
  }
})

onUnmounted(async () => {
  if (nfcCheckTimer) clearInterval(nfcCheckTimer)
  stopNfcShareSession()
  releaseWakeLock()
  if (brightnessPlugin) {
    try {
      await brightnessPlugin.setBrightness({ brightness: previousBrightness })
      console.log('Brightness restored to', previousBrightness)
    } catch (e) {
      console.warn('Brightness restore error:', e)
    }
  }
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

.star {
  font-size: 28px;
  cursor: pointer;
  color: var(--text-secondary);
  transition: color 0.15s, transform 0.15s;
  user-select: none;
  line-height: 1;
  margin-left: auto;
  flex-shrink: 0;
}

.star:hover {
  transform: scale(1.2);
}

.star.starred {
  color: #f5a623;
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

.screen-timer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 14px;
  color: var(--primary);
  cursor: pointer;
  user-select: none;
}

.screen-timer:hover {
  opacity: 0.8;
}

.screen-timer-icon {
  font-size: 16px;
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

.action-row {
  display: flex;
  gap: 8px;
  margin-top: 14px;
}

.action-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 4px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--card-bg);
  color: var(--text);
  cursor: pointer;
  transition: all 0.15s;
  font-size: 12px;
  min-height: 62px;
}

.action-btn:hover:not(:disabled) {
  background: var(--bg);
  border-color: var(--primary);
  color: var(--primary);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-icons {
  display: flex;
  align-items: center;
  gap: 4px;
}

.action-icon {
  width: 20px;
  height: 20px;
}

.action-icon-small {
  width: 13px;
  height: 13px;
  opacity: 0.75;
}

.action-label {
  font-weight: 600;
  text-align: center;
  line-height: 1.2;
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

.nfc-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  overflow-y: auto;
}

.nfc-overlay-card {
  position: relative;
  width: 100%;
  max-width: 420px;
  background: var(--card-bg);
  border-radius: var(--radius);
  padding: 24px 20px 20px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
  animation: nfc-card-in 0.25s ease;
}

@keyframes nfc-card-in {
  from { transform: translateY(16px) scale(0.97); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
}

.nfc-overlay-close {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: var(--bg);
  color: var(--text-secondary);
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
}

.nfc-overlay-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 4px;
  text-align: center;
}

.nfc-overlay-subtitle {
  font-size: 13px;
  color: var(--text-secondary);
  text-align: center;
  margin: 0 0 12px;
}

.nfc-illustration {
  display: flex;
  justify-content: center;
  padding: 8px 0 4px;
}

.nfc-svg {
  width: 100%;
  max-width: 340px;
  height: auto;
}

.nfc-phone-body {
  fill: #e8ebf0;
  stroke: #b6bfcc;
  stroke-width: 2;
}

.nfc-phone-screen {
  fill: #3a4250;
}

.nfc-phone-cam {
  fill: #d0d6df;
  stroke: #b6bfcc;
  stroke-width: 1;
}

.nfc-phone-label {
  font-size: 13px;
  font-weight: 600;
  fill: var(--text-secondary);
  text-anchor: middle;
}

.nfc-tag-body {
  fill: #fff;
  stroke: #b6bfcc;
  stroke-width: 2;
}

.nfc-tag-antenna {
  fill: none;
  stroke: #aeb8c9;
  stroke-width: 1.5;
}

.nfc-tag-chip {
  fill: #3a4250;
}

.nfc-status {
  margin: 8px 0 0;
  text-align: center;
  font-size: 13px;
  font-weight: 700;
  color: var(--primary);
}

.nfc-confirm-question {
  margin: 8px 0 0;
  text-align: center;
  font-size: 15px;
  font-weight: 600;
}

.nfc-unavailable-msg {
  margin: 12px 0 0;
  text-align: center;
  font-size: 14px;
  line-height: 1.5;
}

.nfc-warn-note {
  margin: 10px 0 0;
  padding: 8px 10px;
  font-size: 12px;
  line-height: 1.4;
  text-align: left;
  border-radius: 8px;
  background: rgba(255, 193, 7, 0.14);
  border: 1px solid rgba(255, 193, 7, 0.5);
}

.nfc-wave {
  fill: none;
  stroke: var(--primary);
  stroke-width: 5;
  stroke-linecap: round;
  animation: nfc-pulse 1.8s ease-in-out infinite;
}

.nfc-wave-1 { opacity: 0.35; }
.nfc-wave-2 { opacity: 0.6; animation-delay: 0.3s; }
.nfc-wave-3 { opacity: 0.9; animation-delay: 0.6s; }

@keyframes nfc-pulse {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 1; }
}

.nfc-steps {
  margin: 12px 0 10px;
  padding: 0 4px 0 22px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 14px;
}

.nfc-overlay-note {
  font-size: 12px;
  color: var(--text-secondary);
  margin: 6px 0 0;
  text-align: center;
}

.nfc-overlay-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}

.nfc-overlay-actions .btn {
  flex: 1;
}
</style>
