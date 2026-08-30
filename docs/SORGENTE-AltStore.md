# Sorgente AltStore — FidAPPti

Questo file spiega come rendere disponibile FidAPPti tramite **sorgente
AltStore**, oltre che come semplice download di un IPA.

## Cosa è una "sorgente" AltStore

AltStore consente di aggiungere "sorgenti" (source): piccoli cataloghi JSON
contenenti app. Gli utenti aggiungono il link in **AltStore → Impostazioni →
Sorgenti → "+"** e poi vedono le app, che possono installare con un tocco
(vengono firmate con il **loro** Apple ID automaticamente). In questo modo gli
aggiornamenti non richiedono di riscaricare l'IPA a mano.

## File inclusi

- **`deploy/source.json`** — il file sorgente da pubblicare sul sito web.
- **`docs/INSTALLAZIONE-iOS.md`** — la guida da allegare alle Release.

## Come pubblicarla sul tuo sito (AlterVista)

La sorgente più `URL` è `https://fidapptiweb.altervista.org/source.json`.
Per pubblicarla:

1. **Carica il file `source.json`** su AlterVista, nella cartella principale del
   sito (via FTP o file manager), in modo che sia raggiungibile all'indirizzo:
   ```
   https://fidapptiweb.altervista.org/source.json
   ```

2. **Carica il file `.ipa`** con lo stesso nome indicato nel `source.json`
   (di default `FidAPPti.ipa`), sempre nella cartella principale, così da
   essere raggiungibile all'indirizzo indicato in `downloadURL`:
   ```
   https://fidapptiweb.altervista.org/FidAPPti.ipa
   ```
   > L'IPA unsigned viene prodotto dal workflow di GitHub (`build-ipa.yml`).
   > Ogni volta che pubblichi una nuova versione, aggiorna `downloadURL`,
   > `version`, `versionDate` e `size` nel `source.json` e ricaricalo.

3. **Verifica l'icona**: `iconURL` punta a
   `https://fidapptiweb.altervista.org/fidappti-logo.png` (file già presente
   nella cartella `deploy/`). Aggiustalo se il file viene caricato altrove.

4. **Prova tu stesso** aggiungendo il link in AltStore.

## Struttura del source.json

```jsonc
{
  "name": "FidAPPti Source",
  "identifier": "com.oculus.fidappti.source",
  "apps": [
    {
      "name": "FidAPPti",
      "bundleIdentifier": "it.oculus.carte",
      "version": "1.3.1",
      "versionDate": "2026-08-30T00:00:00Z",
      "size": 0,
      "downloadURL": "https://fidapptiweb.altervista.org/FidAPPti.ipa",
      "iconURL": "https://fidapptiweb.altervista.org/fidappti-logo.png"
    }
  ]
}
```

### Aggiornare la versione

Quando pubblichi una nuova IPA:

1. Aggiorna `version`, `versionDate` e `size` (byte dell'IPA) nel `source.json`
2. Carica il nuovo `.ipa` su AlterVista sovrascrivendo quello esistente
3. Ricarica il `source.json`
4. Gli utenti che hanno la sorgente vedranno l'aggiornamento in AltStore

---

## Nota sulla firma

La firma con Apple ID gratuito vale **7 giorni**. AltStore rinnova in
automatico ma il dispositivo va ricollegato a un computer con AltServer almeno
una volta alla settimana. Per i dettagli vedi `docs/INSTALLAZIONE-iOS.md`.
