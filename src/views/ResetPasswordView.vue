<template>
  <div class="reset-page">
    <div class="reset-card card">
      <div class="reset-header">
        <div class="reset-icon">🔑</div>
        <h2>Reimposta password</h2>
      </div>

      <div v-if="done" class="reset-done">
        <p>{{ done }}</p>
        <button class="btn btn-primary btn-block" @click="$router.push('/login')">Vai al login</button>
      </div>

      <form v-else @submit.prevent="doReset" class="reset-form">
        <div class="input-group">
          <label>Nuova password</label>
          <input v-model="password" type="password" placeholder="Minimo 6 caratteri" required minlength="6" />
        </div>
        <div class="input-group">
          <label>Conferma password</label>
          <input v-model="confirm" type="password" placeholder="Ripeti la password" required minlength="6" />
        </div>
        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Reimpostazione...' : 'Reimposta password' }}
        </button>
      </form>

      <div v-if="error" class="reset-error">{{ error }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api.js'

const route = useRoute()
const token = ref('')
const password = ref('')
const confirm = ref('')
const loading = ref(false)
const error = ref('')
const done = ref('')

onMounted(() => {
  token.value = route.params.token || ''
  if (!token.value) {
    error.value = 'Token mancante. Usa il link ricevuto via email.'
  }
})

async function doReset() {
  if (password.value !== confirm.value) {
    error.value = 'Le password non coincidono'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await api.auth.resetPassword(token.value, password.value)
    done.value = res.message || 'Password reimpostata con successo!'
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.reset-page {
  max-width: 400px;
  margin: 48px auto;
}
.reset-card {
  padding: 32px 24px;
}
.reset-header {
  text-align: center;
  margin-bottom: 24px;
}
.reset-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.reset-header h2 {
  margin: 0;
  font-size: 18px;
}
.reset-form {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.reset-done {
  text-align: center;
}
.reset-done p {
  font-size: 14px;
  color: var(--success);
  margin-bottom: 16px;
  font-weight: 600;
}
.reset-error {
  background: #fff0f0;
  color: var(--danger);
  padding: 10px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  text-align: center;
  margin-top: 16px;
}
</style>
