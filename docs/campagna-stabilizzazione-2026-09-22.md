# Campagna tecnica — 22 settembre 2026

## 1. Executive summary

Interventi locali sullo stato di lavoro esistente, senza deploy, invii reali, migrazioni o ricostruzione dei pacchetti durante la campagna. Le numerose modifiche già preparate per 3.26.151 sono state conservate; non sono conteggiate come nuove correzioni. Il successivo confezionamento e deploy sono documentati nel [rilascio 3.26.152](rilascio-3.26.152.md).

- **2 classi di difetti critici R1** corrette: transazioni non avviate ma scritture proseguite; letture SQL fallite interpretate come conteggi disponibili/zero.
- **5 difetti funzionali R2** corretti: commit non verificati; errori di cron dopo commit; retry concorrente sull'ultimo posto; notifiche di annullamento fuori transazione; conferma delle comunicazioni prima di verificare il commit.
- **1 ottimizzazione R3**: eliminata la copia quadratica del catalogo servizi nel riepilogo.
- Riparati tre test esistenti: doppio caricamento PHP, fixture presenze incompleta e selettori/aspettative camere riferiti a una UI precedente.
- Restano limiti concreti sulla gestione delle presenze massive e sul replay del collegamento lista d'attesa, descritti sotto. Le prove locali non certificano latenza o disponibilità della produzione.

## Modello verificato e punti critici

| Percorso | Proprietario e protezioni | Rischio |
| --- | --- | --- |
| Iscrizione singola/multipla | `MI_Registration_Service::create`, revisione pubblicata, convalida per persona, contatori evento/tipologia, chiave idempotente e outbox nella transazione | R1 |
| Pagamenti, rimborsi, storni | `MI_Payment_Ledger`, importi in centesimi, lock prenotazione, hash richiesta, attribuzioni `MI_Payment_People`, movimenti append-only | R1 |
| Servizi e sistemazioni | `MI_Management_Service`, anteprima, versione comprensiva dei pagamenti, quote personali, audit e commit | R1 |
| Camere | lock evento condiviso, versione inventario, capienza e ricevuta persistente; test su ultimo posto e progressivi concorrenti | R1 |
| Annullamento e attesa | contatori e stato transazionali; proposta riservata, scadenza UTC, token personale; annullamento distinto dal rimborso | R1 |
| Email | outbox persistente, modalità anteprima/prova/operativo, chiavi univoche, acquisizione atomica del lavoro | R2 |
| MySQL → Google | snapshot, hash e generazione; coda per evento; journal `_MI_WRITE`, base `_MI_BASE`, protezioni e retry Apps Script | R1/R2 |
| Google → MySQL | confronto prima/dopo, conferma nel portale autorizzato, ricevute canoniche; modifiche locali preservate | R1 |
| Portale | AJAX con sessione/nonce/ambito; riepilogo e pagine, cache revisionata, bozze e retry conservati in memoria | R2/R3 |

Letti README, PROGETTO, UX-CONTRACT, DESIGN, SECURITY, schema dati, note degli audit precedenti, README dei componenti, workflow CI e strumenti di test. Il modello storico in PROGETTO e alcuni test camere non descrivevano più la UI corrente. Conservati i comportamenti espliciti attuali, aggiornando la descrizione delle camere.

## 2. Bug corretti

### A. Avvio della transazione ignorato

**Priorità:** R1/P0 per possibile scrittura parziale. **File:** `class-mi-registration-service.php`, `class-mi-management-service.php`, `class-mi-spedizione-email.php` in `wordpress-plugin/modulo-iscrizioni/includes`.

**Problema e causa:** `START TRANSACTION` non controllato: con risposta `false`, le successive scritture potevano essere autocommit. **Correzione:** interrompere prima di mutare. **Test:** `registration-failures.php`, `email-transaction-failures.php`, estensione di `attendance-inline-innodb.php`; zero scritture/audit se BEGIN fallisce.

### B. Commit non verificato

**Priorità:** R2/P1. **File:** servizio iscrizioni e spedizione email. **Problema e causa:** risposta di successo anche con `COMMIT=false`. **Correzione:** controllare il commit, restituire errore senza dichiarare successo né pianificare il seguito. **Test:** failure injection su creazione, annullamenti, rinuncia all'offerta e comunicazioni. Un errore di rete sul commit resta un esito incerto da riconciliare mediante retry, non una prova che il server non abbia salvato.

