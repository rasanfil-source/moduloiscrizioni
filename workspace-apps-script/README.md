# Google Apps Script — MODULI AUTONOMO

Questo progetto Apps Script collega WordPress a Google Sheets e ai servizi Google usati per le attività degli eventi. Il codice sorgente operativo è nella cartella `src`.

## Flussi operativi

- WordPress invia richieste firmate al progetto Apps Script.
- Il progetto crea e aggiorna il foglio dedicato a ciascun evento.
- La segreteria consulta il foglio evento e conferma le modifiche operative nel portale WordPress.
- Il progetto gestisce le attività email autorizzate da WordPress.

MySQL conserva il registro autorevole. I fogli evento ne presentano una vista operativa; le colonne economiche e le identità delle iscrizioni restano gestite dal portale.

## Configurazione

1. Crea un progetto Apps Script autonomo e inserisci il sorgente generato con:

   ```powershell
   pwsh.exe -NoLogo -NoProfile -Command "node tools/prepara-codice-workspace.mjs"
   ```

   Il file `dist/Codice-Workspace-Progetto-<versione>.gs` è il sorgente unico destinato al progetto autonomo. Caricalo nel progetto come `Codice.gs`.

2. Nelle proprietà dello script configura `MI_SHARED_SECRET` e `MI_WORDPRESS_COMMAND_URL` con i valori condivisi dal sito WordPress.
3. Distribuisci la Web App con l'account Google autorizzato a creare i fogli evento e gestire le email.
4. Inserisci l'indirizzo della Web App e le credenziali firmate nelle impostazioni del plugin WordPress.

Il proprietario dello script autorizza i servizi Google richiesti. Nessun segreto, identificativo del sito o dato personale va salvato nei sorgenti.

## Uso dei fogli evento

WordPress prepara il foglio al momento della richiesta firmata. La segreteria può aprirlo dal portale o usare direttamente il collegamento del foglio. Le modifiche ammesse vengono confrontate e confermate nel portale prima di aggiornare MySQL.

## Sorgenti

I file `.gs` in `src` descrivono i flussi di accesso, sincronizzazione, proiezione, report ed email. Il generatore verifica la sintassi e produce il singolo sorgente autonomo in `dist`.
