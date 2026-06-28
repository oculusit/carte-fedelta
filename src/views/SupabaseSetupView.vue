<template>
  <div class="supabase-setup">
    <div class="card setup-card">
      <h2>Configura sincronizzazione cloud</h2>
      <p class="setup-desc">
        Collega il tuo account Supabase per sincronizzare le tue carte fedeltà su tutti i dispositivi.
        I tuoi dati rimangono privati e accessibili solo a te.
      </p>

      <div v-if="isConfigured" class="status-card status-ok">
        <span class="status-icon">✓</span>
        <div>
          <strong>Sincronizzazione configurata</strong>
          <p class="status-sub">Supabase: {{ maskedUrl }}</p>
        </div>
        <button class="btn btn-sm btn-outline" @click="disconnect" :disabled="disconnecting">
          {{ disconnecting ? '...' : 'Disconnetti' }}
        </button>
      </div>

      <div v-else-if="step === 0" class="step">
        <div class="step-header">
          <span class="step-number">1</span>
          <h3>Crea un account Supabase</h3>
        </div>
        <p>Vai su <a href="https://supabase.com" target="_blank" rel="noopener">supabase.com</a> e registrati gratuitamente (nessuna carta di credito).</p>
        <div class="step-actions">
          <button class="btn btn-primary" @click="step = 1">Fatto, passo successivo →</button>
        </div>
      </div>

      <div v-else-if="step === 1" class="step">
        <div class="step-header">
          <span class="step-number">2</span>
          <h3>Crea un nuovo progetto</h3>
        </div>
        <p>Dalla dashboard di Supabase, clicca su <strong>"New Project"</strong>.</p>
        <ul class="step-list">
          <li>Scegli un nome a piacere (es. "Carte Fedeltà")</li>
          <li>Imposta una password sicura per il database</li>
          <li>Scegli il server più vicino a te</li>
          <li>Attendi circa 1-2 minuti per la creazione</li>
        </ul>
        <div class="step-actions">
          <button class="btn btn-ghost" @click="step = 0">← Indietro</button>
          <button class="btn btn-primary" @click="step = 2">Fatto, passo successivo →</button>
        </div>
      </div>

      <div v-else-if="step === 2" class="step">
        <div class="step-header">
          <span class="step-number">3</span>
          <h3>Esegui lo script SQL</h3>
        </div>
        <p>Vai su <strong>"SQL Editor"</strong> nel menu di sinistra, clicca <strong>"New Query"</strong>, incolla il codice qui sotto e premi <strong>"Run"</strong>.</p>
        <div class="sql-box">
          <button class="copy-btn" @click="copySql">{{ copyText }}</button>
          <pre><code>{{ sqlScript }}</code></pre>
        </div>
        <div class="step-actions">
          <button class="btn btn-ghost" @click="step = 1">← Indietro</button>
          <button class="btn btn-primary" @click="step = 3">Eseguito, passo successivo →</button>
        </div>
      </div>

      <div v-else-if="step === 3" class="step">
        <div class="step-header">
          <span class="step-number">4</span>
          <h3>Inserisci le credenziali</h3>
        </div>
        <p>Vai su <strong>"Project Settings" → "API"</strong> nel menu di sinistra. Copia i valori qui sotto.</p>
        <div class="form-group">
          <label>Project URL</label>
          <input v-model="formUrl" type="url" placeholder="https://xxxxx.supabase.co" />
        </div>
        <div class="form-group">
          <label>anon public key</label>
          <input v-model="formKey" type="text" placeholder="eyJhbGciOiJIUzI1NiIs..." />
        </div>
        <p v-if="testResult === 'testing'" class="test-status testing">Verifica in corso...</p>
        <p v-if="testResult === 'ok'" class="test-status ok">Connessione riuscita! ✅</p>
        <p v-if="testResult === 'error'" class="test-status error">Errore: {{ testError }}</p>
        <div class="step-actions">
          <button class="btn btn-ghost" @click="step = 2">← Indietro</button>
          <button class="btn btn-primary" :disabled="!formUrl || !formKey || testing" @click="testAndSave">
            {{ testing ? 'Verifica...' : 'Verifica e salva' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="isConfigured" class="card info-card">
      <h3>Come funziona</h3>
      <ul class="info-list">
        <li>I dati vengono salvati localmente e sincronizzati con Supabase quando sei online</li>
        <li>Le modifiche offline si accumulano e vengono inviate automaticamente quando torni online</li>
        <li>I loghi dei negozi vengono scaricati dal server centrale e messi in cache</li>
        <li>La condivisione famiglia avviene tramite il tuo Supabase</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { isSupabaseConfigured, getSupabaseConfig, saveSupabaseConfig, clearSupabaseConfig, testSupabaseConnection, SUPABASE_SETUP_SQL } from '../services/supabase.js'
import { toast } from '../services/toast.js'

const step = ref(0)
const formUrl = ref('')
const formKey = ref('')
const testing = ref(false)
const testResult = ref(null)
const testError = ref('')
const copyText = ref('Copia codice')
const disconnecting = ref(false)

const isConfigured = ref(false)

onMounted(() => {
  isConfigured.value = isSupabaseConfigured()
})

const maskedUrl = computed(() => {
  const config = getSupabaseConfig()
  if (!config?.url) return ''
  try {
    const u = new URL(config.url)
    return u.protocol + '//' + u.hostname.slice(0, 4) + '...' + u.hostname.slice(-4)
  } catch {
    return config.url.slice(0, 20) + '...'
  }
})

const sqlScript = computed(() => SUPABASE_SETUP_SQL)

async function copySql() {
  try {
    await navigator.clipboard.writeText(SUPABASE_SETUP_SQL)
    copyText.value = 'Copiato!'
    setTimeout(() => { copyText.value = 'Copia codice' }, 2000)
  } catch {
    toast.show('Errore durante la copia', 'error')
  }
}

async function testAndSave() {
  if (!formUrl.value || !formKey.value) return
  testing.value = true
  testResult.value = 'testing'
  testError.value = ''
  try {
    const result = await testSupabaseConnection(formUrl.value.trim(), formKey.value.trim())
    if (result.ok) {
      saveSupabaseConfig({ url: formUrl.value.trim(), anonKey: formKey.value.trim() })
      testResult.value = 'ok'
      isConfigured.value = true
      toast.show('Connessione a Supabase riuscita!', 'success')
    } else {
      testResult.value = 'error'
      testError.value = result.error
    }
  } catch (e) {
    testResult.value = 'error'
    testError.value = e.message
  } finally {
    testing.value = false
  }
}

async function disconnect() {
  if (!confirm('Rimuovere la configurazione Supabase? I dati locali rimarranno sul dispositivo.')) return
  disconnecting.value = true
  try {
    clearSupabaseConfig()
    isConfigured.value = false
    step.value = 0
    toast.show('Configurazione rimossa', 'info')
  } finally {
    disconnecting.value = false
  }
}
</script>

<style scoped>
.supabase-setup {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.setup-card {
  padding: 24px;
}

.setup-card h2 {
  font-size: 20px;
  margin-bottom: 8px;
}

.setup-desc {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.5;
  margin-bottom: 20px;
}

.status-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: var(--radius);
  margin-bottom: 8px;
}

.status-ok {
  background: #e6f4ea;
  border: 1px solid #b7e1bd;
}

.status-icon {
  font-size: 24px;
  font-weight: bold;
  color: #1e7e34;
}

.status-sub {
  font-size: 12px;
  color: #1e7e34;
  margin-top: 2px;
}

.step {
  margin-top: 8px;
}

.step-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.step-header h3 {
  font-size: 16px;
  margin: 0;
}

.step p {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.5;
  margin-bottom: 12px;
}

.step-list {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.8;
  padding-left: 20px;
  margin-bottom: 16px;
}

.step-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.sql-box {
  position: relative;
  background: #1e1e1e;
  border-radius: var(--radius);
  margin: 12px 0;
  max-height: 300px;
  overflow: auto;
}

.sql-box pre {
  padding: 16px;
  margin: 0;
}

.sql-box code {
  font-family: monospace;
  font-size: 12px;
  line-height: 1.5;
  color: #d4d4d4;
  white-space: pre;
}

.copy-btn {
  position: sticky;
  top: 8px;
  left: calc(100% - 100px);
  padding: 6px 14px;
  font-size: 12px;
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 4px;
  background: rgba(30,30,30,0.9);
  color: #ccc;
  cursor: pointer;
  z-index: 1;
  margin: 8px 8px 0 0;
  float: right;
}

.copy-btn:hover {
  background: rgba(60,60,60,0.9);
  color: white;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 4px;
  color: var(--text-secondary);
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  font-family: monospace;
  background: var(--bg);
  color: var(--text);
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: var(--primary);
}

.test-status {
  font-size: 13px;
  margin-bottom: 12px;
}

.test-status.testing { color: var(--text-secondary); }
.test-status.ok { color: #1e7e34; font-weight: 500; }
.test-status.error { color: #c5221f; }

.btn-ghost {
  background: none;
  border: none;
  color: var(--primary);
  cursor: pointer;
  font-size: 14px;
  padding: 8px 16px;
}

.btn-ghost:hover {
  text-decoration: underline;
}

.info-card {
  padding: 20px;
}

.info-card h3 {
  font-size: 15px;
  margin-bottom: 12px;
}

.info-list {
  font-size: 13px;
  color: var(--text-secondary);
  line-height: 1.7;
  padding-left: 18px;
}
</style>
