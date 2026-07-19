<template>
  <div class="scanner-overlay" v-if="active" @click.self="close">
    <div class="scanner-panel">
      <div class="scanner-header">
        <h3>Scansiona codice a barre</h3>
        <button class="scanner-close" @click="close" type="button">✕</button>
      </div>

      <div class="scanner-viewport" ref="viewportEl">
        <div v-if="loading" class="scanner-loading">
          <span class="loading-spinner"></span>
          <p>{{ loadingMsg }}</p>
        </div>
        <div v-if="error" class="scanner-error">{{ error }}</div>
      </div>

      <div class="scanner-controls">
        <button
          v-if="!isScanning && !loading"
          class="btn btn-primary btn-block"
          @click="startCamera"
          type="button"
        >
          Avvia fotocamera
        </button>
        <div v-if="isScanning" class="scanner-actions-row">
          <button
            class="btn btn-danger btn-block"
            @click="stopCamera"
            type="button"
          >
            Ferma fotocamera
          </button>
          <button
            v-if="cameras.length > 1"
            class="btn btn-outline btn-switch-cam"
            @click="switchCamera"
            type="button"
            title="Cambia fotocamera"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 19H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>
              <path d="M13 5h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5"/>
              <circle cx="12" cy="12" r="3"/>
              <path d="m18 22-3-3 3-3"/>
              <path d="m6 2 3 3-3 3"/>
            </svg>
          </button>
        </div>
        <label class="btn btn-outline btn-block upload-label">
          Carica immagine
          <input type="file" accept="image/*" hidden @change="onFileUpload" />
        </label>
      </div>

      <p v-if="isScanning" class="scanner-hint">
        Inquadra il codice a barre della carta
        <template v-if="cameras.length > 1"> · {{ cameras[currentCameraIndex]?.label || ('Camera ' + (currentCameraIndex + 1)) }}</template>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount, watch } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { detectBarcodeType } from '../utils/barcodeUtils.js'

const emit = defineEmits(['scan', 'close'])
const props = defineProps({
  active: { type: Boolean, default: false },
})

const viewportEl = ref(null)
const isScanning = ref(false)
const loading = ref(false)
const loadingMsg = ref('')
const error = ref('')
const cameras = ref([])
const currentCameraIndex = ref(0)

let scanner = null
let scannerContainer = null

const SCANNER_ID = 'qrcode-scanner-element'

function getSupportedFormats() {
  return [
    Html5QrcodeSupportedFormats.CODE_128,
    Html5QrcodeSupportedFormats.EAN_13,
    Html5QrcodeSupportedFormats.EAN_8,
    Html5QrcodeSupportedFormats.UPC_A,
    Html5QrcodeSupportedFormats.UPC_E,
    Html5QrcodeSupportedFormats.CODE_39,
    Html5QrcodeSupportedFormats.ITF,
    Html5QrcodeSupportedFormats.QR_CODE,
    Html5QrcodeSupportedFormats.CODABAR,
    Html5QrcodeSupportedFormats.CODE_93,
    Html5QrcodeSupportedFormats.DATA_MATRIX,
    Html5QrcodeSupportedFormats.PDF_417,
  ]
}

function ensureContainer() {
  if (!scannerContainer && viewportEl.value) {
    scannerContainer = document.createElement('div')
    scannerContainer.id = SCANNER_ID
    scannerContainer.style.width = '100%'
    scannerContainer.style.height = '100%'
    scannerContainer.style.position = 'absolute'
    scannerContainer.style.top = '0'
    scannerContainer.style.left = '0'
    viewportEl.value.appendChild(scannerContainer)
  }
  return scannerContainer
}

function removeContainer() {
  if (scannerContainer && scannerContainer.parentNode) {
    scannerContainer.parentNode.removeChild(scannerContainer)
  }
  scannerContainer = null
}

