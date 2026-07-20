<template>
  <div class="scanner-overlay" v-if="active" @click.self="close">
    <div class="scanner-panel">
      <div class="scanner-header">
        <h3>Scansiona codice a barre</h3>
        <button class="scanner-close" @click="close" type="button">✕</button>
      </div>

      <div class="scanner-viewport" ref="viewportEl">
        <video ref="videoEl" class="scanner-video" autoplay playsinline muted></video>
        <div v-if="loading" class="scanner-loading">
          <span class="loading-spinner"></span>
          <p>{{ loadingMsg }}</p>
        </div>
        <div v-if="error" class="scanner-error">{{ error }}</div>
        <div v-if="isScanning" class="scanner-reticle"></div>
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
            class="btn btn-switch-cam"
            :class="torchOn ? 'btn-torch-on' : 'btn-torch-off'"
            @click="toggleTorch"
            type="button"
            :title="torchOn ? 'Spegni flash' : 'Accendi flash'"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
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
        <template v-if="torchOn"> · Flash attivo</template>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount, watch, nextTick } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { detectBarcodeType } from '../utils/barcodeUtils.js'

const emit = defineEmits(['scan', 'close'])
const props = defineProps({
  active: { type: Boolean, default: false },
})

const viewportEl = ref(null)
const videoEl = ref(null)
const isScanning = ref(false)
const loading = ref(false)
const loadingMsg = ref('')
const error = ref('')
const torchOn = ref(false)

let localStream = null
let scanInterval = null
let detector = null
let zxingReader = null
let zxingAbort = null

