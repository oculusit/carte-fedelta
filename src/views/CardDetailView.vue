<template>
  <div class="detail">
    <div v-if="loading" class="loading-state">Caricamento...</div>

    <div v-else-if="!card" class="empty-state">
      <h3>Carta non trovata</h3>
      <button class="btn btn-primary" style="margin-top:12px" @click="$router.push('/')">Torna alla lista</button>
    </div>

    <template v-else>
      <div class="card-preview" :style="{ borderLeftColor: card.color || '#1a73e8' }">
        <div class="card-preview-logo">
          <div v-if="isPredefined" class="logo-badge-lg" :style="{ background: logoColor }">
            {{ card.store_name.charAt(0).toUpperCase() }}
          </div>
          <div v-else-if="card.logo_data" class="logo-image-lg">
            <img :src="card.logo_data" :alt="card.store_name" />
          </div>
          <div v-else class="logo-badge-lg logo-badge-default">
            {{ card.store_name.charAt(0).toUpperCase() }}
          </div>
        </div>
        <div class="card-preview-info">
          <h2>{{ card.store_name }}</h2>
          <p class="holder" v-if="card.holder_name">{{ card.holder_name }}</p>
        </div>
        <span
          class="star"
          :class="{ starred: card.is_favorite }"
          @click="toggleFavorite"
          title="Preferiti"
        >{{ card.is_favorite ? '★' : '☆' }}</span>
      </div>

      <div class="detail-section" style="cursor:pointer" @click="copyNumber">
        <BarcodeDisplay :code="card.card_number" :type="card.barcode_type" />
        <p class="barcode-hint">In caso di problemi di scansione, aumenta manualmente la luminosità dello schermo del tuo dispositivo.</p>
        <div v-if="countdown > 0" class="screen-timer" @click.stop="resetWakeLock" title="Clicca per ripristinare lo schermo acceso per altri 2 minuti">
          <span class="screen-timer-icon">⏱</span>
          <span class="screen-timer-text">{{ formattedCountdown }}</span>
        </div>
      </div>

      <div class="detail-section card-data">
        <div class="data-row" style="cursor:pointer" @click="copyNumber">
          <span class="data-label">Numero</span>
          <span class="data-value mono">{{ card.card_number }}</span>
        </div>
        <div class="data-row" v-if="card.holder_name">
          <span class="data-label">Intestatario</span>
          <span class="data-value">{{ card.holder_name }}</span>
        </div>
        <div class="data-row">
          <span class="data-label">Tipo barcode</span>
          <span class="data-value">{{ card.barcode_type }}</span>
        </div>
        <div class="data-row" v-if="card.notes">
          <span class="data-label">Note</span>
          <span class="data-value">{{ card.notes }}</span>
        </div>
      </div>

      <div class="detail-actions">
        <button class="btn-icon" title="Modifica" @click="$router.push(`/card/${card.id}/edit`)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="btn-icon btn-icon-danger" title="Elimina" @click="confirmDelete">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20H7L3 16c-.8-.8-.8-2 0-2.8L13.2 3c.8-.8 2-.8 2.8 0L21 8.2c.8.8.8 2 0 2.8L12 20"/><path d="M6 12l6 6"/></svg>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'
import { predefinedLogos } from '../utils/logoStore.js'
import BarcodeDisplay from '../components/BarcodeDisplay.vue'

const route = useRoute()
const router = useRouter()
const store = useAppStore()

const card = ref(null)
const loading = ref(true)
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

const isPredefined = computed(() => {
  if (!card.value) return false
  if (card.value.logo_type === 'upload') return false
  const key = card.value.store_name?.toLowerCase().replace(/\s+/g, '')
  return !!predefinedLogos[key]
})

const logoColor = computed(() => {
  if (!card.value) return '#1a73e8'
  const key = card.value.store_name?.toLowerCase().replace(/\s+/g, '')
  return predefinedLogos[key]?.color || '#1a73e8'
})

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

async function loadCard() {
  loading.value = true
  card.value = await store.getCard(route.params.id)
  loading.value = false
}

async function toggleFavorite() {
  if (!card.value) return
  await store.updateCard(card.value.id, { is_favorite: card.value.is_favorite ? 0 : 1 })
  await loadCard()
  toast.show(card.value.is_favorite ? 'Aggiunta ai preferiti' : 'Rimossa dai preferiti', 'success')
}

async function confirmDelete() {
  if (!confirm(`Eliminare la carta di ${card.value.store_name}? Verrà cancellata definitivamente.`)) return
  await store.deleteCard(card.value.id)
  router.push('/')
}

function copyNumber() {
  const num = card.value?.card_number
  if (!num) return
  copyToClipboard(num).then(() => {
    toast.show('Numero copiato negli appunti', 'success')
  }).catch(() => {
    toast.show('Errore copia negli appunti', 'error')
  })
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
})

onUnmounted(async () => {
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

.card-preview-logo {
  flex-shrink: 0;
}

.logo-badge-lg {
  width: 72px;
  height: 72px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 28px;
  font-weight: 700;
}

.logo-badge-default {
  background: var(--primary);
}

.logo-image-lg {
  width: 72px;
  height: 72px;
  border-radius: 16px;
  overflow: hidden;
}

.logo-image-lg img {
  width: 100%;
  height: 100%;
  object-fit: contain;
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

.star {
  font-size: 28px;
  cursor: pointer;
  color: var(--text-secondary);
  transition: color 0.15s, transform 0.15s;
  user-select: none;
  line-height: 1;
  margin-left: auto;
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
}

.data-value.mono {
  font-family: monospace;
  letter-spacing: 1px;
}

.detail-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
  padding: 0 4px;
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
</style>