async function startCamera() {
  loading.value = true
  loadingMsg.value = 'Richiedo permesso fotocamera...'
  error.value = ''

  try {
    if (!cameras.value || cameras.value.length === 0) {
      try {
        cameras.value = await Html5Qrcode.getCameras()
      } catch (permErr) {
        loading.value = false
        error.value = 'Fotocamera non accessibile. Verifica i permessi della fotocamera.'
        return
      }

      if (!cameras.value || cameras.value.length === 0) {
        loading.value = false
        error.value = 'Nessuna fotocamera trovata sul dispositivo.'
        return
      }

      // Prefer back camera by default
      const backIdx = cameras.value.findIndex(c =>
        c.label?.toLowerCase().includes('back') ||
        c.label?.toLowerCase().includes('rear') ||
        c.label?.toLowerCase().includes('environment')
      )
      currentCameraIndex.value = backIdx >= 0 ? backIdx : 0
    }

    loadingMsg.value = 'Avvio fotocamera...'

    const container = ensureContainer()
    if (!container) {
      loading.value = false
      error.value = 'Errore interno: contenitore non trovato.'
      return
    }

    if (scanner) {
      try { await scanner.stop() } catch {}
      scanner.clear()
      scanner = null
    }

    scanner = new Html5Qrcode(SCANNER_ID, {
      formatsToSupport: getSupportedFormats(),
      verbose: false,
    })

    const camId = cameras.value[currentCameraIndex.value].id

    await scanner.start(
      { deviceId: { exact: camId } },
      {
        fps: 15,
        qrbox: { width: 260, height: 120 },
        aspectRatio: 1.777,
        videoConstraints: {
          width: { ideal: 4096 },
          height: { ideal: 2160 },
        },
      },
      onScanSuccess,
      onScanFailure
    )

    isScanning.value = true
  } catch (e) {
    console.warn('Camera error:', e)

    if (e.message?.includes('NotAllowed')) {
      error.value = 'Permesso fotocamera negato. Abilitalo nelle impostazioni del dispositivo.'
    } else if (e.message?.includes('NotFound')) {
      error.value = 'Fotocamera non trovata sul dispositivo.'
    } else if (e.message?.includes('NotReadable')) {
      error.value = 'Fotocamera occupata da un\'altra applicazione.'
    } else {
      error.value = 'Errore fotocamera: ' + (e.message || 'sconosciuto')
    }
  } finally {
    loading.value = false
  }
}

async function switchCamera() {
  if (cameras.value.length < 2) return
  currentCameraIndex.value = (currentCameraIndex.value + 1) % cameras.value.length
  if (isScanning.value) {
    await stopCamera()
    await startCamera()
  }
}

async function stopCamera() {
  if (scanner) {
    try { await scanner.stop() } catch {}
    try { scanner.clear() } catch {}
    scanner = null
  }
  isScanning.value = false
  removeContainer()
}

const scannerFormatMap = {
  EAN_13: 'EAN13',
  EAN_8: 'EAN8',
  UPC_A: 'UPC',
  UPC_E: 'UPC',
  CODE_128: 'CODE128',
  CODE_39: 'CODE39',
  ITF: 'ITF',
  QR_CODE: 'QR',
  CODABAR: 'CODE128',
  CODE_93: 'CODE128',
  DATA_MATRIX: 'CODE128',
  PDF_417: 'CODE128',
  AZTEC: 'CODE128',
}

function mapScannerFormat(libFormat) {
  if (!libFormat) return ''
  let name
  if (typeof libFormat === 'string') {
    name = libFormat.toUpperCase()
  } else if (libFormat.formatName) {
    name = libFormat.formatName.toUpperCase()
  } else if (libFormat.toString) {
    name = libFormat.toString().toUpperCase()
  } else {
    return ''
  }
  return scannerFormatMap[name] || ''
}

const eanPrefixes = new Set([
  '30','31','32','33','34','35','36','37','38','39',
  '40','41','42','43','44','45','46','47','48','49',
  '50','51','52','53','54','55','56','57','58','59',
  '60','61','62','63','64','65','66','67','68','69',
  '70','71','72','73','74','75','76','77','78','79',
  '80','81','82','83','84','85','86','87','88','89',
  '90','91','92','93','94','95','96','97','98','99',
])

