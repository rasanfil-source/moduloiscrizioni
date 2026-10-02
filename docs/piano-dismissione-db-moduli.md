# Piano di modernizzazione e dismissione di DB_MODULI

Questo piano è la checklist di rilascio, non un'autorizzazione a cancellare dati. MySQL/WordPress rimane la fonte autorevole; ogni foglio evento è una proiezione ricostruibile. Il vecchio workbook resta disponibile come rollback finché tutti i criteri finali non sono dimostrati nell'ambiente reale.

## 1. Verifiche riproducibili

- Eseguire `pwsh.exe -NoLogo -NoProfile -File .\tools\verify.ps1` con Node.js e PHP CLI 8.3 + `mbstring`.
- La variante `-SkipPhp` è solo un controllo parziale. Conservare il risultato dei test, del lint PHP, degli asset e della sanitizzazione.
- Prima del cambio deployment eseguire il controllo PHP firmato in sola lettura: impostare `MI_WORKSPACE_SECRET` (e facoltativamente `MI_WORKSPACE_URL`), quindi `php tools/verifica-deployment-workspace.php --url=https://script.google.com/macros/s/.../exec`. Il comando accetta soltanto un progetto autonomo con proiezione diretta, prelievo grandi istantanee attivo e workbook centrale escluso; il segreto non va passato sulla riga di comando.

## 2. Velocità della gestione

- Filtri, ordinamento e paginazione della lista ordinaria devono leggere da MySQL solo la pagina richiesta.
- I filtri avanzati devono avere risultati e conteggi equivalenti al selettore di riferimento, anche con nomi accentati, persone annullate e ricerca per partecipante nelle prenotazioni.
- La scansione avanzata ora unisce i blocchi con lo stesso ordinamento naturale del selettore di riferimento e conserva al massimo `offset + limit` righe; la ricerca con accenti non applica una preselezione SQL potenzialmente incompleta. Verificare con un database reale, in particolare persone di prenotazioni annullate. L'ordinamento della pagina ordinaria eseguito da SQL non è ancora dimostrato equivalente all'ordinamento naturale PHP.
- Misurare query, righe lette e tempo per eventi piccoli e grandi; separare gli aggregati necessari alla pagina dagli aggregati dell'intero evento.
- Il benchmark sintetico senza dati personali si esegue con `php tools/benchmark-management.php`; misura 500 e 10.000 righe e verifica che la scansione avanzata conservi soltanto il prefisso necessario. Query e tempi del database reale vanno ancora acquisiti durante il collaudo.

## 3. Coda e asset

- Le modifiche devono accodare un solo refresh per evento; i vecchi cron per singola iscrizione non devono ricrearsi.
- Retry e backoff devono sopravvivere alla fine della richiesta; verificare consegne duplicate, errori Google, concorrenza e cancellazione in corso.
- Nessun hash di file sul percorso delle richieste ordinarie; aggiornare versione e documentazione dopo il collaudo.

## 4. Proiezione diretta

- Pubblicazione, nuova iscrizione, pagamento e gestione devono produrre una proiezione MySQL → foglio evento senza letture o scritture su DB_MODULI.
- Ripetere la stessa richiesta non deve duplicare un foglio, inviare email due volte o riscrivere celle invarianti.
- Un foglio mancante deve essere ricreato dall'evento MySQL. Le modifiche manuali devono essere lette, validate e confermate da WordPress prima di qualunque sovrascrittura.
- Il trasferimento alternativo per gli eventi oltre 1,8 MB compressi usa un prelievo autenticato Apps Script → WordPress con HMAC già in uso; entrambe le estremità confrontano hash e impronta. Collaudare il percorso su deployment reale, incluso il comportamento a dati modificati durante il prelievo. Restano limiti espliciti di 8 MB compressi e 12 MB di JSON per l'elaborazione Apps Script: misurare eventi reali prima di confermare che siano sufficienti.

## 5. Rimozione delle dipendenze applicative

- Inventariare ogni operazione raggiungibile via Web App, menu, trigger e WordPress che consulta DB_MODULI: configurazione, registro fogli, audit, email, gruppi, report e viste aggregate. Il [censimento locale](inventario-db-moduli.md) distingue le rotte dirette dai percorsi storici; confermarlo sui trigger e sul deployment reali.
- Spostare dati autorevoli in MySQL, configurazione privata nelle proprietà dello script e viste nei fogli evento. Rimuovere o rendere esplicitamente inattive le rotte legacy; la verifica firmata del deployment deve attestare progetto autonomo, proiezione diretta, prelievo grandi istantanee e assenza di accesso al workbook centrale.
- Lo script Apps Script non può rimanere vincolato al workbook da archiviare: predisporre un progetto autonomo e una nuova distribuzione Web App con segreto e account mittente corretti.

## 6. Collaudo e cambio produzione

- Su copie e identità fittizie, verificare evento gratuito e a pagamento, pagamenti e rimborsi, iscrizioni concorrenti, retry dopo timeout, sincronizzazione inversa, email, ricostruzione, archiviazione e cancellazione.
- Eseguire un periodo di confronto tra MySQL e fogli evento; registrare differenze e tempi. Aggiornare plugin e Web App in ordine controllato, con vecchio deployment disponibile per rollback.
- Fare backup verificato di MySQL, dei fogli evento e di DB_MODULI. Solo dopo un collaudo completo disattivare i vecchi trigger e l'accesso applicativo al workbook, poi archiviarlo in sola lettura; non eliminarlo automaticamente.

