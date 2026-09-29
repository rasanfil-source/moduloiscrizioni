# 3.26.209 — Correzioni dell’audit del 29 settembre

Corregge gli otto difetti riprodotti nell’[audit](audit-sistema-2026-09-29.md), conservando le modifiche della 3.26.208.

## Correzioni

- **Sincronizzazione Google:** la base di confronto viene registrata dopo la formattazione monetaria finale. Per le basi precedenti si riconosce la sola differenza di visualizzazione verificando che il valore numerico della cella sia rimasto identico. Modifiche manuali ai dati e agli importi continuano a bloccare la sovrascrittura; il recupero di un giornale già confermato conserva le modifiche intervenute successivamente.
- **Debiti individuali:** il riepilogo usa quote e versamenti noti anche quando sono da verificare le sole caparre. Il credito di una persona non compensa il debito di un’altra.
- **Coda email:** scansione a blocchi con avanzamento persistente distinto per modalità; un evento bloccato non impedisce di raggiungere le email successive. Ogni worker conserva il limite di dieci tentativi di consegna e legge al massimo dieci blocchi da dieci righe. I messaggi saltati restano recuperabili.
- **Rettifiche:** il comando dipende dai permessi e dagli stati ammessi dal backend, anche senza servizi e per prenotazioni annullate o scadute.
- **Saldo pubblico:** anteprima, salvataggio e messaggio usano la posizione futura calcolata sul server. La scadenza riaperta è relativa alla conferma; il tempo trascorso dall’anteprima non invalida gli importi, mentre una modifica della scadenza salvata da parte della segreteria richiede una nuova verifica. Il retry conserva ricevuta ed email idempotenti.
- **Quantità servizi:** vengono rifiutati i codici che diventano duplicati dopo la normalizzazione, prima di creare l’iscrizione.
- **Creazione foglio:** un errore temporaneo Drive o Sheets mantiene l’identificativo già registrato. La ricreazione automatica resta ammessa soltanto per un file verificato nel cestino e in assenza di un identificativo esplicito fornito da WordPress.
- **Caparre versate:** la tessera usa il contatore individuale anche quando una prenotazione familiare è ancora in attesa degli altri versamenti.

Aggiornata anche la verifica dell’etichetta «Importa modifiche dal foglio» introdotta nella 3.26.208.

## Verifiche locali

- 382 test Node, inclusi sei nuovi casi Workspace su formati, migrazione delle basi, recupero del giornale e retry Drive/Sheets.
- 50 suite PHP del verificatore, comprese quattro nuove suite su quantità duplicate, scadenza comunicata, debiti con caparre incoerenti e avanzamento della coda email.
- 43 file PHP controllati sintatticamente, sette test degli asset, corrispondenza degli asset minificati e sanitizzazione.
- Quattro suite browser: regressioni dell’audit, riepilogo e pannelli su richiesta, gestione individuale e stampa, saldo pubblico desktop/mobile. Verificati anche rettifica senza permesso nascosta e stato scaduto ammesso.

Le riproduzioni usano dati sintetici, database e servizi remoti simulati. Nessun invio reale, collaudo MySQL in questa sessione o operazione sul deployment WordPress/Google.

Nuovi test persistenti: `workspace-apps-script/tests/audit-2026-09-29.test.mjs`, `wordpress-plugin/tests/option-key-duplicates.php`, `public-balance-deadline-receipt.php`, `management-deposit-totals.php`, `email-queue-fairness.php` e `tools/test-audit-2026-09-29-browser.cjs`. I test PHP sono inclusi nel verificatore e nel workflow GitHub.

Log locali: `.tmp/fix-audit-2026-09-29-verify-final.log` e `.tmp/fix-audit-2026-09-29-browser.log`.

## Distribuzione

1. Installare il plugin WordPress **3.26.209** dallo ZIP preparato in `dist`.
2. Aggiornare il progetto Apps Script autonomo con `dist/Codice-Workspace-Progetto-3.26.209.gs` e pubblicare una nuova versione della Web App. Non occorrono nuovi permessi OAuth.
3. Verificare la sincronizzazione su un evento sintetico. Le modifiche manuali ancora pendenti nel foglio vanno importate o risolte con il normale percorso di confronto.

Generazione: `tools/package-3.26.209.ps1` prepara ZIP locale e pubblico, ne verifica tutti i file rispetto ai sorgenti e scrive i checksum. Il pacchetto pubblico esclude la configurazione privata. `node tools/prepara-codice-workspace.mjs` genera entrambi i bundle GAS; quello con `-Progetto-` è destinato al progetto autonomo.

I pacchetti sono preparati localmente: il sito e la distribuzione Apps Script non vengono aggiornati automaticamente.
