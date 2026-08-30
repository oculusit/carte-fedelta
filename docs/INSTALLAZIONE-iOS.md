# FidAPPti per iOS — Guida all'installazione

Grazie per aver scaricato l'app. Questa guida ti spiega come installare
**FidAPPti** sul tuo iPhone usando **AltStore**.

> L'IPA distribuita è **unsigned**: viene firmata automaticamente da AltStore
> con il tuo Apple ID al momento dell'installazione. Non serve alcun account
> sviluppatore a pagamento.

---

## Cosa ti serve

- Un **iPhone** (o iPad) con iOS 15 o superiore
- Un **Mac** **oppure** un **PC Windows**
  - (Se usi Windows, serve anche iTunes + iCloud di Apple installati)
- Il tuo **Apple ID** gratuito

> ⚠️ Usa l'Apple ID loggato sul tuo iPhone (quello di sempre). Non crearne uno
> nuovo solo per AltStore.

---

## Passo 1 — Installa Xcode (solo Mac, una sola volta)

1. Apri **App Store** sul Mac
2. Cerca **Xcode** → **Installa** (è un download grande, ~10+ GB)
3. Apri Xcode una volta e accetta la licenza

> Su **Windows** salta questo passaggio: servono invece **iTunes** e **iCloud**
> scaricabili dal sito Apple.

---

## Passo 2 — Installa AltServer

1. Scarica **AltServer** da **https://altstore.io**
2. **Mac**: sposta "AltServer" in **Applicazioni** e aprila (icona nella barra
   in alto). **Windows**: installa come normale programma.
3. Lascia AltServer in esecuzione in background.

---

## Passo 3 — Installa AltStore sul tuo iPhone (una sola volta)

1. **Collega l'iPhone al computer** via cavo USB
2. Sblocca il telefono e premi **"Fidati"** del computer se richiesto
3. Clicca l'icona **AltServer** → **Install AltStore onto [nome iPhone]**
4. Inserisci il tuo **Apple ID** e password
5. Attendi: **AltStore** comparirà sulla home del telefono
6. Sul telefono:
   - **Impostazioni → Generali → Gestione VPN e dispositivi**
   - Tocca il profilo relativo al tuo Apple ID → **Fida**
7. Apri **AltStore** → **Impostazioni** → accedi con lo stesso Apple ID

---

## Passo 4 — Scarica e installa FidAPPti

### Metodo A — da file .ipa (download manuale)

1. Scarica il file **`FidAPPti-ios-unsigned.ipa`** dalla Release
2. Sul Mac/Windows, apri **AltStore** sull'iPhone
3. Vai su **My Apps → "+"**
4. Seleziona il file `.ipa` (serve che sia raggiungibile dal telefono:
   AirDrop, iCloud Drive, o trasferimento via cavo)
5. AltStore firma e installa automaticamente

### Metodo B — dalla sorgente AltStore (consigliato)

1. Apri **AltStore** → **Impostazioni → Sorgenti → "+"**
2. Aggiungi il link della sorgente (vedi "Sorgente AltStore" sotto)
3. Apri la sorgente e tocca **Installa** su **FidAPPti**
4. Poi: **Impostazioni → Generali → Gestione VPN e dispositivi → Fida**

---

## Passo 5 — Prima apertura

1. Se richiesto: **Impostazioni → Generali → Gestione VPN e dispositivi → Fida**
2. Torna sulla home e apri **FidAPPti**

---

## Nota importante sulla firma

- Con l'Apple ID **gratuito** la firma vale **7 giorni**
- AltStore la rinnova **automaticamente**, ma il telefono deve essere
  **ricollegato (anche Wi-Fi) a un computer con AltServer** almeno una volta
  ogni 7 giorni
- Se l'app non si apre con "App non verificata", ripeti il passaggio "Fida"

---

## Risoluzione problemi

| Problema | Soluzione |
| --- | --- |
| AltServer non installa | Riavvia AltServer; riprova il collegamento USB; su Windows verifica iTunes/iCloud |
| "App non verificata" | Impostazioni → Generali → Gestione VPN e dispositivi → Fida |
| App sparisce/ripristinata | Firma scaduta: ricollega a AltServer e ripristina da AltStore |
| Installazione bloccata senza motivo | Su Windows disconnetti/ricon connetti il telefono; su macOS controlla i permessi di AltServer |

---

## Sorgente AltStore

Oltre all'IPA scaricabile, FidAPPti è disponibile come **sorgente AltStore**:
aggiungi il link qui sotto in AltStore (Impostazioni → Sorgenti → "+") per
installare e aggiornare l'app senza scaricare manualmente il file:

```
https://fidapptiweb.altervista.org/source.json
```

Per maggiori informazioni sul progetto: https://www.oculus.it
