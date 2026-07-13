<template>
  <div class="barcode-wrapper" ref="container">
    <svg v-if="!isQr" ref="barcodeSvg"></svg>
    <img v-else :src="qrDataUrl" alt="QR Code" class="qr-display" />
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

const isQr = computed(() => props.type === 'QR' || props.type === 'QRCODE')

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
        height: props.height,
        displayValue: true,
        fontSize: 14,
        margin: 8,
        background: '#ffffff',
      })
    } catch (e) {
      console.warn('Barcode render error:', e.message)
    }
  }
}

onMounted(render)
watch(() => [props.code, props.type], render)
</script>

<style scoped>
.barcode-wrapper {
  display: flex;
  justify-content: center;
  padding: 8px 0;
}

.barcode-wrapper svg,
.barcode-wrapper .qr-display {
  max-width: 100%;
  height: auto;
}

.qr-display {
  border-radius: 8px;
}
</style>
