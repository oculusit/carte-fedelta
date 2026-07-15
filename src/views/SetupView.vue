<template>
  <div class="setup">
    <div class="setup-header">
      <h1>Configurazione iniziale</h1>
      <p>Completa i passaggi per configurare l'applicazione</p>
    </div>

    <div class="steps-indicator">
      <div
        v-for="(s, i) in steps"
        :key="i"
        class="step"
        :class="{ active: step === i, done: step > i }"
      >
        <span class="step-num">{{ i + 1 }}</span>
        <span class="step-label">{{ s }}</span>
      </div>
    </div>

    <!-- Step 1: Database -->
    <div v-if="step === 0" class="card setup-card">
      <h3>Database MySQL</h3>
      <p class="section-desc">Inserisci i dati di connessione al database MySQL</p>

      <div class="form-grid">
        <div class="input-group">
          <label>Host</label>
          <input v-model="db.host" type="text" placeholder="localhost" />
        </div>
        <div class="input-group">
          <label>Porta</label>
          <input v-model="db.port" type="text" placeholder="3306" />
        </div>
        <div class="input-group">
          <label>Nome database</label>
          <input v-model="db.name" type="text" placeholder="carte_fedelta" />
        </div>
        <div class="input-group">
          <label>Prefisso tabelle</label>
          <input v-model="db.prefix" type="text" placeholder="cards_" />
        </div>
        <div class="input-group">
          <label>Utente</label>
          <input v-model="db.user" type="text" placeholder="root" />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="db.pass" type="password" placeholder="" />
        </div>
      </div>

      <div v-if="dbTestResult" class="test-result" :class="dbTestResult.success ? 'success' : 'error'">
        <span v-if="dbTestResult.success">✅</span>
        <span v-else>❌</span>
        {{ dbTestResult.message }}
      </div>

      <div class="nav-buttons">
        <button
          class="btn btn-outline"
          @click="testDb"
          :disabled="testingDb"
        >
          {{ testingDb ? 'Test in corso...' : 'Test connessione' }}
        </button>
        <button class="btn btn-primary" @click="step = 1">
          Continua &rarr;
        </button>
      </div>
    </div>

    <!-- Step 2: Mail -->
    <div v-if="step === 1" class="card setup-card">
      <h3>Configurazione email</h3>
      <p class="section-desc">
        Configura l'invio email per notifiche e recupero password
      </p>

      <div class="input-group">
        <label>Metodo di invio</label>
        <div class="radio-group">
          <label class="radio-option">
            <input v-model="mail.mode" type="radio" value="mail" />
            <span>PHP mail()</span>
          </label>
          <label class="radio-option">
            <input v-model="mail.mode" type="radio" value="smtp" />
            <span>SMTP</span>
          </label>
        </div>
      </div>

      <div v-if="mail.mode === 'mail'" class="mail-fields">
        <div class="input-group">
          <label>Email mittente</label>
          <input v-model="mail.from" type="email" placeholder="noreply@tuodominio.com" />
          <span class="field-hint">Usata nel campo From: delle email inviate</span>
        </div>
        <div class="input-group">
          <label>Nome mittente</label>
          <input v-model="mail.from_name" type="text" placeholder="FidAPPti" />
        </div>
        <div class="input-group">
          <label>Reply-To</label>
          <input v-model="mail.reply_to" type="email" placeholder="noreply@tuodominio.com" />
        </div>
        <div class="input-group">
          <label>Return-Path (antispam)</label>
          <input v-model="mail.return_path" type="email" placeholder="noreply@tuodominio.com" />
          <span class="field-hint">
            Parametro -f della funzione mail() di PHP. Deve corrispondere al dominio SPF del server.
            Riduce la probabilità che le email finiscano nello spam.
          </span>
        </div>
        <div class="info-box">
          <strong>Antispam attivi:</strong> From, Reply-To, Return-Path (-f),
          MIME-Version, Content-Type HTML, Message-ID, X-Mailer
        </div>
      </div>

      <div v-if="mail.mode === 'smtp'" class="mail-fields">
        <div class="input-group">
          <label>Server SMTP</label>
          <input v-model="mail.smtp_host" type="text" placeholder="smtp.example.com" />
        </div>
        <div class="form-grid">
          <div class="input-group">
            <label>Porta</label>
            <input v-model="mail.smtp_port" type="text" placeholder="587" />
          </div>
          <div class="input-group">
            <label>Crittografia</label>
            <select v-model="mail.smtp_encryption">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="">Nessuna</option>
            </select>
          </div>
        </div>
        <div class="input-group">
          <label>Utente</label>
          <input v-model="mail.smtp_user" type="text" placeholder="" />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="mail.smtp_pass" type="password" placeholder="" />
        </div>
        <div class="input-group">
          <label>Email mittente</label>
          <input v-model="mail.from" type="email" placeholder="noreply@tuodominio.com" />
        </div>
        <div class="input-group">
          <label>Nome mittente</label>
          <input v-model="mail.from_name" type="text" placeholder="FidAPPti" />
        </div>
      </div>

      <div class="input-group">
        <label>Email per test</label>
        <input v-model="mail.test_email" type="email" placeholder="tua@email.com" />
        <span class="field-hint">Inserisci una tua email per ricevere il test</span>
      </div>

      <div v-if="mailTestResult" class="test-result" :class="mailTestResult.success ? 'success' : 'error'">
        <span v-if="mailTestResult.success">✅</span>
        <span v-else>❌</span>
        {{ mailTestResult.message }}
      </div>

      <div class="nav-buttons">
        <button class="btn btn-outline" @click="step = 0">&larr; Indietro</button>
        <button
          class="btn btn-outline"
          @click="testMail"
          :disabled="testingMail"
        >
          {{ testingMail ? 'Invio...' : 'Invia test' }}
        </button>
        <button class="btn btn-primary" @click="step = 2">
          Continua &rarr;
        </button>
      </div>
    </div>

    <!-- Step 3: Summary & Save -->
    <div v-if="step === 2" class="card setup-card">
      <h3>Riepilogo e salvataggio</h3>
      <p class="section-desc">Verifica le impostazioni prima di salvare</p>

      <div class="summary-section">
        <h4>Database</h4>
        <table class="summary-table">
          <tr><td>Host</td><td>{{ db.host }}:{{ db.port }}</td></tr>
          <tr><td>Database</td><td>{{ db.name }}</td></tr>
          <tr><td>Utente</td><td>{{ db.user }}</td></tr>
          <tr><td>Prefisso tabelle</td><td><code>{{ db.prefix }}</code></td></tr>
        </table>
      </div>

      <div class="summary-section">
        <h4>Email</h4>
        <table class="summary-table">
          <tr><td>Metodo</td><td>{{ mail.mode === 'mail' ? 'PHP mail()' : 'SMTP' }}</td></tr>
          <tr><td>Mittente</td><td>{{ mail.from_name }} &lt;{{ mail.from }}&gt;</td></tr>
          <tr v-if="mail.mode === 'smtp'">
            <td>Server SMTP</td>
            <td>{{ mail.smtp_host }}:{{ mail.smtp_port }}</td>
          </tr>
          <tr v-if="mail.mode === 'mail'">
            <td>Return-Path (-f)</td>
            <td><code>{{ mail.return_path }}</code></td>
          </tr>
        </table>
      </div>

      <div v-if="saveResult" class="test-result" :class="saveResult.success ? 'success' : 'error'">
        <span v-if="saveResult.success">✅</span>
        <span v-else>❌</span>
        {{ saveResult.message }}
      </div>

      <div v-if="saved" class="save-success">
        <p class="success-message">Database configurato!</p>
        <p class="success-desc">Ora puoi creare un account amministratore per gestire i negozi.</p>
        <button class="btn btn-primary" @click="step = 3">Continua &rarr;</button>
      </div>

      <div v-else class="nav-buttons">
        <button class="btn btn-outline" @click="step = 1">&larr; Indietro</button>
        <button
          class="btn btn-success"
          @click="saveConfig"
          :disabled="saving"
        >
          {{ saving ? 'Salvataggio...' : 'Salva configurazione' }}
        </button>
      </div>
    </div>

    <!-- Step 3: Admin account -->
    <div v-if="step === 3" class="card setup-card">
      <h3>Account amministratore</h3>
      <p class="section-desc">
        Crea un account amministratore per gestire negozi e loghi predefiniti.
        Puoi saltare questo passaggio e creare l'admin anche dopo.
      </p>

      <div class="input-group">
        <label>Email amministratore</label>
        <input v-model="admin.email" type="email" placeholder="admin@esempio.com" />
      </div>
      <div class="input-group">
        <label>Password</label>
        <input v-model="admin.password" type="password" placeholder="Almeno 6 caratteri" />
      </div>

      <div v-if="adminResult" class="test-result" :class="adminResult.success ? 'success' : 'error'">
        <span v-if="adminResult.success">✅</span>
        <span v-else>❌</span>
        {{ adminResult.message }}
      </div>

      <div v-if="adminCreated" class="save-success">
        <p class="success-message">Setup completato!</p>
        <p class="success-desc">Puoi ora accedere con l'account amministratore.</p>
        <router-link to="/login" class="btn btn-primary">Vai al login</router-link>
      </div>

      <div v-else class="nav-buttons">
        <button class="btn btn-outline" @click="step = 2">&larr; Indietro</button>
        <button
          class="btn btn-outline"
          @click="skipAdmin"
        >
          Salta
        </button>
        <button
          class="btn btn-success"
          @click="createAdmin"
          :disabled="creatingAdmin"
        >
          {{ creatingAdmin ? 'Creazione...' : 'Crea admin' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { setup } from '../services/setup.js'

const steps = ['Database', 'Email', 'Salvataggio', 'Amministratore']
const step = ref(0)

const db = ref({
  host: 'localhost',
  port: '3306',
  name: 'carte_fedelta',
  user: 'root',
  pass: '',
  prefix: 'cards_',
})

const mail = ref({
  mode: 'mail',
  from: '',
  from_name: 'FidAPPti',
  reply_to: '',
  return_path: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_user: '',
  smtp_pass: '',
  smtp_encryption: 'tls',
  test_email: '',
})

const testingDb = ref(false)
const dbTestResult = ref(null)

const testingMail = ref(false)
const mailTestResult = ref(null)

const saving = ref(false)
const saveResult = ref(null)
const saved = ref(false)

const admin = ref({ email: '', password: '' })
const creatingAdmin = ref(false)
const adminResult = ref(null)
const adminCreated = ref(false)

onMounted(async () => {
  try {
    const result = await setup.check()
    if (result.configured && result.config) {
      Object.assign(db.value, result.config.db)
      Object.assign(mail.value, result.config.mail)
    }
  } catch {
    // Use defaults
  }
})

async function testDb() {
  testingDb.value = true
  dbTestResult.value = null
  try {
    const result = await setup.testDb(db.value)
    dbTestResult.value = { success: true, message: result.message }
  } catch (e) {
    dbTestResult.value = { success: false, message: e.message }
  } finally {
    testingDb.value = false
  }
}

async function testMail() {
  testingMail.value = true
  mailTestResult.value = null
  try {
    const result = await setup.testMail({
      mode: mail.value.mode,
      from: mail.value.from,
      from_name: mail.value.from_name,
      reply_to: mail.value.reply_to,
      return_path: mail.value.return_path,
      smtp_host: mail.value.smtp_host,
      smtp_port: mail.value.smtp_port,
      smtp_user: mail.value.smtp_user,
      smtp_pass: mail.value.smtp_pass,
      smtp_encryption: mail.value.smtp_encryption,
      test_email: mail.value.test_email,
    })
    mailTestResult.value = { success: true, message: result.message }
  } catch (e) {
    mailTestResult.value = { success: false, message: e.message }
  } finally {
    testingMail.value = false
  }
}

async function saveConfig() {
  saving.value = true
  saveResult.value = null
  try {
    const result = await setup.save({
      db: {
        host: db.value.host,
        port: db.value.port,
        name: db.value.name,
        user: db.value.user,
        pass: db.value.pass,
        prefix: db.value.prefix,
      },
      mail: {
        mode: mail.value.mode,
        from: mail.value.from,
        from_name: mail.value.from_name,
        reply_to: mail.value.reply_to,
        return_path: mail.value.return_path,
        smtp_host: mail.value.smtp_host,
        smtp_port: mail.value.smtp_port,
        smtp_user: mail.value.smtp_user,
        smtp_pass: mail.value.smtp_pass,
        smtp_encryption: mail.value.smtp_encryption,
      },
    })
    saveResult.value = { success: true, message: result.message }
    saved.value = true
  } catch (e) {
    saveResult.value = { success: false, message: e.message }
  } finally {
    saving.value = false
  }
}

async function createAdmin() {
  creatingAdmin.value = true
  adminResult.value = null
  try {
    const result = await setup.createAdmin(admin.value.email, admin.value.password)
    adminResult.value = { success: true, message: result.message }
    adminCreated.value = true
  } catch (e) {
    adminResult.value = { success: false, message: e.message }
  } finally {
    creatingAdmin.value = false
  }
}

function skipAdmin() {
  adminCreated.value = true
}
</script>

<style scoped>
.setup {
  max-width: 600px;
  margin: 40px auto;
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

.steps-indicator {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-bottom: 32px;
}

.step {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  background: #f0f0f0;
  color: #999;
  transition: all 0.2s;
}

.step.active {
  background: var(--primary);
  color: white;
}

.step.done {
  background: var(--success);
  color: white;
}

.step-num {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 12px;
  background: rgba(0,0,0,0.1);
}

.step.active .step-num,
.step.done .step-num {
  background: rgba(255,255,255,0.25);
}

.setup-card {
  padding: 24px;
}

.setup-card h3 {
  font-size: 18px;
  margin-bottom: 4px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-grid .input-group {
  margin-bottom: 0;
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

.input-group input,
.input-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  background: white;
}

.input-group select {
  cursor: pointer;
}

.field-hint {
  display: block;
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 4px;
}

.radio-group {
  display: flex;
  gap: 16px;
}

.radio-option {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  font-size: 14px;
}

.radio-option input {
  width: auto;
}

.info-box {
  background: #e8f4fd;
  border: 1px solid #b8daf0;
  border-radius: var(--radius);
  padding: 12px;
  font-size: 12px;
  color: #1a6ea8;
  margin-bottom: 16px;
  line-height: 1.6;
}

.test-result {
  padding: 12px;
  border-radius: var(--radius);
  font-size: 13px;
  margin-bottom: 16px;
}

.test-result.success {
  background: #e6f9ee;
  border: 1px solid #b8e6cc;
  color: #1a7a3a;
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

.nav-buttons .btn {
  min-width: 120px;
}

.summary-section {
  margin-bottom: 20px;
}

.summary-section h4 {
  font-size: 14px;
  margin-bottom: 8px;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-table {
  width: 100%;
  font-size: 14px;
}

.summary-table td {
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}

.summary-table td:first-child {
  color: var(--text-secondary);
  width: 140px;
}

.summary-table code {
  background: #f0f0f0;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 13px;
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
