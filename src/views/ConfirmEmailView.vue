<template>
  <div class="confirm-page">
    <div class="card confirm-card">
      <div class="confirm-header">
        <div class="confirm-icon">📧</div>
        <h2>Conferma email</h2>
      </div>

      <div v-if="done" class="confirm-done">
        <p>{{ done }}</p>
        <button class="btn btn-primary btn-block" @click="$router.push('/login')">Vai al login</button>
      </div>

      <div v-else-if="error" class="confirm-error">
        <p>{{ error }}</p>
      </div>

      <div v-else class="confirm-loading">
        <p>Conferma in corso...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api.js'

const route = useRoute()
const done = ref('')
const error = ref('')

onMounted(async () => {
  const token = route.params.token || ''
  if (!token) {
    error.value = 'Token mancante.'
    return
  }
  try {
    const res = await api.auth.confirmEmail(token)
    done.value = res.message || 'Email confermata con successo!'
  } catch (e) {
    error.value = e.message
  }
})
</script>

<style scoped>
.confirm-page {
  max-width: 400px;
  margin: 48px auto;
}
.confirm-card {
  padding: 32px 24px;
  text-align: center;
}
.confirm-header {
  text-align: center;
  margin-bottom: 24px;
}
.confirm-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.confirm-header h2 {
  margin: 0;
  font-size: 18px;
}
.confirm-done p {
  font-size: 14px;
  color: var(--success);
  margin-bottom: 16px;
  font-weight: 600;
}
.confirm-error p {
  font-size: 14px;
  color: var(--danger);
  font-weight: 600;
}
.confirm-loading p {
  color: var(--text-secondary);
}
</style>
