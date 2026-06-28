<template>
  <div class="logo-cropper">
    <div v-if="state === 'preview'" class="cropper-preview-only">
      <img :src="modelValue" class="preview-img" />
      <button class="btn btn-outline btn-sm" @click="startReplace">+ Aggiungi un logo diverso</button>
    </div>

    <div v-else-if="state === 'empty'" class="cropper-empty">
      <label class="btn btn-primary upload-trigger">
        <input type="file" accept="image/*" @change="onFileSelect" hidden />
        + Scegli immagine
      </label>
      <p class="empty-hint">PNG, JPG o WebP. Verrà ritagliata in formato orizzontale 8:5.5</p>
      <button v-if="hasOriginal" class="btn btn-outline btn-sm" @click="cancelReplace" style="margin-top:8px">Annulla</button>
    </div>

    <div v-if="state === 'cropping' || state === 'cropped'" class="cropper-workspace">
      <div class="cropper-viewport" ref="viewportRef" :style="{ width: vpW + 'px', height: vpH + 'px' }">
        <div
          class="cropper-image-layer"
          :style="imageLayerStyle"
          @mousedown="onPointerDown"
          @touchstart.prevent="onPointerDown"
        >
          <img :src="imageSrc" draggable="false" ref="imgRef" @load="onImgLoad" />
        </div>
        <div class="cropper-mask" :style="maskStyle"></div>
        <div class="crop-box" :style="cropBoxStyle">
          <div class="crop-handle nw"></div>
          <div class="crop-handle ne"></div>
          <div class="crop-handle sw"></div>
          <div class="crop-handle se"></div>
        </div>
      </div>

      <div class="cropper-controls">
        <div class="zoom-slider">
          <span class="zoom-label">Zoom</span>
          <input type="range" min="0.2" max="4" step="0.01" v-model.number="zoom" />
          <span class="zoom-pct">{{ Math.round(zoom * 100) }}%</span>
        </div>
        <div class="cropper-actions">
          <button class="btn btn-outline btn-sm" @click="resetImage">Rimuovi</button>
          <button class="btn btn-primary btn-sm" @click="applyCrop">Applica ritaglio</button>
        </div>
      </div>

      <div v-if="state === 'cropped'" class="cropped-result">
        <img :src="croppedDataUrl" class="cropped-preview" />
        <button class="btn btn-sm btn-outline" @click="$emit('change', croppedDataUrl)">Conferma logo</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'

const emit = defineEmits(['change'])
const props = defineProps({ modelValue: { type: String, default: '' } })

const ASPECT = 8 / 5.5

const imageSrc = ref('')
const imgRef = ref(null)
const viewportRef = ref(null)
const imgNatural = ref({ w: 0, h: 0 })

const vpW = ref(360)
const vpH = ref(280)
const zoom = ref(1)
const panX = ref(0)
const panY = ref(0)
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0, px: 0, py: 0 })
const croppedDataUrl = ref('')
const finalW = ref(0)
const finalH = ref(0)
const isReplacing = ref(false)

const hasOriginal = computed(() => !!props.modelValue)

const state = computed(() => {
  if (croppedDataUrl.value) return 'cropped'
  if (imageSrc.value) return 'cropping'
  if (props.modelValue && !isReplacing.value) return 'preview'
  return 'empty'
})

const cropBoxStyle = computed(() => {
  const w = vpW.value * 0.85
  const h = w / ASPECT
  if (h > vpH.value * 0.85) {
    const h2 = vpH.value * 0.85
    const w2 = h2 * ASPECT
    return { width: w2 + 'px', height: h2 + 'px' }
  }
  return { width: w + 'px', height: h + 'px' }
})

const maskStyle = computed(() => {
  const cw = parseFloat(cropBoxStyle.value.width)
  const ch = parseFloat(cropBoxStyle.value.height)
  const cx = (vpW.value - cw) / 2
  const cy = (vpH.value - ch) / 2
  return {
    clipPath: `polygon(
      0% 0%, 100% 0%, 100% 100%, 0% 100%,
      0% ${cy}px, ${cx}px ${cy}px, ${cx}px ${cy + ch}px,
      ${cx + cw}px ${cy + ch}px, ${cx + cw}px ${cy}px,
      100% ${cy}px
    )`
  }
})

const imageLayerStyle = computed(() => ({
  transform: `translate(${panX.value}px, ${panY.value}px) scale(${zoom.value})`,
  cursor: isDragging.value ? 'grabbing' : 'grab',
}))

function startReplace() {
  isReplacing.value = true
  imageSrc.value = ''
  croppedDataUrl.value = ''
}

function cancelReplace() {
  isReplacing.value = false
}

function onFileSelect(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    imageSrc.value = ev.target.result
    croppedDataUrl.value = ''
    nextTick(() => {
      if (imgRef.value?.complete) centerImage()
    })
  }
  reader.readAsDataURL(file)
}

