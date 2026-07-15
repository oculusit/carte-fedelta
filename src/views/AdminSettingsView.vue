<template>
  <div class="admin-settings">
    <div class="page-header">
      <h2>Impostazioni</h2>
    </div>

    <div class="card settings-card">
      <h3>URL applicazione</h3>
      <p class="section-desc">URL completo dell'app (es. https://tuodominio.it/carte). Usato nelle email per generare link corretti.</p>
      <div class="input-group">
        <label>URL applicazione</label>
        <input v-model="form.app_url" type="url" placeholder="https://tuodominio.it/sottocartella" />
      </div>
    </div>

    <div class="card settings-card">
      <h3>Nome applicazione</h3>
      <p class="section-desc">Nome personalizzato mostrato nel titolo della pagina e nelle email.</p>
      <div class="input-group">
        <label>Nome applicazione</label>
        <input v-model="form.app_name" type="text" placeholder="Carte Fedeltà" />
      </div>
    </div>

    <div class="card settings-card">
      <h3>Modalità di invio</h3>
      <div class="mode-selector">
        <button class="mode-option" :class="{ active: form.mail_mode === 'mail' }" @click="form.mail_mode = 'mail'">
          <span class="mode-name">PHP mail()</span>
          <span class="mode-desc">Usa la funzione mail() del server</span>
        </button>
        <button class="mode-option" :class="{ active: form.mail_mode === 'smtp' }" @click="form.mail_mode = 'smtp'">
          <span class="mode-name">SMTP</span>
          <span class="mode-desc">Server SMTP dedicato</span>
        </button>
      </div>
    </div>

    <div class="card settings-card">
      <h3>Mittente</h3>
      <div class="input-group">
        <label>Mittente (From)</label>
        <input v-model="form.mail_from" type="email" placeholder="noreply@tuodominio.it" />
      </div>
      <div class="input-group">
        <label>Nome mittente</label>
        <input v-model="form.mail_from_name" type="text" placeholder="Carte Fedeltà" />
      </div>
      <div class="input-group">
        <label>Rispondi a (Reply-To)</label>
        <input v-model="form.mail_reply_to" type="email" placeholder="assistenza@tuodominio.it" />
      </div>
      <div class="input-group">
        <label>Return-Path</label>
        <input v-model="form.mail_return_path" type="email" placeholder="bounce@tuodominio.it" />
      </div>
    </div>

    <div v-if="form.mail_mode === 'smtp'" class="card settings-card">
      <h3>Server SMTP</h3>
      <div class="input-group">
        <label>Host</label>
        <input v-model="form.smtp_host" type="text" placeholder="smtp.tuodominio.it" />
      </div>
      <div class="input-row">
        <div class="input-group flex-1">
          <label>Porta</label>
          <input v-model="form.smtp_port" type="number" placeholder="587" />
        </div>
        <div class="input-group flex-1">
          <label>Crittografia</label>
          <select v-model="form.smtp_encryption">
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
            <option value="">Nessuna</option>
          </select>
        </div>
      </div>
      <div class="input-group">
        <label>Utente</label>
        <input v-model="form.smtp_user" type="text" placeholder="utente@tuodominio.it" />
      </div>
      <div class="input-group">
        <label>Password</label>
        <input v-model="form.smtp_pass" type="password" placeholder="password SMTP" />
      </div>
    </div>

    <div class="card settings-card">
      <h3>Antispam</h3>
      <p class="section-desc">Inserisci qui i record DNS per la configurazione antispam (copia/incolla dal tuo pannello DNS)</p>
      <div class="input-group">
        <label>Record SPF</label>
        <textarea v-model="form.spf_record" rows="3" placeholder="v=spf1 mx include:_spf.tuodominio.it ~all" class="mono"></textarea>
      </div>
      <div class="input-group">
        <label>Record DKIM</label>
        <textarea v-model="form.dkim_record" rows="3" placeholder="v=DKIM1; k=rsa; p=MIGfMA0G..." class="mono"></textarea>
      </div>
      <div class="input-group">
        <label>Record DMARC</label>
        <textarea v-model="form.dmarc_record" rows="3" placeholder="v=DMARC1; p=quarantine; rua=mailto:dmarc@tuodominio.it" class="mono"></textarea>
      </div>
    </div>

    <div class="card settings-card">
      <h3>Test</h3>
      <p class="section-desc">Invia una email di prova per verificare la configurazione</p>
      <div class="input-group">
        <label>Email di test</label>
        <input v-model="testEmail" type="email" placeholder="tua@email.it" />
      </div>
      <button class="btn btn-primary btn-block" :disabled="!testEmail || testing" @click="testMail">
        {{ testing ? 'Invio in corso...' : 'Invia email di test' }}
      </button>
      <p v-if="testResult" class="test-result" :class="{ success: testResult.success, error: !testResult.success }">
        {{ testResult.message }}
      </p>
    </div>

    <div class="card settings-card">
      <h3>Crittografia tessere</h3>
      <p class="section-desc">
        Crittografa i numeri delle carte fedeltà con chiave AES-256.
        <span class="status-badge" :class="encryptionActive ? 'status-on' : 'status-off'">
          {{ encryptionActive ? 'Attiva' : 'Disattiva' }}
        </span>
      </p>

      <button class="btn btn-primary btn-block" :disabled="encryptionActive || genKeyLoading" @click="generateKey">
        {{ genKeyLoading ? 'Generazione...' : 'Genera chiave casuale' }}
      </button>

      <div class="encrypt-actions">
        <button class="btn btn-success flex-1" :disabled="encryptionActive || encryptAllLoading" @click="encryptAll">
          {{ encryptAllLoading ? 'Crittografia...' : 'Crittografa tutte le tessere' }}
        </button>
        <button class="btn btn-warning flex-1" :disabled="!encryptionActive || decryptAllLoading" @click="decryptAll">
          {{ decryptAllLoading ? 'Decifratura...' : 'Decifra tutte le tessere' }}
        </button>
      </div>

      <button v-if="has2fa" class="btn btn-outline btn-block" style="margin-top:12px" :disabled="revealLoading" @click="showRevealModal = true">
        Visualizza Seed
      </button>
      <p v-else class="fa-hint" style="margin-top:12px">
        Per visualizzare il seed è necessario configurare il 2FA nelle impostazioni del profilo.
      </p>

      <button v-if="encryptionActive && has2fa" class="btn btn-danger btn-block" style="margin-top:8px" :disabled="disableLoading" @click="showDisableModal = true">
        {{ disableLoading ? 'Disabilitazione...' : 'Disabilita crittografia' }}
      </button>
      <p v-else-if="encryptionActive && !has2fa" class="fa-hint" style="margin-top:8px">
        Per disabilitare la crittografia è necessario configurare il 2FA nelle impostazioni del profilo.
      </p>
    </div>

    <div v-if="showRevealModal" class="reveal-overlay" @click.self="showRevealModal = false">
      <div class="reveal-modal card">
        <h3>Verifica 2FA</h3>
        <p class="reveal-desc">Inserisci il codice 2FA per visualizzare il seed di crittografia</p>
        <div class="input-group">
          <input
            v-model="revealCode"
            type="text"
            placeholder="000000"
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="one-time-code"
            @keyup.enter="revealSeed"
          />
        </div>
        <div class="reveal-error" v-if="revealError">{{ revealError }}</div>
        <div class="reveal-result" v-if="revealedSeed">
          <label>Seed di crittografia:</label>
          <code class="reveal-seed">{{ revealedSeed }}</code>
          <button class="btn btn-sm btn-outline" @click="copySeed">Copia</button>
        </div>
        <div class="reveal-actions">
          <button class="btn btn-primary" :disabled="!revealCode || revealLoading" @click="revealSeed">
            {{ revealLoading ? 'Verifica...' : 'Verifica' }}
          </button>
          <button class="btn btn-outline" @click="showRevealModal = false">Chiudi</button>
        </div>
      </div>
    </div>

    <div v-if="showDisableModal" class="reveal-overlay" @click.self="showDisableModal = false">
      <div class="reveal-modal card">
        <h3>Disabilita crittografia</h3>
        <p class="reveal-desc">Inserisci il codice 2FA per disabilitare la crittografia. Tutte le tessere verranno decifrate e il seed rimosso.</p>
        <div class="input-group">
          <input
            v-model="disableCode"
            type="text"
            placeholder="000000"
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="one-time-code"
            @keyup.enter="disableEncryption"
          />
        </div>
        <div class="reveal-error" v-if="disableError">{{ disableError }}</div>
        <div class="reveal-actions">
          <button class="btn btn-danger" :disabled="!disableCode || disableLoading" @click="disableEncryption">
            {{ disableLoading ? 'Disabilitazione...' : 'Disabilita crittografia' }}
          </button>
          <button class="btn btn-outline" @click="showDisableModal = false">Annulla</button>
        </div>
      </div>
    </div>

    <div class="actions-bar">
      <button class="btn btn-primary btn-block btn-lg" :disabled="saving" @click="save">
        {{ saving ? 'Salvataggio...' : 'Salva impostazioni' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { toast } from '../services/toast.js'
import { copyToClipboard } from '../services/clipboard.js'

const router = useRouter()

const saving = ref(false)
const testing = ref(false)
const testEmail = ref('')
const testResult = ref(null)
const encryptionActive = ref(false)
const genKeyLoading = ref(false)
const encryptAllLoading = ref(false)
const decryptAllLoading = ref(false)
const has2fa = ref(false)
const showRevealModal = ref(false)
const revealCode = ref('')
const revealError = ref('')
const revealedSeed = ref('')
const revealLoading = ref(false)
const showDisableModal = ref(false)
const disableCode = ref('')
const disableError = ref('')
const disableLoading = ref(false)
const form = reactive({
  app_url: '',
  app_name: '',
  mail_mode: 'mail',
  mail_from: '',
  mail_from_name: 'Carte Fedeltà',
  mail_reply_to: '',
  mail_return_path: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_user: '',
  smtp_pass: '',
  smtp_encryption: 'tls',
  spf_record: '',
  dkim_record: '',
  dmarc_record: '',
  allow_registration: '1',
})

onMounted(async () => {
  if (!auth.isAdmin()) {
    router.push('/')
    return
  }
  try {
    const [settings, encStatus, faStatus] = await Promise.all([
      api.settings.get(),
      api.settings.encryptionStatus(),
      api.settings.check2fa().catch(() => ({ has_2fa: false })),
    ])
    has2fa.value = faStatus.has_2fa
    encryptionActive.value = encStatus.encryption_seed_set
    if (settings.app_url) form.app_url = settings.app_url
    if (settings.app_name) form.app_name = settings.app_name
    if (settings.mail_mode) form.mail_mode = settings.mail_mode
    if (settings.mail_from) form.mail_from = settings.mail_from
    if (settings.mail_from_name) form.mail_from_name = settings.mail_from_name
    if (settings.mail_reply_to) form.mail_reply_to = settings.mail_reply_to
    if (settings.mail_return_path) form.mail_return_path = settings.mail_return_path
    if (settings.smtp_host) form.smtp_host = settings.smtp_host
    if (settings.smtp_port) form.smtp_port = settings.smtp_port
    if (settings.smtp_user) form.smtp_user = settings.smtp_user
    if (settings.smtp_pass) form.smtp_pass = settings.smtp_pass
    if (settings.smtp_encryption) form.smtp_encryption = settings.smtp_encryption
    if (settings.spf_record) form.spf_record = settings.spf_record
    if (settings.dkim_record) form.dkim_record = settings.dkim_record
    if (settings.dmarc_record) form.dmarc_record = settings.dmarc_record
    if (settings.allow_registration) form.allow_registration = settings.allow_registration
  } catch (e) {
    toast.show('Errore caricamento impostazioni: ' + e.message, 'error')
  }
})

async function save() {
  saving.value = true
  try {
    const payload = { ...form }
    await api.settings.update(payload)
    toast.show('Impostazioni salvate con successo', 'success')
  } catch (e) {
    toast.show('Errore salvataggio: ' + e.message, 'error')
  } finally {
    saving.value = false
  }
}

function generateRandomSeed() {
  const bytes = new Uint8Array(32)
  crypto.getRandomValues(bytes)
  return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('')
}

async function generateKey() {
  genKeyLoading.value = true
  try {
    const seed = generateRandomSeed()
    await api.settings.update({ encryption_seed: seed })
    encryptionActive.value = true
    toast.show('Chiave di crittografia generata e salvata con successo', 'success')
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    genKeyLoading.value = false
  }
}

async function encryptAll() {
  if (!confirm('Vuoi veramente crittografare tutte le tessere del database?')) return
  encryptAllLoading.value = true
  try {
    if (!encryptionActive.value) {
      const seed = generateRandomSeed()
      await api.settings.update({ encryption_seed: seed })
      encryptionActive.value = true
    }
    const result = await api.cards.encryptAll()
    toast.show('Crittografate ' + result.count + ' tessere', 'success')
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    encryptAllLoading.value = false
  }
}

async function decryptAll() {
  if (!confirm('Decifrare TUTTE le tessere del database? I numeri torneranno visibili in chiaro nell\'archivio.')) return
  decryptAllLoading.value = true
  try {
    const result = await api.cards.decryptAll()
    toast.show('Decifrate ' + result.count + ' tessere', 'success')
  } catch (e) {
    toast.show('Errore: ' + e.message, 'error')
  } finally {
    decryptAllLoading.value = false
  }
}

async function revealSeed() {
  revealLoading.value = true
  revealError.value = ''
  revealedSeed.value = ''
  try {
    const result = await api.settings.revealSeed(revealCode.value)
    revealedSeed.value = result.seed
    revealCode.value = ''
  } catch (e) {
    revealError.value = e.message
  } finally {
    revealLoading.value = false
  }
}

async function copySeed() {
  try {
    await copyToClipboard(revealedSeed.value)
    toast.show('Seed copiato negli appunti', 'success')
  } catch {
    toast.show('Errore copia', 'error')
  }
}

async function disableEncryption() {
  if (!confirm('Tutte le tessere verranno decifrate e il seed di crittografia rimosso. Continuare?')) return
  disableLoading.value = true
  disableError.value = ''
  try {
    const result = await api.settings.disableEncryption(disableCode.value)
    encryptionActive.value = false
    showDisableModal.value = false
    disableCode.value = ''
    toast.show('Crittografia disabilitata. Decifrate ' + result.decrypted_count + ' tessere.', 'success')
  } catch (e) {
    disableError.value = e.message
  } finally {
    disableLoading.value = false
  }
}

async function testMail() {
  if (!testEmail.value) return
  testing.value = true
  testResult.value = null
  try {
    const payload = {
      mode: form.mail_mode,
      test_email: testEmail.value,
      from: form.mail_from,
      from_name: form.mail_from_name,
      reply_to: form.mail_reply_to,
      return_path: form.mail_return_path,
      smtp_host: form.smtp_host,
      smtp_port: form.smtp_port,
      smtp_user: form.smtp_user,
      smtp_pass: form.smtp_pass,
      smtp_encryption: form.smtp_encryption,
    }
    const res = await fetch('./api/setup/test-mail', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    const data = await res.json()
    testResult.value = data
  } catch (e) {
    testResult.value = { success: false, message: 'Errore: ' + e.message }
  } finally {
    testing.value = false
  }
}
</script>

<style scoped>
.admin-settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-header h2 {
  font-size: 18px;
  font-weight: 600;
}

.settings-card {
  padding: 20px;
}

.settings-card h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}

.mode-selector {
  display: flex;
  gap: 12px;
}

.mode-option {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 12px;
  border: 2px solid var(--border);
  border-radius: var(--radius);
  background: none;
  cursor: pointer;
  transition: all 0.2s;
}

.mode-option.active {
  border-color: var(--primary);
  background: rgba(26, 115, 232, 0.05);
}

.mode-name {
  font-weight: 600;
  font-size: 14px;
}

.mode-desc {
  font-size: 11px;
  color: var(--text-secondary);
  text-align: center;
}

.input-group {
  margin-bottom: 12px;
}

.input-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 4px;
  color: var(--text-secondary);
}

.warning-text {
  font-size: 12px;
  color: var(--danger);
  margin-bottom: 8px;
}

.input-group input,
.input-group select,
.input-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  background: var(--card-bg);
  color: inherit;
  box-sizing: border-box;
}

