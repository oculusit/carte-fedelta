<template>
  <div class="login-page">
    <div class="login-card card">
      <div class="login-header">
        <div class="login-icon">🔐</div>
        <h2>{{ step === 'login' ? 'Accedi' : step === 'register' ? 'Registrati' : 'Password dimenticata' }}</h2>
        <p class="login-sub" v-if="step === 'login'">Accedi per sincronizzare le tue carte</p>
      </div>

      <!-- Login form -->
      <form v-if="step === 'login'" @submit.prevent="doLogin" class="login-form">
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" placeholder="la@tua.email" required />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="password" type="password" placeholder="••••••" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Accesso...' : 'Accedi' }}
        </button>
        <p class="form-switch">
          <a href="#" @click.prevent="step = 'forgot-password'">Password dimenticata?</a>
        </p>
        <p class="form-switch">
          Non hai un account?
          <a href="#" @click.prevent="step = 'register'">Registrati</a>
        </p>
      </form>

      <!-- Register form -->
      <form v-else-if="step === 'register' && !registerDone" @submit.prevent="doRegister" class="login-form">
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" placeholder="la@tua.email" required />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="password" type="password" placeholder="Minimo 6 caratteri" required minlength="6" />
        </div>
        <label class="checkbox-label">
          <input v-model="privacyAccepted" type="checkbox" required />
          <span>Ho letto e accetto l'<a href="#/privacy" target="_blank">informativa sulla privacy</a></span>
        </label>
        <button type="submit" class="btn btn-success btn-block" :disabled="loading || !privacyAccepted">
          {{ loading ? 'Registrazione...' : 'Registrati' }}
        </button>
        <p class="form-switch">
          Hai già un account?
          <a href="#" @click.prevent="step = 'login'">Accedi</a>
        </p>
      </form>

      <!-- Forgot password form -->
      <form v-else-if="step === 'forgot-password'" @submit.prevent="doForgotPassword" class="login-form">
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" placeholder="la@tua.email" required />
        </div>
        <p class="fa-hint">Riceverai un link per il reset via email.</p>
        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Invio...' : 'Invia link reset' }}
        </button>
        <p class="form-switch">
          <a href="#" @click.prevent="step = 'login'">Torna al login</a>
        </p>
      </form>

      <div v-if="error" class="login-error">{{ error }}</div>
      <div v-if="registerDone" class="login-success">{{ registerDone }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../services/auth.js'
import { isSupabaseConfigured } from '../services/supabase.js'
import { getSupabaseClient } from '../services/supabase.js'

const router = useRouter()

const step = ref('login')
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const privacyAccepted = ref(false)
const registerDone = ref('')

async function doLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function doRegister() {
  loading.value = true
  error.value = ''
  registerDone.value = ''
  try {
    const res = await auth.register(email.value, password.value)
    registerDone.value = res?.user ? 'Registrazione completata. Controlla la tua email per confermare.' : 'Registrazione completata.'
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function doForgotPassword() {
  loading.value = true
  error.value = ''
  try {
    const supabase = getSupabaseClient()
    if (!supabase) throw new Error('Supabase non configurato')
    const { error: err } = await supabase.auth.resetPasswordForEmail(email.value)
    if (err) throw err
    error.value = 'Email di reset inviata. Controlla la tua casella di posta.'
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  max-width: 400px;
  margin: 48px auto;
}

.login-card {
  padding: 32px 24px;
}

.login-header {
  text-align: center;
  margin-bottom: 24px;
}

.login-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.login-sub {
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 4px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-switch {
  text-align: center;
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 16px;
}

.form-switch a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
}

.fa-hint {
  font-size: 12px;
  color: var(--text-secondary);
  text-align: center;
  margin-bottom: 8px;
}

.login-error {
  background: #fff0f0;
  color: var(--danger);
  padding: 10px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  text-align: center;
  margin-top: 16px;
}

.login-success {
  background: #f0fff0;
  color: var(--success);
  padding: 10px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  text-align: center;
  margin-top: 16px;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: var(--text-secondary);
  margin: 8px 0;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin-top: 2px;
  flex-shrink: 0;
}

.checkbox-label a {
  color: var(--primary);
  text-decoration: underline;
}

.login-back {
  margin-top: 24px;
}
</style>
