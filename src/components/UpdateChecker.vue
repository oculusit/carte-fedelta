<template>
  <Teleport to="body">
    <div v-if="showUpdatePopup" class="update-overlay">
      <div class="update-popup">
        <div class="update-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="16 12 12 8 8 12"/>
            <line x1="12" y1="16" x2="12" y2="8"/>
          </svg>
        </div>
        <h2>Aggiornamento disponibile</h2>
        <p class="update-text">
          Una nuova versione di <strong>FidAPPti</strong> è disponibile:<br/>
          <span class="update-version">{{ latestVersion }}</span>
        </p>
        <div class="update-actions">
          <button class="btn-download" @click="openDownload">Scarica</button>
          <button class="btn-later" @click="dismiss">Lo faccio più tardi!</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { httpFetch } from '../services/http.js'

const CURRENT_VERSION = '1.2.1'
const showUpdatePopup = ref(false)
const latestVersion = ref('')
const downloadUrl = ref('')

const DISMISS_KEY = 'update_dismissed_version'

function isDismissed(version) {
  return localStorage.getItem(DISMISS_KEY) === version
}

function dismiss() {
  showUpdatePopup.value = false
  localStorage.setItem(DISMISS_KEY, latestVersion.value)
}

function openDownload() {
  if (downloadUrl.value) {
    window.open(downloadUrl.value, '_blank')
  }
}

onMounted(async () => {
  try {
    const res = await httpFetch('./api/version.php', { method: 'GET' })
    if (!res.ok) return
    const data = await res.json()
    if (data.version && data.version !== CURRENT_VERSION && !isDismissed(data.version)) {
      latestVersion.value = data.version
      downloadUrl.value = data.download_url || ''
      showUpdatePopup.value = true
    }
  } catch {}
})
</script>

<style scoped>
.update-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 24px;
}

.update-popup {
  background: var(--card-bg, #fff);
  border-radius: 16px;
  padding: 32px 24px;
  max-width: 360px;
  width: 100%;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.update-icon {
  margin-bottom: 16px;
}

.update-popup h2 {
  font-size: 20px;
  margin: 0 0 12px;
  color: var(--text, #1a1a2e);
}

.update-text {
  font-size: 14px;
  color: var(--text-secondary, #666);
  margin: 0 0 24px;
  line-height: 1.5;
}

.update-version {
  display: inline-block;
  margin-top: 8px;
  padding: 4px 12px;
  border-radius: 8px;
  background: var(--primary-bg, #e8f0fe);
  color: var(--primary, #1a73e8);
  font-weight: 700;
  font-size: 16px;
}

.update-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn-download {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 10px;
  background: var(--primary, #1a73e8);
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-download:hover {
  background: var(--primary-dark, #1557b0);
}

.btn-later {
  width: 100%;
  padding: 12px;
  border: 2px solid var(--border, #ddd);
  border-radius: 10px;
  background: transparent;
  color: var(--text-secondary, #666);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s;
}

.btn-later:hover {
  border-color: var(--primary, #1a73e8);
}
</style>
