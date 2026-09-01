# FidAPPti per iPhone — Guida semplicissima (for Dummies)

Ciao 👋 Hai scaricato **FidAPPti** per il tuo iPhone. Questa guida spiega, in
parole semplici, come installarla e tenerla sempre funzionante.

> ⚠️ Hai solo un iPhone e un computer (anche Linux/Windows va benissimo).
> **Non serve pagare nulla** e **non serve essere esperti.**

---

## Cosa devi sapere prima (in breve)

- iPhone non fa scaricare app da fuori dall'App Store facilmente. Apple usa un
  sistema di "firma" che scade ogni 7 giorni.
- Noi risolviamo il problema con un programma gratis che si chiama
  **SideStore**: lo installi **una sola volta**, e poi si "rinnova" da solo,
  così **non ti scade più nulla**.
- Ti serve: un **iPhone**, un **computer** (Mac, Windows o anche Linux), e il
  tuo **Apple ID** (quello del tuo iPhone, quello di sempre).

---

## Passo 1 — Sul computer: apri il sito di SideStore

Apri il browser del computer e vai su:

```
https://sidestore.io
```

Clicca sul pulsante **"Download"**. Scarichi un programma chiamato
**SideStore** (esiste per Windows, Mac e Linux).

> Non importa che tipo di computer hai: la procedura è simile per tutti.

---

## Passo 2 — Installa e apri SideStore sul computer

1. **Windows**: apri il file scaricato e segui l'installazione.
   Serve avere **iTunes** (basta quello dell'App Store o dal sito Apple).
2. **Mac**: trascina l'app nella cartella "Applicazioni" e apri.
3. **Linux**: segui le istruzioni della pagina di download (c'è il pacchetto
   per la tua versione).

Apri il programma SideStore. Vedrai un'icona, di solito in basso a destra
(Windows) o in alto a destra (Mac).

---

## Passo 3 — Collega l'iPhone al computer e installa SideStore sull'iPhone

1. Collega l'**iPhone** al computer con il **cavo USB** originale.
2. Sblocca il telefono. Se ti chiede "Fidarsi di questo computer?", premi
   **Fidati** e (su iPhone) inserisci il codice.
3. Apri il programma **SideStore** sul computer.
4. Dal programma, scegli **"Install SideStore on <nome del tuo iPhone>"**.

A questo punto ti chiederà il **tuo Apple ID** (email e password). Inseriscili
— serviranno solo a firmare le app, è normale.

> 🛑 Se hai il "protezione di due fattori" attivo (consigliato!), ti manderà
> un **codice sul telefono**: scrivilo quando te lo chiede.

Attendi qualche minuto. Alla fine vedrai l'icona **SideStore** apparire
sull'iPhone.

---

## Passo 4 — Fida il programma sul tuo iPhone

Sul **telefono**:

1. Vai su **Impostazioni → Generali → Gestione VPN e dispositivi**.
2. Vedrai un profilo con lo stesso nome del tuo Apple ID.
3. Toccalo e premi **"Fida"**.

Fatto. Ora SideStore è autorizzato a installare app.

---

## Passo 5 — Metti SideStore "in modalità sviluppatore" (solo se serve)

Su alcuni iPhone più nuovi, alla prima apertura di SideStore ti chiederà di
abilitare la **"Modalità sviluppatore"**:

1. Vai su **Impostazioni → Privacy e sicurezza → Modalità sviluppatore**.
2. Attivala e riavvia il telefono (ti avviserà lui).

Se non vedi questa opzione, vai tranquillo: non ti serve.

---

## Passo 6 — Scarica l'app FidAPPti

Sul **computer** (o direttamente sul telefono):

1. Vai alla pagina dove hai preso questa guida (la Release di GitHub).
2. Scarica il file che si chiama **`FidAPPti-ios-unsigned.ipa`**.

> "unsigned" è solo un termine tecnico: significa che la firma la fa
> SideStore col tuo Apple ID. Non preoccuparti.

---

## Passo 7 — Installa FidAPPti con SideStore

1. Sul **telefono**, apri l'app **SideStore** (l'icona che hai installato).
2. Tieni il telefono **collegato al computer** (o sulla stessa rete Wi-Fi).
3. Nell'app SideStore, tocca il simbolo **"+"** in alto a sinistra.
4. Scegli il file **`FidAPPti-ios-unsigned.ipa`** che hai scaricato.
5. Apparirà "FidAPPti" nella lista: tocca **Install** (Installa).

Attendi: l'app si installa da sola, proprio come dall'App Store.

Ora tocca l'icona **FidAPPti** sull'home del telefono: si apre! 🎉

---

## Passo 8 — Come fare perché NON scada dopo 7 giorni (importante!)

Su iPhone, le app installate "a parte" scadono dopo 7 giorni. **SideStore le
rinnova da sole**, ma per farlo ti serve il **SideStore Server** sul computer.

### Come attivarlo (una sola volta)

1. Apri **SideStore** sul **computer**.
2. Cerca l'opzione **"Start SideStore Server"** (o "Server") e avviala.
3. Lascia il computer acceso, con il programma in esecuzione.
4. Assicurati che iPhone e computer siano sulla **stessa rete Wi-Fi**.

Se tutto è a posto, SideStore rinfresca la firma **automaticamente** ogni
tanto: **non devi fare più nulla**, FidAPPti non ti scade più.

> 💡 Suggerimento: se puoi, lascia il computer acceso o avvia il Server quando
> usi il telefono. È l'unico "lavoretto" che serve.

---

## Problemi comuni — e come risolverli

### "Non vedo FidAPPti" dopo il "+"
Controlla che il file `.ipa` sia davvero scaricato e che il telefono sia
collegato/stessa rete. Riprova.

### "App non verificata" o "Cannot be opened"
Su **Impostazioni → Generali → Gestione VPN e dispositivi** premi **Fida** sul
profilo del tuo Apple ID.

### "Mi dice che la firma è scaduta"
- Avvia il **SideStore Server** sul computer.
- Collega/ricollega il telefono alla stessa Wi-Fi.
- Apri SideStore: vedrai la lista delle app e la data di scadenza. Se è
  scaduta, ripremi **Install** per rinnovarla subito.

### SideStore sul computer non parte (Windows)
Assicurati di avere **iTunes** installato dal sito Apple (non dal Microsoft
Store). Riavvia il computer e riprova.

---

## Serve ancora aiuto?

- Guida ufficiale SideStore: **https://faq.sidestore.io**
- Sito del progetto FidAPPti: **https://www.oculus.it**

Ricorda: **tutto gratis**, e lo fai **una sola volta**. Dopo, FidAPPti resta
sul telefono e si rinnova da sola. Buon viaggio! 🚀
