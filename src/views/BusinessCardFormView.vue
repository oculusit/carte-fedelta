<template>
  <div class="form-page">
    <div class="card form-card">
      <h2 class="form-title">{{ isEdit ? 'Modifica Biglietto' : 'Nuovo Biglietto' }}</h2>

      <div class="input-group">
        <label>Nome *</label>
        <input v-model="form.first_name" type="text" placeholder="Nome" />
      </div>

      <div class="input-group">
        <label>Cognome *</label>
        <input v-model="form.last_name" type="text" placeholder="Cognome" />
      </div>

      <div class="input-group">
        <label>Azienda</label>
        <input v-model="form.org" type="text" placeholder="Nome azienda / organizzazione" />
      </div>

      <div class="input-group">
        <label>Ruolo</label>
        <input v-model="form.role" type="text" placeholder="es. Responsabile vendite" />
      </div>

      <div class="input-group">
        <label>Telefono personale</label>
        <input v-model="form.phone_personal" type="tel" placeholder="+39 ..." />
      </div>

      <div class="input-group">
        <label>Telefono business</label>
        <input v-model="form.phone_business" type="tel" placeholder="+39 ..." />
      </div>

      <div class="input-group">
        <label>E-mail</label>
        <input v-model="form.email" type="email" placeholder="nome@azienda.it" />
      </div>

      <div class="input-group">
        <label>Sito web</label>
        <input v-model="form.website" type="text" placeholder="www.azienda.it" />
      </div>

      <div class="input-group">
        <label>Indirizzo</label>
        <input v-model="form.address" type="text" placeholder="Via, numero" />
      </div>

      <div class="input-row">
        <div class="input-group input-grow">
          <label>Città</label>
          <input v-model="form.city" type="text" placeholder="Città" />
        </div>
        <div class="input-group input-small">
          <label>Prov.</label>
          <input
            v-model="form.province"
            type="text"
            placeholder="RM"
            maxlength="2"
            style="text-transform: uppercase"
          />
        </div>
        <div class="input-group input-small">
          <label>CAP</label>
          <input v-model="form.postal_code" type="text" placeholder="CAP" />
        </div>
      </div>

      <div class="input-group">
        <label>Paese</label>
        <input v-model="form.country" type="text" placeholder="Italia" />
      </div>

      <div class="input-group">
        <label>Colore</label>
        <div class="color-row">
          <input v-model="form.color" type="color" class="color-input" />
          <span class="color-value">{{ form.color }}</span>
        </div>
      </div>

      <div class="input-group">
        <label>Foto (opzionale)</label>
        <div v-if="form.avatar_data" class="avatar-preview">
          <img :src="form.avatar_data" alt="Foto profilo" />
          <button type="button" class="btn btn-outline btn-sm" @click="form.avatar_data = ''">Rimuovi foto</button>
        </div>
        <div v-else>
          <input ref="avatarInput" type="file" accept="image/*" @change="onAvatarFile" hidden />
          <button type="button" class="btn btn-outline" @click="avatarInput?.click()">Aggiungi foto</button>
        </div>
      </div>

      <div class="input-group">
        <label>Note</label>
        <textarea v-model="form.notes" rows="3" placeholder="Note opzionali..."></textarea>
      </div>

      <div class="form-actions">
        <button class="btn btn-outline" @click="$router.back()">Annulla</button>
        <button class="btn btn-primary" @click="save" :disabled="saving">
          {{ saving ? 'Salvataggio...' : 'Salva' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBusinessCardsStore } from '../stores/businessCards.js'
import { toast } from '../services/toast.js'

const route = useRoute()
const router = useRouter()
const bcStore = useBusinessCardsStore()
const isEdit = computed(() => !!route.params.id)

const saving = ref(false)
const avatarInput = ref(null)

const form = ref({
  first_name: '',
  last_name: '',
  org: '',
  role: '',
  phone_personal: '',
  phone_business: '',
  email: '',
  website: '',
  address: '',
  city: '',
  province: '',
  postal_code: '',
  country: '',
  notes: '',
  color: '#1a73e8',
  avatar_data: '',
})

function compressImage(file, maxDim = 512, quality = 0.8) {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      const scale = Math.min(1, maxDim / Math.max(img.width, img.height))
      const w = Math.round(img.width * scale)
      const h = Math.round(img.height * scale)
      const canvas = document.createElement('canvas')
      canvas.width = w
      canvas.height = h
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, w, h)
      URL.revokeObjectURL(url)
      resolve(canvas.toDataURL('image/jpeg', quality))
    }
    img.onerror = (e) => {
      URL.revokeObjectURL(url)
      reject(e)
    }
    img.src = url
  })
}

async function onAvatarFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  try {
    form.value.avatar_data = await compressImage(file)
    toast.show('Foto aggiunta', 'success')
  } catch {
    toast.show('Errore caricamento immagine', 'error')
  }
  e.target.value = ''
}

onMounted(async () => {
  window.scrollTo(0, 0)
  if (isEdit.value) {
    const card = await bcStore.getCard(route.params.id)
    if (card) {
      form.value = { ...form.value, ...card }
    }
  }
})

async function save() {
  const first = form.value.first_name.trim()
  const last = form.value.last_name.trim()
  if (!first && !last) {
    toast.show('Inserisci almeno il nome o il cognome', 'error')
    return
  }
  const data = {
    ...form.value,
    first_name: first,
    last_name: last,
    org: form.value.org.trim(),
    role: form.value.role.trim(),
    phone_personal: form.value.phone_personal.trim(),
    phone_business: form.value.phone_business.trim(),
    email: form.value.email.trim(),
    website: form.value.website.trim(),
    address: form.value.address.trim(),
    city: form.value.city.trim(),
    province: form.value.province.trim().toUpperCase(),
    postal_code: form.value.postal_code.trim(),
    country: form.value.country.trim(),
    notes: form.value.notes.trim(),
  }
  if (!data.avatar_data) delete data.avatar_data
  saving.value = true
  try {
    if (isEdit.value) {
      await bcStore.updateCard(route.params.id, data)
    } else {
      await bcStore.createCard(data)
    }
    router.push({ path: '/', query: { t: 'business' } })
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
  gap: 12px;
  align-items: stretch;
}
.input-grow {
  flex: 1;
  min-width: 0;
}
.input-small {
  flex: 0 0 74px;
}
.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
}
.input-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
}
input, textarea, select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--bg);
  color: var(--text);
}
input:focus, textarea:focus {
  outline: none;
  border-color: var(--primary);
}
textarea {
  resize: vertical;
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
.avatar-preview {
  display: flex;
  align-items: center;
  gap: 12px;
}
.avatar-preview img {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--border);
}
.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}
.form-actions .btn {
  flex: 1;
}
.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}
</style>