### C. Errori di pianificazione dopo commit

**Priorità:** R2/P1. **File:** i tre servizi sopra. **Problema:** eccezioni di cron/cache trasformavano scritture già confermate in errori. **Correzione:** isolare la pianificazione, mantenendo outbox e stato PENDING autorevoli. **Test:** errori simulati di cron e transient; `room-postcommit-innodb.php` verifica camera effettivamente salvata e replay senza duplicati. Il recupero email periodico è già collegato a `mi_sync_workspace_pending`.

### D. Letture SQL fallite nei contatori e negli annullamenti

**Priorità:** R1/P0 per possibile incoerenza di disponibilità/stato. **File:** servizio iscrizioni. **Causa:** `null`/array vuoto utilizzati come zero, e `last_error` cancellato dalle query successive. **Correzione:** controlli immediati sulle letture dei partecipanti e della lista d'attesa; verifica dei lock contatori e della loro creazione. **Test:** fallimenti di conteggio, lettura persone, lock evento/tipologia e lettura candidati. Rollback verificato; nessuna disponibilità inventata.

### E. Retry concorrente sull'ultimo posto

**Priorità:** R2/P1. **File:** servizio iscrizioni. **Causa:** controllo idempotenza prima del lock; una seconda richiesta vedeva il posto appena consumato dalla prima e usciva con esaurimento. **Correzione:** rilettura corrente `FOR UPDATE` della chiave dopo il lock evento, prima dei controlli di data/capienza. **Test:** interleaving simulato in `registration-failures.php`: prenotazione concorrente già salvata, nessuna nuova scrittura, risposta `replayed` con stesso codice.

### F. Notifiche di annullamento perse o duplicate

**Priorità:** R2/P1. **File:** servizio iscrizioni. **Causa:** lettura dello stato prima del lock e inserimento email dopo commit; retry saltava la notifica mancante, richieste concorrenti potevano entrambe accodarla. **Correzione:** stato e due notifiche richieste nella medesima transazione; il percorso già annullato non accoda altro. **Test:** outbox KO comporta rollback prima del commit; secondo annullamento non aggiunge scritture. Nessuna email viene inviata sotto lock.

### G. Ricevuta comunicazione senza commit confermato

**Priorità:** R2/P1. **File:** spedizione email. **Causa:** transient idempotente scritto dopo un COMMIT non verificato; eventuale retry poteva essere saltato pur senza email persistite. **Correzione:** commit controllato; cache e cron successivi e indipendenti; UNIQUE `origin_key` rimane la protezione persistente. **Test:** 8 casi in `email-transaction-failures.php`, compreso retry con cache indisponibile.

## 3. Performance

**Percorso:** apertura a cache fredda del riepilogo e ricostruzioni dopo mutazione. **Problema:** `array_merge` ripetuto nell'accumulo delle opzioni copiava ogni volta tutte le voci precedenti. **Prima:** costo cumulativo quadratico. **Dopo:** append lineare, stessa deduplicazione finale e precedenza delle definizioni. Nessun indice o cache aggiunto.

Benchmark locale PHP, mediana di 5 esecuzioni, dati sintetici, confronto di equivalenza del risultato:

| Gruppi × 10 voci | Prima, ms | Dopo, ms |
| --- | ---: | ---: |
| 100 | 0,257 | 0,078 |
| 1.000 | 27,238 | 1,289 |
| 10.000 | 7.972,819 | 11,781 |

Comando: `pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe tools/benchmark-summary-options.php"`.

È un benchmark dell'aggregazione PHP, **non dell'endpoint**. Non misura SQL, rete, Google o TTFB. Il percorso caldo usa già il modello in cache; le proiezioni Google incrementali già presenti scrivono una riga operativa e una riga base per una persona cambiata su 1.000, come verificato dalla suite esistente. Nessun nuovo miglioramento Apps Script viene attribuito a questa campagna.

## 4. Race condition e idempotenza

**Trovate e corrette:** retry dopo attesa del lock sull'ultimo posto; notifiche annullamento accodate fuori dalla sezione serializzata; falsi errori dopo commit; ricevuta email memorizzata senza commit verificato.

