<template>
  <div class="form-page">
    <div class="card form-card">
      <h2 class="form-title">{{ isEdit ? 'Modifica Carta' : 'Nuova Carta' }}</h2>

      <div class="input-group" ref="storeInputRef">
        <label>Nome negozio *</label>
        <div v-if="selectedStoreLogo" class="store-logo-preview">
          <img :src="selectedStoreLogo" alt="logo" />
        </div>
        <div class="input-row store-input-row">
          <input
            ref="firstInputRef"
            v-model="form.store_name"
            type="text"
            placeholder="es. Conad, Coop, Decathlon..."
            @input="onStoreNameChange"
            @focus="onStoreFocus"
            @blur="onStoreBlur"
            class="input-grow"
          />
          <div v-if="storeResults.length > 0 && showDropdown" class="store-autocomplete">
            <div
              v-for="s in storeResults"
              :key="s.id"
              class="store-option"
              @mousedown.prevent="selectStore(s)"
            >
              <span class="store-option-logo" v-if="s.logo_data">
                <img :src="s.logo_data" :alt="s.name" />
              </span>
              <span class="store-option-logo store-option-default" v-else>
                {{ s.name.charAt(0).toUpperCase() }}
              </span>
              <span>{{ s.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="input-group">
        <label>Numero carta *</label>
        <div class="input-row">
          <input v-model="form.card_number" type="text" placeholder="Inserisci il numero" class="input-grow" />
          <button type="button" class="btn btn-outline btn-scan" @click="showScanner = true" title="Scansiona con fotocamera">
            <svg viewBox="0 0 40 36" width="20" height="18" class="barcode-icon">
              <rect x="1" y="4" width="2.5" height="22" fill="currentColor" />
              <rect x="4.5" y="4" width="4" height="22" fill="currentColor" />
              <rect x="10" y="4" width="1.5" height="22" fill="currentColor" />
              <rect x="13" y="4" width="3" height="22" fill="currentColor" />
              <rect x="17.5" y="4" width="1" height="22" fill="currentColor" />
              <rect x="19.5" y="4" width="3.5" height="22" fill="currentColor" />
              <rect x="24.5" y="4" width="2" height="22" fill="currentColor" />
              <rect x="28" y="4" width="4.5" height="22" fill="currentColor" />
              <rect x="34" y="4" width="2" height="22" fill="currentColor" />
              <rect x="37" y="4" width="2" height="22" fill="currentColor" />
              <line x1="0" y1="18" x2="40" y2="18" stroke="#e53935" stroke-width="1.8" stroke-linecap="round" class="laser-line" />
            </svg>
          </button>
        </div>
        <p v-if="duplicateCard && !isEdit" class="duplicate-warning">
          ⚠ Già presente per {{ duplicateCard.store_name }}
        </p>
        <p v-if="checksumError" class="checksum-warning">
          ⚠ {{ checksumError }}
        </p>
        <p v-if="scanSource" class="scan-info">
          Scansionato {{ scanSource === 'camera' ? 'da fotocamera' : 'da immagine' }}
          <span v-if="form.barcode_type !== 'CODE128'" class="scan-type">
            · rilevato {{ form.barcode_type }}
          </span>
        </p>
      </div>

      <div class="input-group">
        <label>Intestatario</label>
        <input v-model="form.holder_name" type="text" placeholder="Il tuo nome (opzionale)" />
      </div>

      <div class="input-group">
        <label>Tipo barcode</label>
        <div class="input-row">
          <select v-model="form.barcode_type" class="input-grow">
            <option value="CODE128">CODE128 (alphanumerico)</option>
            <option value="EAN13">EAN-13 (13 cifre)</option>
            <option value="EAN8">EAN-8 (8 cifre)</option>
            <option value="UPC">UPC-A (12 cifre)</option>
            <option value="CODE39">CODE39 (alphanumerico)</option>
            <option value="ITF">ITF-14 (14 cifre)</option>
            <option value="pharmacode">Pharmacode</option>
            <option value="QR">QR Code</option>
            <option value="FISCALCODE">Codice Fiscale</option>
          </select>
          <button
            type="button"
            class="btn btn-sm btn-outline"
            @click="autoDetectType"
            title="Rileva automaticamente"
          >
            ✨ Auto
          </button>
        </div>
      </div>

      <div class="input-group">
        <label>Colore</label>
        <div class="color-row">
          <input v-model="form.color" type="color" class="color-input" />
          <span class="color-value">{{ form.color }}</span>
        </div>
      </div>

      <div class="input-group">
        <label>Note</label>
        <textarea v-model="form.notes" rows="3" placeholder="Note opzionali..."></textarea>
      </div>

      <div class="input-group">
        <label class="checkbox-label">
          <input v-model="form.is_private" type="checkbox" />
          <span>Tessera privata - Non condivisa con il gruppo famiglia</span>
        </label>
      </div>

      <div class="form-actions">
        <button class="btn btn-outline" @click="$router.back()">Annulla</button>
        <button class="btn btn-primary" @click="save" :disabled="saving">
          {{ saving ? 'Salvataggio...' : 'Salva' }}
        </button>
      </div>
    </div>
  </div>

  <BarcodeScanner
    :active="showScanner"
    @scan="onScanResult"
    @close="showScanner = false"
  />

  <div v-if="scanConfirm" class="modal-overlay" @click.self="scanConfirm = null">
    <div class="modal">
      <h3>Conferma numero</h3>
      <p>Il numero scansionato è:</p>
      <p class="scan-confirm-number">{{ scanConfirm.code }}</p>
      <div class="modal-actions">
        <button class="btn btn-outline" @click="scanConfirm = null">No, riacquisisci</button>
        <button class="btn btn-primary" @click="confirmScanNumber(true)">Sì, è corretto</button>
      </div>
    </div>
  </div>

  <div v-if="scanZeroConfirm" class="modal-overlay" @click.self="scanZeroConfirm = null">
    <div class="modal">
      <h3>Zero iniziale</h3>
      <p>Il codice a barre originale inizia con <strong>"0"</strong>?</p>
      <div class="modal-actions">
        <button class="btn btn-outline" @click="confirmLeadingZero(false)">No</button>
        <button class="btn btn-primary" @click="confirmLeadingZero(true)">Sì</button>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../stores/app.js'
import { api } from '../services/api.js'
import { toast } from '../services/toast.js'
import { detectBarcodeType, validateChecksum } from '../utils/barcodeUtils.js'
import { predefinedLogos, placeholderSvg, barcodeTypeDefaultLogo } from '../utils/logoStore.js'
import BarcodeScanner from '../components/BarcodeScanner.vue'

const route = useRoute()
const router = useRouter()
const store = useAppStore()

const isEdit = computed(() => !!route.params.id)

const normalizedNumber = computed(() => form.value.card_number.replace(/\s/g, ''))

const duplicateCard = computed(() => {
  if (isEdit.value) return null
  if (!normalizedNumber.value || normalizedNumber.value.length < 4) return null
  return store.cards.find(c =>
    c.card_number && c.card_number.replace(/\s/g, '') === normalizedNumber.value
  ) || null
})
const checksumError = computed(() => {
  const result = validateChecksum(form.value.card_number, form.value.barcode_type)
  return result.valid ? '' : result.reason
})
const saving = ref(false)
const showScanner = ref(false)
const scanSource = ref('')

const storeInputRef = ref(null)
const storeResults = ref([])
const showDropdown = ref(false)
const allStores = ref([])
const selectedStoreLogo = ref('')
let blurTimeout = null

const scanGuard = ref(false)
const scanConfirm = ref(null)
const scanZeroConfirm = ref(null)
const initializing = ref(true)
const firstInputRef = ref(null)

const form = ref({
  store_name: '',
  card_number: '',
  holder_name: '',
  barcode_type: 'CODE128',
  logo_type: 'none',
  logo_path: '',
  logo_data: '',
  notes: '',
  is_private: false,
  color: '#1a73e8',
})

onMounted(async () => {
  nextTick(() => firstInputRef.value?.focus())
  if (isEdit.value) {
    const card = await store.getCard(route.params.id)
    if (card) {
      form.value = {
        ...form.value,
        ...card,
        is_private: card.is_private ? true : false,
      }
      if (card.logo_data) selectedStoreLogo.value = card.logo_data
    }
  }

  initializing.value = false
})

function filterStores(query) {
  if (!query || query.length < 2) { storeResults.value = []; return }
  const q = query.toLowerCase()
  storeResults.value = allStores.value
    .filter(s => s.name.toLowerCase().includes(q))
    .slice(0, 8)
}

async function onStoreNameChange() {
  if (allStores.value.length > 0) {
    filterStores(form.value.store_name)
    showDropdown.value = storeResults.value.length > 0
    const exact = allStores.value.find(s => s.name.toLowerCase() === form.value.store_name.toLowerCase().trim())
    if (exact) {
      await applyStoreLogo(exact)
    } else {
      selectedStoreLogo.value = ''
    }
  }
}

function onStoreFocus() {
  showDropdown.value = true
  if (form.value.store_name) filterStores(form.value.store_name)
}

function onStoreBlur() {
  blurTimeout = setTimeout(() => { showDropdown.value = false }, 200)
}

async function applyStoreLogo(s) {
  let storeData = s
  if (!storeData.logo_data && storeData.id) {
    try {
      storeData = await api.stores.get(storeData.id)
    } catch {}
  }
  if (storeData.logo_data) {
    selectedStoreLogo.value = storeData.logo_data
    form.value.logo_type = 'upload'
    form.value.logo_data = storeData.logo_data
    form.value.logo_path = ''
  } else {
    selectedStoreLogo.value = ''
    form.value.logo_type = 'none'
    form.value.logo_data = ''
    form.value.logo_path = ''
  }
}

async function selectStore(s) {
  form.value.store_name = s.name
  showDropdown.value = false
  await applyStoreLogo(s)
}

function onScanResult(result) {
  scanConfirm.value = result
  showScanner.value = false
}

function confirmScanNumber(confirmed) {
  const result = scanConfirm.value
  scanConfirm.value = null
  if (!confirmed) return
  if (result.code.length === 12) {
    scanZeroConfirm.value = result
    return
  }
  applyScanResult(result)
}

function confirmLeadingZero(addZero) {
  const result = scanZeroConfirm.value
  scanZeroConfirm.value = null
  if (addZero) {
    result.code = '0' + result.code
    result.type = 'EAN13'
  }
  applyScanResult(result)
}

function applyScanResult(result) {
  scanGuard.value = true
  form.value.card_number = result.code
  form.value.barcode_type = result.type
  scanSource.value = result.cameraFormat ? 'camera' : 'file'
  autoDetectType()
}

async function autoDetectType() {
  if (!form.value.card_number) return
  const detected = detectBarcodeType(form.value.card_number)
  form.value.barcode_type = detected

  const logoKey = barcodeTypeDefaultLogo[detected]
  if (logoKey && predefinedLogos[logoKey]) {
    const logo = predefinedLogos[logoKey]
    if (!form.value.store_name || form.value.store_name === logo.name) {
      form.value.store_name = logo.name
      const dbStore = allStores.value.find(s => s.name.toLowerCase() === logo.name.toLowerCase())
      if (dbStore) {
        await applyStoreLogo(dbStore)
        return
      }
      form.value.logo_type = 'predefined'
      form.value.logo_path = logoKey
      form.value.logo_data = ''
      selectedStoreLogo.value = placeholderSvg(logo.color, logo.name.charAt(0).toUpperCase())
    }
  } else if (form.value.logo_type === 'none') {
    if (form.value.store_name) {
      const dbStore = allStores.value.find(s => s.name.toLowerCase() === form.value.store_name.toLowerCase().trim())
      if (dbStore) {
        await applyStoreLogo(dbStore)
        return
      }
    }
    const letter = (form.value.store_name || detected).charAt(0).toUpperCase()
    const svg = placeholderSvg('#1a73e8', letter)
    form.value.logo_type = 'upload'
    form.value.logo_path = ''
    form.value.logo_data = svg
    selectedStoreLogo.value = svg
  }
}

watch(() => form.value.card_number, () => {
  if (initializing.value) return
  if (scanGuard.value) { scanGuard.value = false; return }
  autoDetectType()
})

async function save() {
  if (!form.value.store_name.trim()) {
    toast.show('Inserisci il nome del negozio', 'error')
    return
  }
  if (!form.value.card_number.trim()) {
    toast.show('Inserisci il numero della carta', 'error')
    return
  }

  if (duplicateCard.value) {
    toast.show('Numero carta già presente', 'warning')
    return
  }

  saving.value = true
  try {
    const data = { ...form.value }
    if (!data.logo_data) delete data.logo_data
    if (isEdit.value) {
      await store.updateCard(route.params.id, data)
    } else {
      await store.createCard(data)
    }
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    saving.value = false
  }
}

</script>

<style scoped>
.form-card {
  padding: 20px;
}
.form-title {
  font-size: 20px;
  margin-bottom: 20px;
}
.input-row {
  display: flex;
  gap: 8px;
  align-items: stretch;
}
.input-grow {
  flex: 1;
  min-width: 0;
}
.btn-scan {
  padding: 8px 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.barcode-icon {
  display: block;
  color: var(--text);
}
.laser-line {
  animation: laser-scan 1.4s ease-in-out infinite;
}
@keyframes laser-scan {
  0%, 100% { transform: translateY(-6px); opacity: 0.3; }
  50% { transform: translateY(6px); opacity: 1; }
}
.scan-info {
  font-size: 12px;
  color: var(--success);
  margin-top: 4px;
}

.duplicate-warning {
  font-size: 13px;
  color: #e65100;
  font-weight: 600;
  margin-top: 4px;
}
.checksum-warning {
  font-size: 13px;
  color: #e53935;
  font-weight: 600;
  margin-top: 4px;
}
.scan-type {
  color: var(--text-secondary);
}
.color-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.color-input {
  width: 48px;
  height: 48px;
  border: 2px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 2px;
  cursor: pointer;
}
.color-value {
  font-size: 14px;
  color: var(--text-secondary);
  font-family: monospace;
}
.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}
.form-actions .btn {
  flex: 1;
}
textarea {
  resize: vertical;
}
.store-input-row {
  position: relative;
  flex-wrap: wrap;
}
.store-logo-preview {
  width: 160px;
  max-width: 100%;
  margin: 0 auto 10px;
  aspect-ratio: 8 / 5.5;
  border-radius: 12px;
  overflow: hidden;
  isolation: isolate;
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--card-bg, #fff);
}
.store-logo-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 12px;
}
.store-autocomplete {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 50;
  background: white;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  max-height: 260px;
  overflow-y: auto;
  margin-top: 2px;
}
.store-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.1s;
}
.store-option:hover {
  background: #f5f5f5;
}
.store-option-logo {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: white;
  background: var(--primary);
  flex-shrink: 0;
  overflow: hidden;
}
.store-option-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.store-option-default {
  background: var(--primary);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
  color: var(--text-secondary);
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  padding: 20px;
}
.modal {
  background: var(--card-bg, #fff);
  border-radius: var(--radius);
  padding: 24px;
  max-width: 420px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.modal h3 {
  margin: 0 0 12px;
  font-size: 16px;
}
.modal p {
  font-size: 13px;
  margin: 0 0 8px;
  color: var(--text-secondary);
}
.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}
.scan-confirm-number {
  font-size: 24px !important;
  font-weight: 700;
  text-align: center;
  color: var(--text-primary) !important;
  font-family: monospace;
  letter-spacing: 2px;
  padding: 12px 0;
}
</style>