const fileScannerFormats = [
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

const barcodeFormatMap = {
  'ean_13': 'EAN13', 'ean-13': 'EAN13',
  'ean_8': 'EAN8', 'ean-8': 'EAN8',
  'upc_a': 'UPC', 'upc-a': 'UPC', 'upc_e': 'UPC', 'upc-e': 'UPC',
  'code_128': 'CODE128', 'code-128': 'CODE128', 'code128': 'CODE128',
  'code_39': 'CODE39', 'code-39': 'CODE39', 'code39': 'CODE39',
  'itf': 'ITF', 'itf-14': 'ITF',
  'qr_code': 'QR', 'qr-code': 'QR', 'qr': 'QR',
  'codabar': 'CODE128',
  'code_93': 'CODE128', 'code-93': 'CODE128',
  'data_matrix': 'CODE128', 'data-matrix': 'CODE128',
  'pdf_417': 'CODE128', 'pdf-417': 'CODE128',
  'aztec': 'CODE128',
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

function resolveType(rawFormat) {
  if (!rawFormat) return ''
  const name = (rawFormat.formatName || rawFormat.toString?.() || '').toLowerCase()
  return barcodeFormatMap[name] || ''
}

function postProcess(code, type) {
  if (!type) type = detectBarcodeType(code)
  if (type === 'UPC' && code.length === 12 && eanPrefixes.has(code.substring(0, 2))) {
    type = 'EAN13'
    code = '0' + code
  }
  if (type === 'EAN13' && code.length === 12) code = '0' + code
  return { code, type }
}

function handleDetection(decodedText, rawFormat) {
  if (!isScanning.value) return
  let type = resolveType(rawFormat)
  const { code, type: finalType } = postProcess(decodedText, type)
  stopCamera()
  emit('scan', { code, type: finalType, cameraFormat: true })
}

async function startCamera() {
  await nextTick()

  loading.value = true
  loadingMsg.value = 'Richiedo permesso fotocamera...'
  error.value = ''

  try {
    const savedId = localStorage.getItem('preferred_camera_id')

    let videoDevices = []
    try {
      const cams = await Html5Qrcode.getCameras()
      videoDevices = (cams || []).map(c => ({ deviceId: c.id, label: c.label || '' }))
    } catch {
      loading.value = false
      error.value = 'Fotocamera non accessibile. Verifica i permessi della fotocamera.'
      return
    }

    if (videoDevices.length === 0) {
      loading.value = false
      error.value = 'Nessuna fotocamera trovata sul dispositivo.'
      return
    }

    let selectedDevice = null
    if (savedId) {
      selectedDevice = videoDevices.find(d => String(d.deviceId) === String(savedId))
    }
    if (!selectedDevice) {
      selectedDevice = videoDevices.find(d =>
        d.label?.toLowerCase().includes('back') ||
        d.label?.toLowerCase().includes('rear') ||
        d.label?.toLowerCase().includes('environment')
      ) || videoDevices[videoDevices.length - 1]
    }

    const camLabel = selectedDevice?.label || ''
    loadingMsg.value = 'Avvio ' + (camLabel || 'fotocamera') + '...'

    const constraints = {
      video: {
        deviceId: { exact: selectedDevice.deviceId },
        width: { ideal: 1920 },
        height: { ideal: 1080 },
      }
    }

    localStream = await navigator.mediaDevices.getUserMedia(constraints)
    await nextTick()

    const video = videoEl.value
    if (!video) {
      error.value = 'Errore interno: elemento video non trovato.'
      return
    }

    video.srcObject = localStream
    await video.play()

    loading.value = false
    isScanning.value = true

    if ('BarcodeDetector' in window) {
      const supported = await BarcodeDetector.getSupportedFormats()
      detector = new BarcodeDetector({ formats: supported })
      scanLoop()
    } else {
      try {
        const zxing = await import('@zxing/library')
        zxingReader = new zxing.BrowserMultiFormatReader()
        const video = videoEl.value
        const controls = await zxingReader.decodeFromVideoElement(video, (result) => {
          if (result && result.getText() && isScanning.value) {
            handleDetection(result.getText(), null)
          }
        })
        zxingAbort = controls
      } catch {
        error.value = 'Nessun decodificatore barcode disponibile su questo dispositivo.'
      }
    }
  } catch (e) {
    if (e.name === 'NotAllowedError' || e.message?.includes('NotAllowed')) {
      error.value = 'Permesso fotocamera negato. Abilitalo nelle impostazioni del dispositivo.'
    } else if (e.name === 'NotFoundError' || e.message?.includes('NotFound')) {
      error.value = 'Fotocamera non trovata sul dispositivo.'
    } else if (e.name === 'NotReadableError' || e.message?.includes('NotReadable')) {
      error.value = 'Fotocamera occupata da un\'altra applicazione.'
    } else {
      error.value = 'Errore fotocamera: ' + (e.message || 'sconosciuto')
    }
  } finally {
    loading.value = false
  }
}

async function scanLoop() {
  if (!isScanning.value || !detector || !videoEl.value) return

  try {
    const results = await detector.detect(videoEl.value)
    if (results && results.length > 0) {
      const best = results[0]
      handleDetection(best.rawValue, best.format)
      return
    }
  } catch {}

  if (isScanning.value) {
    scanInterval = requestAnimationFrame(scanLoop)
  }
}

async function toggleTorch() {
  if (!localStream) return
  try {
    torchOn.value = !torchOn.value
    const track = localStream.getVideoTracks()[0]
    await track.applyConstraints({
      advanced: [{ torch: torchOn.value }]
    })
  } catch (e) {
    torchOn.value = false
  }
}

async function stopCamera() {
  torchOn.value = false
  isScanning.value = false
  if (scanInterval) {
    cancelAnimationFrame(scanInterval)
    scanInterval = null
  }
  detector = null
  if (zxingAbort) {
    try { zxingAbort.stop() } catch {}
    zxingAbort = null
  }
  zxingReader = null
  if (localStream) {
    localStream.getTracks().forEach(t => t.stop())
    localStream = null
  }
  if (videoEl.value) {
    videoEl.value.srcObject = null
  }
}

async function onFileUpload(e) {
  const file = e.target.files?.[0]
  if (!file) return

  error.value = ''
  loading.value = true
  loadingMsg.value = 'Analisi immagine...'

  const fileDivId = 'barcode-file-scanner'
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
    formatsToSupport: fileScannerFormats,
    verbose: false,
  })

  try {
    const result = await fileScanner.scanFileV2(file, false)
    let type = ''
    try {
      const raw = result.result?.format || result.format
      type = resolveType(raw)
    } catch {}
    const { code, type: finalType } = postProcess(result.decodedText, type)
    emit('scan', { code, type: finalType, cameraFormat: false })
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
  torchOn.value = false
  error.value = ''
  emit('close')
}

watch(() => props.active, async (val) => {
  if (val) {
    error.value = ''
    await startCamera()
  } else {
    await stopCamera()
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
  min-height: 300px;
  background: #1a1a1a;
  overflow: hidden;
}

.scanner-video {
  width: 100%;
  height: 100%;
  min-height: 300px;
  object-fit: cover;
  display: block;
}

.scanner-reticle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 240px;
  height: 240px;
  border: 2px solid rgba(255, 255, 255, 0.7);
  border-radius: 12px;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
  pointer-events: none;
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

.btn-torch-off {
  background: var(--card-bg);
  color: var(--text);
  border: 1px solid var(--border);
}

.btn-torch-on {
  background: #f59e0b;
  color: #fff;
  border: 1px solid #f59e0b;
}

.btn-torch-on:hover {
  background: #d97706;
  border-color: #d97706;
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