function onImgLoad() {
  imgNatural.value = { w: imgRef.value.naturalWidth, h: imgRef.value.naturalHeight }
  centerImage()
}

function centerImage() {
  zoom.value = 1
  panX.value = 0
  panY.value = 0
  fitToViewport()
}

function fitToViewport() {
  if (!imgRef.value) return
  const cw = parseFloat(cropBoxStyle.value.width)
  const ch = parseFloat(cropBoxStyle.value.height)
  const scaleX = cw / imgNatural.value.w
  const scaleY = ch / imgNatural.value.h
  zoom.value = Math.max(scaleX, scaleY)
}

function getCropBox() {
  const cw = parseFloat(cropBoxStyle.value.width)
  const ch = parseFloat(cropBoxStyle.value.height)
  const cx = (vpW.value - cw) / 2
  const cy = (vpH.value - ch) / 2
  return { cx, cy, cw, ch }
}

function applyCrop() {
  const { cx, cy, cw, ch } = getCropBox()
  const imgDisplayW = imgNatural.value.w * zoom.value
  const imgDisplayH = imgNatural.value.h * zoom.value
  const imgLeftInVp = (vpW.value - imgDisplayW) / 2 + panX.value
  const imgTopInVp = (vpH.value - imgDisplayH) / 2 + panY.value

  const sx = (cx - imgLeftInVp) / zoom.value
  const sy = (cy - imgTopInVp) / zoom.value
  const sw = cw / zoom.value
  const sh = ch / zoom.value

  const canvas = document.createElement('canvas')
  const outW = 320
  const outH = Math.round(outW / ASPECT)
  canvas.width = outW
  canvas.height = outH
  const ctx = canvas.getContext('2d')
  ctx.drawImage(imgRef.value, sx, sy, sw, sh, 0, 0, outW, outH)

  const png = canvas.toDataURL('image/png')
  const jpeg = canvas.toDataURL('image/jpeg', 0.8)
  croppedDataUrl.value = png.length <= jpeg.length ? png : jpeg
  finalW.value = outW
  finalH.value = outH
}

function resetImage() {
  imageSrc.value = ''
  croppedDataUrl.value = ''
  panX.value = 0
  panY.value = 0
  zoom.value = 1
}

function onPointerDown(e) {
  isDragging.value = true
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  dragStart.value = { x: clientX, y: clientY, px: panX.value, py: panY.value }

  const onMove = (ev) => {
    const cx = ev.touches ? ev.touches[0].clientX : ev.clientX
    const cy = ev.touches ? ev.touches[0].clientY : ev.clientY
    panX.value = dragStart.value.px + (cx - dragStart.value.x)
    panY.value = dragStart.value.py + (cy - dragStart.value.y)
  }

  const onUp = () => {
    isDragging.value = false
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
    window.removeEventListener('touchmove', onMove)
    window.removeEventListener('touchend', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
  window.addEventListener('touchmove', onMove, { passive: false })
  window.addEventListener('touchend', onUp)
}
</script>

<style scoped>
.logo-cropper {
  width: 100%;
}
.cropper-preview-only {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
}
.preview-img {
  max-width: 160px;
  height: auto;
  border-radius: 8px;
  border: 1px solid var(--border);
  object-fit: contain;
}
.cropper-empty {
  text-align: center;
  padding: 20px;
  border: 2px dashed var(--border);
  border-radius: var(--radius);
}
.upload-trigger {
  color: white !important;
}
.empty-hint {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 8px;
}
.cropper-workspace {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.cropper-viewport {
  position: relative;
  overflow: hidden;
  background: #eee;
  border-radius: var(--radius);
  margin: 0 auto;
  max-width: 100%;
}
.cropper-image-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1;
}
.cropper-image-layer img {
  max-width: none;
  width: auto;
  height: auto;
  display: block;
  user-select: none;
  -webkit-user-drag: none;
}
.cropper-mask {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 2;
  pointer-events: none;
}
.crop-box {
  position: absolute;
  z-index: 3;
  border: 2px solid white;
  box-shadow: 0 0 0 1px rgba(0,0,0,0.3);
  pointer-events: none;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
.crop-handle {
  position: absolute;
  width: 12px;
  height: 12px;
  border: 2px solid white;
  background: var(--primary);
  border-radius: 2px;
  z-index: 4;
}
.crop-handle.nw { top: -6px; left: -6px; }
.crop-handle.ne { top: -6px; right: -6px; }
.crop-handle.sw { bottom: -6px; left: -6px; }
.crop-handle.se { bottom: -6px; right: -6px; }
.cropper-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.zoom-slider {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 160px;
}
.zoom-slider input[type="range"] {
  flex: 1;
}
.zoom-label, .zoom-pct {
  font-size: 12px;
  color: var(--text-secondary);
  white-space: nowrap;
}
.cropper-actions {
  display: flex;
  gap: 8px;
}
.cropped-result {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
.cropped-preview {
  width: 80px;
  height: auto;
  border-radius: 6px;
  object-fit: contain;
}
</style>
