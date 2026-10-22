# Sviluppo

Guida alla struttura del progetto e alla gestione dei sorgenti. Le regole funzionali sono raccolte in [UX-CONTRACT](../UX-CONTRACT.md), [DESIGN](../DESIGN.md), [PROGETTO](../PROGETTO.md) e [schema dati](SCHEMA_DATI.md).

## Componenti

- `wordpress-plugin/modulo-iscrizioni`: plugin WordPress, API, interfacce e registro autorevole MySQL.
- `workspace-apps-script/src`: servizi Google per MODULI AUTONOMO, proiezione diretta, gestione delle scritture e accesso ai fogli evento.
- `wordpress-plugin/tests` e `workspace-apps-script/tests`: controlli automatici dei comportamenti definiti dai sorgenti.
- `tools`: generazione degli asset, preparazione del sorgente Apps Script e strumenti di confezionamento.
- `dist`: sorgenti aggregati e pacchetti generati; non è la fonte autorevole del codice.
- `.tmp`: runtime, file temporanei e dati sintetici usati dagli strumenti locali.

MySQL conserva i dati autorevoli di iscrizioni, partecipanti, pagamenti e assegnazioni. Google riceve le proiezioni e ospita i fogli di lavoro degli eventi. Il sorgente Apps Script destinato all'uso operativo viene generato per MODULI AUTONOMO.

## Asset e sorgenti Apps Script

Gli asset modificabili risiedono in `wordpress-plugin/modulo-iscrizioni/assets`; i file in `assets/min` sono generati da `tools/asset-build/build.cjs`. Per preparare il sorgente Apps Script eseguire dalla radice del progetto:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "node tools/prepara-codice-workspace.mjs"
```

Il risultato è `Codice-Workspace-Progetto-<versione>.gs`, destinato al progetto Apps Script autonomo. Caricarlo nel progetto con il nome `Codice.gs`.

## Confezionamento

Prima di generare un pacchetto distribuibile, impostare lo stesso identificativo nell'intestazione del plugin e in `MI_VERSION`. Usare tale identificativo nel nome dell'archivio e delle note tecniche. Ogni archivio ha un nome univoco e viene conservato senza successive sostituzioni. La distribuzione pubblicabile esclude `public-balance-config.json`.

Il protocollo firmato tra WordPress e Apps Script è condiviso dai due componenti. La configurazione del canale email è descritta in [Comunicazioni tramite Apps Script](ATTIVAZIONE_EMAIL_GAS.md).

## Dati temporanei

Gli strumenti locali possono usare servizi e database sintetici. Verificare la configurazione richiesta da ciascun comando prima di avviarlo e mantenere i dati di esempio separati da quelli operativi. Non cancellare in blocco le cartelle temporanee: possono contenere runtime o database necessari alle attività locali.
