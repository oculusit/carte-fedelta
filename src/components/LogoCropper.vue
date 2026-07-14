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
      <div
        class="cropper-viewport"
        ref="viewportRef"
        :style="{ width: vpW + 'px', height: vpH + 'px' }"
        @touchstart.prevent="onViewportTouchStart"
        @touchmove.prevent="onViewportTouchMove"
        @touchend.prevent="onViewportTouchEnd"
        @mousedown.prevent="onImageMouseDown"
      >
        <div class="cropper-image-layer" :style="imageLayerStyle">
          <img :src="imageSrc" draggable="false" ref="imgRef" @load="onImgLoad" />
        </div>
        <div class="cropper-mask" :style="maskStyle"></div>
        <div class="crop-box" :style="cropBoxStyle"></div>
      </div>

      <div class="cropper-controls">
        <div class="zoom-slider">
          <button class="zoom-btn" @click="zoomBy(-0.1)">−</button>
          <input type="range" min="0.2" max="4" step="0.05" :value="zoom" @input="zoom = +$event.target.value" />
          <button class="zoom-btn" @click="zoomBy(0.1)">+</button>
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
import { ref, computed, nextTick, watch, onBeforeUnmount } from 'vue'

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
const croppedDataUrl = ref('')
const isReplacing = ref(false)

const cropW = ref(300)
const cropH = ref(206)

const hasOriginal = computed(() => !!props.modelValue)

const state = computed(() => {
  if (croppedDataUrl.value) return 'cropped'
  if (imageSrc.value) return 'cropping'
  if (props.modelValue && !isReplacing.value) return 'preview'
  return 'empty'
})

let _orientationLocked = false

async function lockLandscape() {
  try {
    if (screen.orientation && screen.orientation.lock) {
      await screen.orientation.lock('landscape')
      _orientationLocked = true
    }
  } catch {}
}

async function unlockOrientation() {
  try {
    if (_orientationLocked && screen.orientation && screen.orientation.unlock) {
      screen.orientation.unlock()
      _orientationLocked = false
    }
  } catch {}
}

watch(state, (s) => {
  if (s === 'cropping') lockLandscape()
  else unlockOrientation()
})

onBeforeUnmount(() => { unlockOrientation() })

const cropBoxStyle = computed(() => ({
  width: cropW.value + 'px',
  height: cropH.value + 'px',
}))

const maskStyle = computed(() => {
  const cw = cropW.value, ch = cropH.value
  const cx = (vpW.value - cw) / 2, cy = (vpH.value - ch) / 2
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
}))

function zoomBy(delta) {
  zoom.value = Math.min(4, Math.max(0.1, Math.round((zoom.value + delta) * 100) / 100))
}

// ── Image drag (mouse) ──
let _dragData = null

function onImageMouseDown(e) {
  _dragData = { sx: e.clientX, sy: e.clientY, px: panX.value, py: panY.value }
  window.addEventListener('mousemove', onGlobalMouseMove)
  window.addEventListener('mouseup', onGlobalMouseUp)
}

function onGlobalMouseMove(e) {
  if (!_dragData) return
  panX.value = _dragData.px + (e.clientX - _dragData.sx)
  panY.value = _dragData.py + (e.clientY - _dragData.sy)
}

function onGlobalMouseUp() {
  _dragData = null
  window.removeEventListener('mousemove', onGlobalMouseMove)
  window.removeEventListener('mouseup', onGlobalMouseUp)
}

// ── Image drag + pinch (touch) ──
let _touchData = null

function onViewportTouchStart(e) {
  if (e.touches.length === 2) {
    const d = getTouchDist(e.touches)
    const mid = getTouchMid(e.touches)
    _touchData = { type: 'pinch', dist: d, zoom: zoom.value, midX: mid.x, midY: mid.y, px: panX.value, py: panY.value }
  } else if (e.touches.length === 1) {
    const t = e.touches[0]
    _touchData = { type: 'pan', sx: t.clientX, sy: t.clientY, px: panX.value, py: panY.value }
  }
}