**Verificate senza nuove patch:** concorrenza incassi, quote individuali e rimborsi, ultimo posto camera, progressivi automatici, cambio sistemazione multi-prenotazione, richieste con hash diverso, scambi camere e ricevute Sheets. I test InnoDB usano connessioni/processi reali dove previsti dalle fixture.

**Ancora aperte:** presenze massive senza identificativo persistente; replay del token di un'offerta già conclusa. Vedi limiti sotto.

## 5. Sicurezza

Nessuna nuova vulnerabilità di autorizzazione confermata o corretta. Conservati controlli sessione, sospensione, capability, nonce e ambito evento; firma HMAC e anti-replay per Workspace; validazioni server degli importi e delle appartenenze. I test includono evento non autorizzato, richieste alterate, importi non validi, CSV e output sicuro. Nessun dato reale introdotto e nessuna chiamata al sito o ai fogli operativi.

## 6. Test e comandi

Runtime locale: PHP 8.3 con mbstring/mysqli; MariaDB 11.4.8, soltanto `127.0.0.1:33317`, database sintetico `mi_ledger_test`; browser Edge headless con Playwright. Le fixture InnoDB ricreano tabelle sintetiche e vanno usate esclusivamente su quel database.

1. `pwsh.exe -NoLogo -NoProfile -Command "& ./tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe"`: **superato**, 341 test Node + 7 test asset, verifica asset, sanitizzazione, lint PHP e 22 suite PHP, incluse le due nuove di failure injection. Log locale `.tmp/campaign-verify-final.log`.
2. `pwsh.exe -NoLogo -NoProfile -Command "& ./tools/verify-campaign.ps1 -PlaywrightModule '<percorso-modulo-playwright>'"`: **superato**, 11 suite PHP/InnoDB e 6 browser. Sostituire il segnaposto con il percorso locale del modulo Playwright. Lo script elenca esattamente le suite ed esce al primo errore. Log `.tmp/campaign-integration-final.log`.
3. `pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe -d extension_dir=.tmp/php-runtime/ext -d extension=mbstring wordpress-plugin/tests/registration-failures.php"`: 29 scenari, compresi baseline validi, BEGIN/COMMIT KO, conteggi e lock KO, cron KO, retry concorrente e outbox.
4. `pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe wordpress-plugin/tests/email-transaction-failures.php"`: 8 scenari su transazione, cache, cron e retry.
5. `pwsh.exe -NoLogo -NoProfile -Command "git diff --check"`: **superato**, nessun errore whitespace delle modifiche locali.

Le nuove regressioni sono state eseguite prima delle rispettive patch e fallivano. Le fixture browser sono state aggiornate sulla base dei controlli attuali, senza modificare il frontend per far passare i test. Verificati viewport 320/390 e fino a 1024 px, errori JavaScript, overflow, conservazione bozze, retry, recupero e permanenza sulla persona corretta. Non equivale a un collaudo PHP/WordPress nel browser di produzione.

## 7. Limiti e problemi residui concreti

- **Confermato:** `save_attendance_bulk` non riceve request ID/versione. Ripetere lo stesso invio aggiunge eventi audit; un retry tardivo può sovrascrivere una presenza successiva. La patch corrente protegge l'avvio della transazione, non introduce un nuovo protocollo di conflitto per questa UI. Serve estendere insieme endpoint, bozza e ricevuta persistente.
- **Confermato:** dopo accettazione/rinuncia, il token dell'offerta viene cancellato; un replay del link restituisce proposta non disponibile invece dell'esito precedente. Non duplica l'ammissione, ma il recupero UX dopo risposta persa resta incompleto. Conservare una ricevuta del token richiede definire durata e dati pubblicamente consultabili.
- **Da misurare:** la prima costruzione del riepilogo resta proporzionale all'intero evento; non eseguiti EXPLAIN su cardinalità operative, profiling del server WordPress o tempi Google. Il benchmark PHP non giustifica una previsione del tempo pagina.
- **Confermato, fuori dalla verifica locale:** codice e pacchetti distribuiti non sono allineati automaticamente. Le correzioni richiedono confezionamento e collaudo di rilascio separati; nessun deploy eseguito.

Il secondo riesame ha esteso le correzioni a comunicazioni e annullamento completo; test verdi non vengono presentati come prova di assenza assoluta di difetti. Non introdotti cambi di prodotto per i due protocolli residui.