function onScanSuccess(decodedText, decodedResult) {
  if (!isScanning.value) return
  let code = decodedText
  let type = ''
  try {
    const raw = decodedResult?.result?.format || decodedResult?.format
    type = mapScannerFormat(raw)
  } catch {}
  if (!type) type = detectBarcodeType(code)
  if (type === 'UPC' && code.length === 12 && eanPrefixes.has(code.substring(0, 2))) {
    type = 'EAN13'
    code = '0' + code
  }
  if (type === 'EAN13' && code.length === 12) code = '0' + code
  stopCamera()
  emit('scan', { code, type, cameraFormat: true })
}

function onScanFailure() {
}

async function onFileUpload(e) {
  const file = e.target.files?.[0]
  if (!file) return

  error.value = ''
  loading.value = true
  loadingMsg.value = 'Analisi immagine...'

  const fileDivId = SCANNER_ID + '-file'
  let fileDiv = document.getElementById(fileDivId)
  if (!fileDiv) {
    fileDiv = document.createElement('div')
    fileDiv.id = fileDivId
    fileDiv.style.position = 'fixed'
    fileDiv.style.top = '-9999px'
    fileDiv.style.left = '-9999px'
    fileDiv.style.width = '1px'
    fileDiv.style.height = '1px'
    document.body.appendChild(fileDiv)
  }

  const fileScanner = new Html5Qrcode(fileDivId, {
    formatsToSupport: getSupportedFormats(),
    verbose: false,
  })

  try {
    const result = await fileScanner.scanFileV2(file, false)
    let code = result.decodedText
    let type = ''
    try {
      const raw = result.result?.format || result.format
      type = mapScannerFormat(raw)
    } catch {}
    if (!type) type = detectBarcodeType(code)
    if (type === 'UPC' && code.length === 12 && eanPrefixes.has(code.substring(0, 2))) {
      type = 'EAN13'
      code = '0' + code
    }
    if (type === 'EAN13' && code.length === 12) code = '0' + code
    emit('scan', { code, type, cameraFormat: false })
  } catch {
    error.value = 'Nessun codice a barre riconosciuto nell\'immagine'
  } finally {
    fileScanner.clear()
    if (fileDiv && fileDiv.parentNode) {
      fileDiv.parentNode.removeChild(fileDiv)
    }
    loading.value = false
    e.target.value = ''
  }
}

async function close() {
  await stopCamera()
  cameras.value = []
  currentCameraIndex.value = 0
  error.value = ''
  emit('close')
}

watch(() => props.active, (val) => {
  if (val) {
    error.value = ''
    startCamera()
  } else {
    stopCamera()
    error.value = ''
  }
})

onBeforeUnmount(() => {
  stopCamera()
})
</script>

<style scoped>
.scanner-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.scanner-panel {
  background: var(--card-bg);
  border-radius: var(--radius);
  width: 100%;
  max-width: 440px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.scanner-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  background: white;
  position: relative;
  z-index: 2;
}

.scanner-header h3 {
  font-size: 16px;
}

.scanner-close {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f0f0;
  border: none;
  border-radius: 50%;
  font-size: 18px;
  cursor: pointer;
  color: var(--text);
  transition: background 0.15s;
  z-index: 10;
}

.scanner-close:hover {
  background: #e0e0e0;
}

.scanner-close:active {
  background: #d0d0d0;
}

.scanner-viewport {
  position: relative;
  min-height: 260px;
  background: #1a1a1a;
  overflow: hidden;
}

.scanner-loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: rgba(255, 255, 255, 0.8);
  gap: 12px;
  background: rgba(0, 0, 0, 0.7);
  z-index: 5;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.scanner-error {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: #ff6b6b;
  font-size: 14px;
  text-align: center;
  background: rgba(0, 0, 0, 0.85);
  z-index: 5;
}

.scanner-controls {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px 20px;
  background: white;
  position: relative;
  z-index: 2;
}

.upload-label {
  cursor: pointer;
  text-align: center;
}

.scanner-actions-row {
  display: flex;
  gap: 8px;
  align-items: stretch;
}

.scanner-actions-row .btn-danger {
  flex: 1;
}

.btn-switch-cam {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  flex-shrink: 0;
  padding: 0;
}

.scanner-hint {
  text-align: center;
  font-size: 12px;
  color: var(--text-secondary);
  padding: 0 20px 16px;
  margin: 0;
  background: white;
  position: relative;
  z-index: 2;
}
</style>