.input-group textarea.mono {
  font-family: monospace;
  font-size: 12px;
}

.input-row {
  display: flex;
  gap: 12px;
}

.flex-1 {
  flex: 1;
}

.test-result {
  margin-top: 12px;
  padding: 10px 14px;
  border-radius: var(--radius);
  font-size: 13px;
  text-align: center;
}

.test-result.success {
  background: #e8f5e9;
  color: #2e7d32;
}

.test-result.error {
  background: #ffebee;
  color: #c62828;
}

.actions-bar {
  position: sticky;
  bottom: 0;
  padding: 12px 0;
  background: var(--bg);
}

.btn-lg {
  padding: 14px;
  font-size: 16px;
}
.status-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 6px;
  margin-left: 8px;
}
.status-on {
  background: #e8f5e9;
  color: #2e7d32;
}
.status-off {
  background: #ffebee;
  color: #c62828;
}
.encrypt-actions {
  display: flex;
  gap: 10px;
  margin-top: 12px;
}
.encrypt-actions .btn {
  min-width: 0;
}
.btn-warning {
  background: #f57c00;
  color: white;
  border: none;
}
.btn-warning:hover:not(:disabled) {
  background: #e65100;
}
.btn-warning:disabled {
  background: #bbb;
  cursor: not-allowed;
}
.fa-hint {
  font-size: 12px;
  color: var(--text-secondary);
  text-align: center;
}
.reveal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}
.reveal-modal {
  max-width: 420px;
  width: 100%;
  padding: 24px;
}
.reveal-modal h3 {
  font-size: 16px;
  margin-bottom: 8px;
}
.reveal-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 16px;
}
.reveal-error {
  background: #fff0f0;
  color: var(--danger);
  padding: 10px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  text-align: center;
  margin-bottom: 12px;
}
.reveal-result {
  margin-bottom: 16px;
}
.reveal-result label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 4px;
}
.reveal-seed {
  display: block;
  font-family: monospace;
  font-size: 12px;
  word-break: break-all;
  background: var(--bg);
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  margin-bottom: 8px;
}
.reveal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}
</style>