## Stato corrente

- Verifica locale completa ripetuta il 21 settembre 2026 con PHP 8.3.33 portabile e `mbstring`: 333 test Node principali, 7 test cache, lint e test PHP, asset e sanitizzazione superati.
- Il plugin 3.26.216 e il progetto `MODULI AUTONOMO 3.26.149` sono installati in produzione. Il 2 ottobre 2026 il collegamento firmato e la verifica delle capacità autonome sono riusciti: il deployment dichiara proiezione diretta e prelievo firmato, e rifiuta il workbook centrale.
- Endpoint WordPress, mittente e segreto condiviso sono configurati nelle proprietà del nuovo progetto. Il progetto storico, il vecchio deployment e `DB_MODULI` restano disponibili come rollback.
- Il 2 ottobre 2026 è stato completato il cutover dei trigger. Il progetto autonomo mantiene un solo trigger `Head` per `avviaCronWordPress`, temporale ogni 5 minuti, coerente con `attivaCronWordPress()`; le esecuzioni osservate alle 21:16:45 e 21:21:45 si sono concluse con successo rispettivamente in 4,108 e 3,993 secondi, con percentuale errori visualizzata pari a 0%. Dal progetto storico sono stati eliminati, nell'ordine, `sincronizzaFogliEventi` e il precedente `avviaCronWordPress`; la pagina Attivatori conferma zero trigger. Progetto e workbook storici restano intatti come rollback.
- Il controllo reale dell’editor evento aveva individuato un avviso PHP sul campo descrittivo del cellulare. La correzione introdotta nella versione 3.26.150 è inclusa nella 3.26.216 corrente: warning assente nell’evento 8083 e asset pubblici serviti con la versione aggiornata.
- Il deployment autonomo è stato aggiornato alla versione 3, sul medesimo URL, per correggere la decompressione gzip delle proiezioni. Non è necessario un ulteriore deployment.
- Il collaudo funzionale in produzione è concluso su due eventi esclusivamente fittizi: gratuito, pagamento e rimborso simulati, proiezione e ricostruzione, modifica Sheet → MySQL, retry ed email in modalità Prova. Le notifiche finali risultano `SENT`, gli eventi e i dati di prova sono stati eliminati, i fogli spostati nel cestino e la modalità email ripristinata a `OPERATIVO`.
- Il 2 ottobre 2026 è stata creata in Google Drive la cartella `BACKUP DISMISSIONE MODULI 2026-10-02`. Contiene copie verificate di `DB_MODULI` e dei fogli evento referenziati 8039 e 8083. Gli originali non sono stati spostati o modificati.
- Il backup MySQL pre-cutover è `parrocch19749_2026-10-02_pre-cutover.sql.gz`, conservato localmente fuori dal repository. Il gzip è integro; il dump phpMyAdmin contiene struttura e dati (73 `CREATE TABLE`, 236 `INSERT INTO`) e tutte le tabelle operative `wp_mi_*` richieste. Dimensione compressa 1.286.249 byte, non compressa 12.538.588 byte; SHA-256 `4D07D8C54EB432782C9C531E9B910EA906593C4DC439988F406DBC4F1067B8C7`.
- È attivo il monitoraggio `Sorveglia cutover MODULI`, ogni 6 ore fino al completamento della finestra di 48 ore. Deve restare silenzioso in assenza di anomalie e segnalare errori, azioni richieste o l'esito finale prima della sola lettura del rollback.

## Punto di ripresa

La prossima sessione deve partire da questa sequenza, senza ripetere il collaudo funzionale già concluso:

1. Misurare sul database reale query, righe lette e tempi per eventi piccoli e grandi; confermare ordinamento SQL, ricerca con accenti e comportamento delle prenotazioni annullate.
2. Collaudare i casi di carico ancora aperti: iscrizioni simultanee, proiezione oltre 1,8 MB, dati modificati durante il prelievo e adeguatezza dei limiti di 8 MB compressi e 12 MB JSON. Verificare separatamente l’archiviazione, già distinta dalla cancellazione collaudata.
3. Eseguire e documentare un periodo di confronto MySQL ↔ fogli evento, registrando differenze e tempi.
4. **Completato il 2 ottobre 2026:** creato e verificato il backup MySQL; le copie dei fogli evento 8039 e 8083 e di `DB_MODULI` sono raccolte in `BACKUP DISMISSIONE MODULI 2026-10-02`. Il progetto storico, il vecchio deployment e il dump MySQL restano utilizzabili per il rollback.
5. **Completato il 2 ottobre 2026:** il trigger autonomo `avviaCronWordPress` ogni 5 minuti è attivo e ha completato due esecuzioni consecutive verificate; i due trigger storici sono stati eliminati nell'ordine previsto e `MODULI` mostra zero trigger. Osservare per 24–48 ore log, coda email e sincronizzazioni prima di rendere il rollback di sola lettura.
6. Rendere `DB_MODULI` e il progetto storico di sola lettura e conservarli come rollback. Non svuotare il cestino dei fogli di prova e non eliminare automaticamente il workbook centrale.

Fino al completamento dei punti precedenti non sono autorizzati la cancellazione di `DB_MODULI`, la rimozione del progetto storico o la disattivazione anticipata dei suoi trigger. Alla ripresa non occorre caricare un nuovo plugin né distribuire nuovamente Apps Script, salvo che le prove di carico evidenzino una correzione ulteriore.
