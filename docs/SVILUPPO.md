# Sviluppo corrente

Aggiornato il 3 ottobre 2026. Questa guida sostituisce le cronache tecniche e i piani di interventi già completati. Le regole funzionali restano in [UX-CONTRACT](../UX-CONTRACT.md), [DESIGN](../DESIGN.md), [PROGETTO](../PROGETTO.md) e [schema dati](SCHEMA_DATI.md).

## Componenti e autorità dei dati

- `wordpress-plugin/modulo-iscrizioni`: plugin, API, interfacce e registro MySQL.
- `workspace-apps-script/src`: servizi Google, proiezione diretta, journal di scrittura e importazione controllata.
- `wordpress-plugin/tests` e `workspace-apps-script/tests`: regressioni permanenti. I nomi contenenti “audit” non rendono i test obsoleti.
- `tools`: verifica, generazione asset, confezionamento e prove browser/database. Alcuni script di confezionamento richiamano script di versioni precedenti: conservarli come dipendenze di sviluppo.
- `dist`: sole cinque versioni più recenti dei pacchetti; non è la fonte autorevole del codice corrente.
- `.tmp`: runtime e diagnostica locali. Non eliminare in blocco questa cartella: contiene anche dipendenze di verifica e database sintetici.

MySQL è autorevole per iscrizioni, movimenti, partecipanti e assegnazioni. Le copie Google sono asincrone. Il pacchetto autonomo dichiara `MI_STANDALONE_MODE=true` e blocca l'accesso al workbook centrale; menu e report storici non certificano dati correnti. Conservare il rollback esterno come previsto dal [piano di dismissione](piano-dismissione-db-moduli.md).

## Verifiche

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe"
```

Il verificatore esegue suite Node, controllo degli asset generati, sanitizzazione, lint PHP e suite PHP elencate nello script. Richiede Node 22+, PHP 8.3+ e `mbstring`; il percorso PHP è facoltativo se presente nel PATH.

La campagna InnoDB/browser è distinta:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./tools/verify-campaign.ps1 -PhpPath .tmp/php-runtime/php.exe"
```

Richiede MariaDB locale sulla porta **33317**, database sintetico **mi_ledger_test** e Playwright; usare `-PlaywrightModule` se necessario. Le fixture usano tabelle di prova: leggere gli script prima di predisporre il servizio. La suite ordinaria non sostituisce queste prove né un collaudo del deployment.

## Asset e pacchetti

Per ogni nuovo pacchetto dei sorgenti aggiornati incrementare il numero nell'intestazione del plugin e in `MI_VERSION`. Usare `modulo-iscrizioni-<versione>.zip` e, per la variante pubblica, `modulo-iscrizioni-<versione>-pubblico.zip`; evitare suffissi di data, audit o candidato in sostituzione dell'incremento di versione. Conservare solo le ultime cinque versioni in `dist`.

Gli asset modificabili sono in `assets`; quelli in `assets/min` si generano con `tools/asset-build/build.cjs`. Vedere la [guida asset](../tools/asset-build/README.md).

Il comando seguente genera i bundle Apps Script dalla cartella sorgente corrente:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "node tools/prepara-codice-workspace.mjs"
```

Per il progetto autonomo usare `Codice-Workspace-Progetto-<versione>.gs` oppure il bundle equivalente `codice.js`, verificando bootstrap e manifest. Il file senza `Progetto` è il bundle storico. Non caricare contemporaneamente file aggregati e singoli sorgenti che duplicano le funzioni.

Il protocollo di firma WordPress/Apps Script e le capacità di proiezione vanno aggiornati in modo coordinato; il canale email corrente richiede la configurazione e le autorizzazioni descritte in [ATTIVAZIONE_EMAIL_GAS](ATTIVAZIONE_EMAIL_GAS.md). Non usare le istruzioni del vecchio candidato MailApp, ora rimosso. Prima di distribuire confrontare sorgenti, contenuto ZIP e configurazione privata/pubblica; i pacchetti non certificano l'installazione.

## Lavoro residuo

I bug confermati e i casi da progettare sono nell'[analisi corrente](audit-sistema-2026-10-03.md). Le vecchie osservazioni sulle prestazioni non devono essere ripresentate come misure attuali: la versione corrente ha già script condizionali, pannelli differiti, riepilogo aggregato, controllo Google su richiesta ed email in background.

Restano aree da misurare: costo del modello completo in cache, scansioni per filtri avanzati, ricerca normalizzata nel saldo pubblico e caricamento di storici ampi. Usare i benchmark e confrontare gli stessi risultati, inclusi accenti, alias, annullamenti e scadenze. Le misure sintetiche storiche non rappresentano la latenza in produzione.

Le verifiche del cutover, dei backup, dell'archiviazione e dei grandi payload restano nel piano di dismissione; questo intervento non ne aggiorna lo stato. Non eliminare backup applicativi o dati in base alla sola età della documentazione.

## Pulizia del 3 ottobre

Rimossi 31 documenti storici e 47 elementi di distribuzione (file o cartelle), per 48.543.430 byte complessivi. Conservate le versioni 3.26.213–3.26.217, tutte le loro varianti disponibili, i sorgenti, i test e i backup. L'elenco esatto delle rimozioni è nel manifest locale `.tmp/pulizia-sviluppo-2026-10-03.json`.

Verificati i nove abbinamenti ZIP/checksum già disponibili e i collegamenti Markdown della documentazione corrente. Il controllo di sanitizzazione ora esclude i file che Git segnala eliminati dal checkout anche prima dello staging; gli altri errori di lettura continuano a interrompere la verifica.

Successivo confezionamento 3.26.218: conservate le distribuzioni 3.26.214–3.26.218; eliminati la 3.26.213 e i candidati 3.26.217 con suffissi audit/corrente, ora sostituiti dalla nuova versione.
