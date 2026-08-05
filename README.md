# FidAppTi 📱💳 IN ENGLISH 


An independent, 100% privacy-focused, offline-first Loyalty Card Manager. No tracking, no ads, no central servers. Made in Italy with 🇮🇹 by Alessandro Blasi (*oculus*).

Unlike commercial alternatives that track your shopping habits and sell data to third parties, **FidAppTi** gives users absolute data sovereignty. It boots in under 5 seconds and works completely offline, rendering barcodes instantly at checkout.

---

## ✨ Key Features

- **Privacy-First & Offline-First:** No accounts, no telemetry, no internet required for daily usage.
- **Self-Hosted Cloud Sync (Supabase):** Sync data across devices or within your family by connecting the app directly to your personal, free **Supabase** instance. The developer never sees or touches your data.
- **Zero Ads / Zero Cost:** Free, open-source (MIT License), with absolutely no banner ads or in-app purchases.
- **Air-Gapped Backups:** Import and export your cards locally via flat backup files.
- **Cross-Platform:** Available as a native Android app and a Zero-Knowledge Progressive Web App (PWA) for Apple iOS.

---

## 🏗️ Architecture & Security

### Android App
The native Android app is designed for speed and security. 
- Local storage by default.
- Automated CI/CD builds powered by **GitHub Actions** to guarantee supply chain integrity (the distributed APK matches the public source code identically).
- In the process of being officially published on **F-Droid**.

### iOS PWA (Zero-Knowledge)
To bypass the expensive Apple Developer fees for a non-profit hobby project, an engineered **Progressive Web App (PWA)** serves iOS users.
- **Client-Side Encryption:** Your loyalty data is encrypted directly inside your browser using a private personal key before being sent to the online storage.
- **Zero-Knowledge:** Even the hosting server administrator cannot read or decrypt your database payload.

---

## 📲 Deployment & Links

| Platform | Type | Install Link / Reference |
| :--- | :--- | :--- |
| **Android** | Native App | [Official Website / APK](http://fidappti.altervista.org) |
| **Apple iOS** | PWA (Safari) | [Launch WebApp](http://fidapptiweb.altervista.org/webapp) ([Info Site](http://fidapptiweb.altervista.org/site)) |
| **Developer** | Author | Alessandro Blasi ([oculus.it](http://oculus.it)) |

### How to install on iOS:
1. Open [fidapptiweb.altervista.org/webapp](http://fidapptiweb.altervista.org/webapp) in **Safari**.
2. Tap the **Share** button.
3. Scroll down and select **"Add to Home Screen"**.

---

## 🛠️ Contributing

Contributions, bug reports, and feature requests are welcome! 
Feel free to check the [issues page]([https://github.com](https://github.com/oculusit/carte-fedelta/issues)) if you want to contribute to the code or suggest improvements.

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

# FidAppTi 📱💳 IN ITALIANO

Un gestore di carte fedeltà indipendente, 100% orientato alla privacy e offline-first. Nessun tracciamento, nessuna pubblicità, nessun server centrale. Sviluppato in Italia con 🇮🇹 da Alessandro Blasi (*oculus*).

A differenza delle alternative commerciali che tracciano le tue abitudini di spesa e rivendono i dati a terzi, **FidAppTi** restituisce agli utenti la totale sovranità sui propri dati. L'applicazione è operativa in meno di 5 secondi e funziona completamente offline, mostrando istantaneamente i codici a barre alla cassa.

---

## ✨ Funzionalità Principali

- **Privacy-First & Offline-First:** Nessun account richiesto, nessuna telemetria, nessun bisogno di connessione internet per l'uso quotidiano.
- **Sincronizzazione Cloud Personale (Supabase):** Sincronizza i dati su più dispositivi o con la tua famiglia collegando l'app direttamente alla tua istanza privata e gratuita di **Supabase**. Lo sviluppatore non vedrà né toccherà mai i tuoi dati.
- **Zero Pubblicità / Zero Costi:** Totalmente gratuito, open-source (Licenza MIT), senza banner pubblicitari o acquisti in-app.
- **Backup Locali Protetti:** Esporta e importa le tue tessere in locale tramite file di backup flat.
- **Cross-Platform:** Disponibile come app nativa per Android e come Progressive Web App (PWA) con architettura Zero-Knowledge per Apple iOS.

---

## 🏗️ Architettura e Sicurezza

### Applicazione Android
L'app nativa per Android è progettata per garantire massima velocità e sicurezza.
- Memorizzazione dei dati in locale come impostazione predefinita.
- Pipeline di CI/CD automatizzate tramite **GitHub Actions** per garantire l'integrità della catena di distribuzione (l'APK distribuito corrisponde esattamente al codice sorgente pubblico).
- In fase di rilascio ufficiale all'interno della directory indipendente **F-Droid**.

### iOS PWA (Zero-Knowledge)
Per aggirare gli elevati costi annuali dell'Apple Developer Account per un progetto no-profit, è stata ingegnerizzata una **Progressive Web App (PWA)** dedicata agli utenti iPhone.
- **Crittografia Client-Side:** I dati delle tue tessere vengono cifrati direttamente all'interno del browser utilizzando una chiave personale prima di essere inviati allo storage online.
- **Zero-Knowledge:** Nemmeno l'amministratore del server ospitante ha la possibilità tecnica di leggere o decifrare il payload del database.

---

## 📲 Installazione e Link Utili

| Piattaforma | Tipologia | Link di Installazione / Riferimento |
| :--- | :--- | :--- |
| **Android** | App Nativa | [Sito Ufficiale / Download APK](http://fidappti.altervista.org) |
| **Apple iOS** | PWA (Safari) | [Avvia WebApp](http://fidapptiweb.altervista.org/webapp) ([Sito Informativo](http://fidapptiweb.altervista.org)) |
| **Sviluppatore** | Autore | Alessandro Blasi ([oculus.it](http://oculus.it)) |

### Come installare su iOS:
1. Apri [fidapptiweb.altervista.org/webapp](http://fidapptiweb.altervista.org/webapp) utilizzando il browser **Safari**.
2. Tocca il pulsante di **Condivisione** (l'icona con il quadrato e la freccia verso l'alto).
3. Scorri verso il basso e seleziona **"Aggiungi alla schermata Home"**.

---

## 🛠️ Contributi

I contributi, le segnalazioni di bug e le richieste di nuove funzionalità sono sempre benvenuti!
Visita la [pagina delle issues]([https://github.com](https://github.com/oculusit/carte-fedelta/issues)) se desideri contribuire al codice o suggerire miglioramenti al progetto.

## 📄 Licenza

Questo progetto è distribuito sotto **Licenza MIT** - consulta il file [LICENSE](LICENSE) per ulteriori dettagli.
