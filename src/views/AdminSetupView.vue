<template>
  <div class="setup">
    <div class="setup-header">
      <h1>Crea amministratore</h1>
      <p>Non è stato trovato un account amministratore. Creane uno per gestire l'applicazione.</p>
    </div>

    <div class="card setup-card">
      <div class="input-group">
        <label>Email amministratore</label>
        <input v-model="email" type="email" placeholder="admin@esempio.com" />
      </div>
      <div class="input-group">
        <label>Password</label>
        <input v-model="password" type="password" placeholder="Almeno 6 caratteri" />
      </div>

      <div v-if="error" class="test-result error">{{ error }}</div>
      <div v-if="done" class="save-success">
        <p class="success-message">Amministratore creato!</p>
        <p class="success-desc">Ora puoi accedere con l'email {{ email }}</p>
        <router-link to="/login" class="btn btn-primary">Vai al login</router-link>
      </div>

      <div v-else class="nav-buttons">
        <button class="btn btn-success" @click="submit" :disabled="saving">
          {{ saving ? 'Creazione...' : 'Crea amministratore' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { setup } from '../services/setup.js'

const email = ref('')
const password = ref('')
const saving = ref(false)
const error = ref('')
const done = ref(false)

async function submit() {
  if (!email.value) { error.value = 'Inserisci un email'; return }
  if (password.value.length < 6) { error.value = 'Password almeno 6 caratteri'; return }
  saving.value = true
  error.value = ''
  try {
    const result = await setup.createAdmin(email.value, password.value)
    done.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.setup {
  max-width: 500px;
  margin: 60px auto;
  padding: 0 16px;
}
.setup-header {
  text-align: center;
  margin-bottom: 32px;
}
.setup-header h1 {
  font-size: 24px;
  margin-bottom: 8px;
}
.setup-header p {
  color: var(--text-secondary);
  font-size: 14px;
}
.setup-card {
  padding: 24px;
}
.input-group {
  margin-bottom: 16px;
}
.input-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 4px;
}
.input-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
}
.test-result {
  padding: 12px;
  border-radius: var(--radius);
  font-size: 13px;
  margin-bottom: 16px;
}
.test-result.error {
  background: #fde8e8;
  border: 1px solid #f5c6c6;
  color: #a11a1a;
}
.nav-buttons {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}
.save-success {
  text-align: center;
  padding: 24px 0;
}
.success-message {
  font-size: 18px;
  font-weight: 700;
  color: var(--success);
  margin-bottom: 8px;
}
.success-desc {
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: 20px;
}
</style>
