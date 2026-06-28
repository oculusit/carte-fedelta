<template>
  <div class="privacy-page">
    <div class="card privacy-card">
      <h2>Informativa sulla Privacy</h2>
      <p><strong>Ultimo aggiornamento:</strong> Giugno 2026</p>

      <h3>1. Titolare del trattamento</h3>
      <p>Oculus.it<br />Email: <a href="mailto:info@oculus.it">info@oculus.it</a></p>

      <h3>2. Dati raccolti</h3>
      <p>Registrandoti all'applicazione "Carte Fedeltà", raccogliamo i seguenti dati personali:</p>
      <ul>
        <li>Indirizzo email</li>
        <li>Password (memorizzata in forma crittografata)</li>
        <li>Contenuto delle carte fedeltà (nome negozio, numero tessera, note)</li>
      </ul>

      <h3>3. Finalità del trattamento</h3>
      <p>I tuoi dati vengono utilizzati esclusivamente per:</p>
      <ul>
        <li>Gestire il tuo account e permetterti l'accesso all'applicazione</li>
        <li>Memorizzare e sincronizzare le tue carte fedeltà</li>
        <li>Inviarti comunicazioni relative al funzionamento del servizio (es. conferma registrazione, reset password)</li>
      </ul>

      <h3>4. Base giuridica</h3>
      <p>Il trattamento è basato sul tuo consenso esplicito (art. 6 par. 1 lett. a GDPR) e sull'esecuzione del contratto di servizio (art. 6 par. 1 lett. b GDPR).</p>

      <h3>5. Condivisione dei dati</h3>
      <p>I tuoi dati non vengono venduti o condivisi con terze parti, salvo obblighi di legge.</p>

      <h3>6. Periodo di conservazione</h3>
      <p>I tuoi dati vengono conservati per tutta la durata del tuo account. In caso di cancellazione dell'account, tutti i dati associati vengono eliminati entro 30 giorni.</p>

      <h3>7. I tuoi diritti</h3>
      <p>Hai diritto di:</p>
      <ul>
        <li>Accedere ai tuoi dati personali</li>
        <li>Richiedere la rettifica o cancellazione</li>
        <li>Limitare o opporti al trattamento</li>
        <li>Richiedere la portabilità dei dati</li>
        <li>Revocare il consenso in qualsiasi momento</li>
      </ul>
      <p>Per esercitare i tuoi diritti, scrivi a <a href="mailto:info@oculus.it">info@oculus.it</a>.</p>

      <h3>8. Sicurezza</h3>
      <p>Adottiamo misure tecniche e organizzative adeguate per proteggere i tuoi dati personali, inclusa la crittografia delle password e la trasmissione sicura tramite HTTPS.</p>
    </div>
  </div>
</template>

<style scoped>
.privacy-page {
  max-width: 640px;
  margin: 24px auto;
}
.privacy-card {
  padding: 24px;
  font-size: 14px;
  line-height: 1.6;
}
.privacy-card h2 {
  margin: 0 0 8px;
  font-size: 20px;
}
.privacy-card h3 {
  font-size: 15px;
  margin: 20px 0 8px;
}
.privacy-card p {
  color: var(--text-secondary);
  margin: 0 0 8px;
}
.privacy-card ul {
  color: var(--text-secondary);
  margin: 0 0 8px;
  padding-left: 20px;
}
.privacy-card a {
  color: var(--primary);
}
</style>
