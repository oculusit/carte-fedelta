<template>
  <div class="barcode-wrapper">
    <svg v-if="!isQr" ref="barcodeSvg" class="barcode-svg"></svg>
    <img v-else :src="qrDataUrl" alt="QR Code" class="qr-display" />
    <img v-if="showQr" :src="qrDataUrl" alt="QR Code" class="qr-below" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
const qrDataUrl = ref('')

const ALPHANUMERIC_TYPES = ['CODE128', 'CODE39', 'ITF', 'MSI', 'FISCALCODE']

const isQr = computed(() => props.type === 'QR' || props.type === 'QRCODE')
const isAlphanumeric = computed(() => ALPHANUMERIC_TYPES.includes(props.type))
const showQr = computed(() => isAlphanumeric.value && !isQr.value && props.code)
const barcodeHeight = computed(() => isAlphanumeric.value ? 100 : props.height)

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
</style>
