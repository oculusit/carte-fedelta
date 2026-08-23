<template>
  <div class="barcode-wrapper">
    <svg v-if="!isQr" ref="barcodeSvg" class="barcode-svg barcode-tappable" @click.stop="expandBarcode"></svg>
    <img v-else :src="qrDataUrl" alt="QR Code" class="qr-display qr-tappable" @click.stop="expandQr" />
    <img v-if="showQr" :src="qrDataUrl" alt="QR Code" class="qr-below qr-tappable" @click.stop="expandQr" />

    <Teleport to="body">
      <div v-if="barcodeExpanded" class="barcode-overlay" @click="barcodeExpanded = false">
        <div class="barcode-overlay-inner">
          <svg ref="barcodeFullscreenSvg" class="barcode-fullscreen"></svg>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="qrExpanded" class="barcode-overlay" @click="qrExpanded = false; qrLargeUrl = ''">
        <img :src="qrLargeUrl || qrDataUrl" alt="QR Code" class="qr-expanded" />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import JsBarcode from 'jsbarcode'
import QRCode from 'qrcode'

const props = defineProps({
  code: { type: String, required: true },
  type: { type: String, default: 'CODE128' },
  width: { type: Number, default: 2 },
  height: { type: Number, default: 60 },
})

const container = ref(null)
const barcodeSvg = ref(null)
const barcodeFullscreenSvg = ref(null)
const qrDataUrl = ref('')
const qrLargeUrl = ref('')
const qrExpanded = ref(false)
const barcodeExpanded = ref(false)

const ALPHANUMERIC_TYPES = ['CODE128', 'CODE39', 'ITF', 'MSI', 'FISCALCODE']

const isQr = computed(() => props.type === 'QR' || props.type === 'QRCODE')
const isAlphanumeric = computed(() => ALPHANUMERIC_TYPES.includes(props.type))
const showQr = computed(() => isAlphanumeric.value && !isQr.value && props.code)
const barcodeHeight = computed(() => isAlphanumeric.value ? 100 : props.height)

async function expandBarcode() {
  barcodeExpanded.value = true
  await nextTick()
  if (barcodeFullscreenSvg.value) {
    const jsFormat = props.type === 'FISCALCODE' ? 'CODE39' : props.type
    try {
      JsBarcode(barcodeFullscreenSvg.value, props.code, {
        format: jsFormat,
        width: 6,
        height: 250,
        displayValue: true,
        fontSize: 28,
        margin: 12,
        background: '#ffffff',
      })
    } catch (e) {
      console.warn('Fullscreen barcode error:', e.message)
    }
  }
}

async function expandQr() {
  qrExpanded.value = true
  const size = Math.min(window.innerWidth, window.innerHeight) - 48
  try {
    qrLargeUrl.value = await QRCode.toDataURL(props.code, { width: Math.max(size, 200), margin: 2 })
  } catch {
    qrLargeUrl.value = ''
  }
}

function render() {
  if (!props.code) return
  if (isQr.value) {
    QRCode.toDataURL(props.code, { width: 220, margin: 2 })
      .then(url => { qrDataUrl.value = url })
      .catch(e => console.warn('QR render error:', e.message))
  } else if (barcodeSvg.value) {
    const jsFormat = props.type === 'FISCALCODE' ? 'CODE39' : props.type
    try {
      JsBarcode(barcodeSvg.value, props.code, {
        format: jsFormat,
        width: props.width,
        height: barcodeHeight.value,
        displayValue: true,
        fontSize: 14,
        margin: 8,
        background: '#ffffff',
      })
    } catch (e) {
      console.warn('Barcode render error:', e.message)
    }
  }
  if (showQr.value) {
    QRCode.toDataURL(props.code, { width: 140, margin: 1 })
      .then(url => { qrDataUrl.value = url })
      .catch(() => {})
  }
}

onMounted(render)
watch(() => [props.code, props.type], render)
</script>

<style scoped>
.barcode-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
}

.barcode-svg,
.qr-display,
.qr-below {
  max-width: 100%;
  height: auto;
}

.qr-display {
  border-radius: 8px;
}

.qr-below {
  border-radius: 6px;
  width: 120px;
}

.barcode-tappable,
.qr-tappable {
  cursor: pointer;
}
</style>

<style>
.barcode-overlay {
  position: fixed;
  inset: 0;
  z-index: 9000;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.barcode-overlay-inner {
  width: 100vh;
  height: 100vw;
  display: flex;
  align-items: center;
  justify-content: center;
  transform: rotate(90deg);
}

.barcode-fullscreen {
  max-width: 100vh;
  max-height: 100vw;
  background: white;
  border-radius: 12px;
  padding: 24px;
}

.qr-expanded {
  max-width: 100%;
  max-height: 100%;
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
}
</style>