function onViewportTouchMove(e) {
  if (!_touchData) return
  if (_touchData.type === 'pinch' && e.touches.length === 2) {
    const newDist = getTouchDist(e.touches)
    const newMid = getTouchMid(e.touches)
    const scale = newDist / _touchData.dist
    zoom.value = Math.min(4, Math.max(0.1, _touchData.zoom * scale))
    panX.value = _touchData.px + (newMid.x - _touchData.midX)
    panY.value = _touchData.py + (newMid.y - _touchData.midY)
  } else if (_touchData.type === 'pan' && e.touches.length === 1) {
    const t = e.touches[0]
    panX.value = _touchData.px + (t.clientX - _touchData.sx)
    panY.value = _touchData.py + (t.clientY - _touchData.sy)
  }
}

function onViewportTouchEnd() {
  _touchData = null
}

function getTouchDist(touches) {
  const dx = touches[0].clientX - touches[1].clientX
  const dy = touches[0].clientY - touches[1].clientY
  return Math.sqrt(dx * dx + dy * dy)
}

function getTouchMid(touches) {
  return {
    x: (touches[0].clientX + touches[1].clientX) / 2,
    y: (touches[0].clientY + touches[1].clientY) / 2,
  }
}

// ── File select ──
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
  if (!imgRef.value || !imgNatural.value.w) return
  const scaleX = cropW.value / imgNatural.value.w
  const scaleY = cropH.value / imgNatural.value.h
  zoom.value = Math.max(scaleX, scaleY) * 1.1
}

function getCropBox() {
  const cw = cropW.value, ch = cropH.value
  const cx = (vpW.value - cw) / 2, cy = (vpH.value - ch) / 2
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
}

function resetImage() {
  imageSrc.value = ''
  croppedDataUrl.value = ''
  panX.value = 0
  panY.value = 0
  zoom.value = 1
}

function startReplace() {
  isReplacing.value = true
  imageSrc.value = ''
  croppedDataUrl.value = ''
}

function cancelReplace() {
  isReplacing.value = false
}
</script>

<style scoped>
.logo-cropper { width: 100%; }
.cropper-preview-only { display: flex; flex-direction: column; gap: 10px; align-items: center; }
.preview-img { max-width: 160px; height: auto; border-radius: 8px; border: 1px solid var(--border); object-fit: contain; }
.cropper-empty { text-align: center; padding: 20px; border: 2px dashed var(--border); border-radius: var(--radius); }
.upload-trigger { color: white !important; }
.empty-hint { font-size: 12px; color: var(--text-secondary); margin-top: 8px; }
.cropper-workspace { display: flex; flex-direction: column; gap: 10px; }
.cropper-viewport {
  position: relative; overflow: hidden; background: #1a1a2e;
  border-radius: var(--radius); margin: 0 auto; max-width: 100%;
  touch-action: none;
}
.cropper-image-layer {
  position: absolute; top: 0; left: 0; width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center; z-index: 1;
}
.cropper-image-layer img {
  max-width: none; width: auto; height: auto; display: block;
  user-select: none; -webkit-user-drag: none; pointer-events: none;
}
.cropper-mask { position: absolute; inset: 0; background: rgba(0,0,0,.55); z-index: 2; pointer-events: none; }
.crop-box {
  position: absolute; z-index: 3; border: 2px solid white;
  box-shadow: 0 0 0 1px rgba(0,0,0,.3); pointer-events: none;
  top: 50%; left: 50%; transform: translate(-50%,-50%);
}
.cropper-controls { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.zoom-slider { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 180px; }
.zoom-slider input[type="range"] { flex: 1; min-width: 0; }
.zoom-btn {
  width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--border);
  background: var(--card-bg); color: var(--text); font-size: 18px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  flex-shrink: 0; line-height: 1;
}
.zoom-btn:active { background: var(--primary); color: #fff; border-color: var(--primary); }
.zoom-pct { font-size: 12px; color: var(--text-secondary); white-space: nowrap; min-width: 36px; text-align: right; }
.cropper-actions { display: flex; gap: 8px; }
.cropped-result { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border); }
.cropped-preview { width: 80px; height: auto; border-radius: 6px; object-fit: contain; }
</style>
