# Audit del sistema — 29 settembre 2026

**Aggiornamento successivo all’audit:** gli otto difetti e il test obsoleto sono stati corretti nei sorgenti della [3.26.209](rilascio-3.26.209.md). Le riproduzioni originali qui sotto documentano la 3.26.208; le nuove prove di regressione verificano il comportamento corretto. L’aggiornamento non è stato installato in produzione.

Analizzati i sorgenti locali del plugin WordPress **3.26.208** e di Google Apps Script, su base Git `9947c5e`, comprese le modifiche locali preesistenti. Risultato: **8 difetti applicativi riprodotti in ambiente sintetico**, più un test non aggiornato che interrompe il verificatore generale.

I sorgenti applicativi sono rimasti invariati. Sono stati aggiunti soltanto questo rapporto e script/log diagnostici in `.tmp`. Nessun accesso al sito, invio email, modifica di fogli reali o distribuzione. I risultati descrivono il codice locale; la presenza degli stessi problemi nel deployment installato richiede una verifica separata.

## 1. P1 — La formattazione degli importi blocca la sincronizzazione successiva

Riferimento: [ProiezioneIncrementale.gs, riga 118](../workspace-apps-script/src/ProiezioneIncrementale.gs#L118), con applicazione del formato monetario alla riga 123.

La scrittura applica prima il formato testo ai blocchi, salva in `_MI_BASE` i valori visualizzati e solo dopo ripristina il formato a due decimali delle colonne economiche. Il confronto successivo legge nuovamente i valori visualizzati: la formattazione effettuata dal programma viene interpretata come una modifica manuale di una colonna protetta.

Riproduzione: importo numerico **12,50 €**. Il simulatore salva `12.5` nella base e mostra `12,50` dopo il cambio formato. Senza alcuna modifica umana, `modificheCorrentiFoglio_` restituisce `Colonna non modificabile: balance`; una successiva proiezione segnala un conflitto e non aggiorna nemmeno il nome modificato in WordPress.

La fixture esistente non simula gli effetti di `setNumberFormat`; quella diagnostica li aggiunge senza cambiare il codice applicativo. La dipendenza di `getDisplayValues()` da formato e locale è documentata da [Google](https://developers.google.com/apps-script/reference/spreadsheet/range#getDisplayValues()). La prova è locale, non eseguita sul servizio Google.

Correzione proposta: rendere coerenti formattazione finale e salvataggio della base, anche nel recupero del giornale; oppure confrontare valori normalizzati indipendenti dalla presentazione. Prevedere il recupero delle basi già incoerenti.

## 2. P2 — Il riepilogo può nascondere un debito individuale

Riferimento: [class-mi-management-service.php, riga 344](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php#L344), con ulteriore uso di `known` alla riga 361.

La 3.26.207 distingue la certezza di quote e pagamenti (`totals_known`) dalla coerenza delle caparre. Il riepilogo gestione usa ancora `known` per scegliere il saldo individuale: se le sole caparre sono incoerenti, torna al saldo netto dell'intera prenotazione e compensa crediti e debiti di persone diverse.

Riproduzione con due quote da **100 €**: A ha versato **200 €**, B nulla. Quote e attribuzioni sono note; la sola somma delle caparre individuali non coincide con la caparra complessiva. Il dettaglio pagamenti indica correttamente **100 € da incassare**, ma il riepilogo evento indica **0 €**. Ripristinando la coerenza delle caparre, il riepilogo torna a 100 €.

Correzione proposta: usare `totals_known` per importi e residui, e `deposits_known` soltanto per caparre; mantenere distinti crediti e debiti personali.

## 3. P2 — Un'eliminazione interrotta può fermare le email degli altri eventi

Riferimento: [class-mi-spedizione-email.php, riga 410](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-spedizione-email.php#L410), con esclusione dell'evento alla riga 416.

Ogni esecuzione seleziona le prime dieci email pendenti ordinate per ID. Le righe appartenenti a un evento in eliminazione vengono saltate senza cambiare stato, priorità o data del prossimo tentativo. Se occupano tutto il blocco, le email successive non vengono mai raggiunte finché l'eliminazione resta sospesa.

Riproduzione: dieci email pendenti dell'evento 42, con eliminazione interrotta, seguite da una email valida dell'evento 43. Dopo tre esecuzioni del worker: **zero chiamate al trasporto**, undicesima email ancora `PENDING`, zero tentativi, tre nuove pianificazioni. Nel caso di controllo, rimuovendo il blocco, tutte le undici email vengono spedite in due esecuzioni simulate.

Correzione proposta: scorrere oltre le righe non elaborabili o differirle esplicitamente, conservando il blocco delle comunicazioni dell'evento in eliminazione.

## 4. P2 — La rettifica del dovuto scompare quando serve

Riferimento: [portal-management.js, riga 747](../wordpress-plugin/modulo-iscrizioni/assets/portal-management.js#L747).

Il modulo «Rettifica il dovuto della prenotazione» è contenuto nel blocco dei servizi, che richiede opzioni configurate e stato `CONFIRMED` oppure `PENDING_PAYMENT`. Il permesso `can_adjust_due` non basta a renderlo disponibile.

Riprodotti nel browser con il JavaScript reale:

- prenotazione confermata a quota unica, senza servizi: `can_adjust_due=true`, modulo assente;
- prenotazione annullata, con servizi: `can_adjust_due=true`, modulo assente;
- controllo con prenotazione confermata e servizi: modulo presente.

Il backend ammette esplicitamente la rettifica per `CANCELLED` e `EXPIRED` (`class-mi-management-service.php:824`); il messaggio di annullamento invita inoltre a usare questo comando. Il difetto rende tale operazione irraggiungibile da questa schermata.

Correzione proposta: separare la visualizzazione della rettifica da quella dei servizi e allineare gli stati ammessi al backend.

## 5. P2 — Il saldo comunica la scadenza precedente alla modifica

Riferimento: [class-mi-public-balance.php, riga 245](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-public-balance.php#L245), con aggiornamento della scadenza alla riga 286 e accodamento email alla riga 291.

Il riepilogo viene costruito prima di calcolare e salvare l'eventuale nuova scadenza. Se una modifica economica riapre il pagamento, risposta ed email conservano il termine precedente, anche se assente.

Riproduzione: aggiunta di un transfer da 10 € a una quota da 500 €, con vecchia scadenza trascorsa oppure nulla. Il database simulato salva un nuovo termine a **48 ore**; risposta ed email mostrano rispettivamente la vecchia data oppure nessuna data. Il calcolo della nuova scadenza è quello reale del servizio.

Correzione proposta: calcolare prima la posizione futura completa e usare la stessa scadenza per anteprima, conferma, salvataggio ed email, preservando la verifica dell'anteprima.

## 6. P2 — Il limite di quantità dei servizi è aggirabile

Riferimento: [class-mi-registration-service.php, riga 1495](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php#L1495).

Il validatore normalizza il codice di ciascuna opzione, ma non rifiuta duplicati dopo la normalizzazione. Il limite viene applicato alla singola chiave ricevuta, non al totale dello stesso servizio.

Riproduzione attraverso `create()`: servizio cumulabile con `max_quantity=1`. `{"pullman-a":2}` è rifiutato; `{"pullman-a":1,"PULLMAN-A":1}` è accettato e salva due righe con il medesimo codice. Quota base 100 €, servizio 10 €: totale salvato 120 €. **Richiede una richiesta costruita o modificata: il modulo ordinario non genera queste chiavi duplicate.** Il caso non riguarda un servizio con gruppo esclusivo esplicito.

Correzione proposta: rifiutare codici duplicati normalizzati o aggregarne le quantità prima di verificare limiti e alternative.

## 7. P2 — Un errore temporaneo Google può duplicare il foglio appena creato

Riferimento: [ProiezioneDiretta.gs, riga 70](../workspace-apps-script/src/ProiezioneDiretta.gs#L70).

Quando WordPress non conosce ancora `sheet_id`, Apps Script recupera il foglio dal proprio registro. Se Drive o `openById` sollevano un errore, il `catch` cancella il riferimento e crea un nuovo foglio, anche quando il problema è temporaneo.

Riproduzione: primo foglio creato e registrato, risposta non ricevuta da WordPress; nuovo tentativo con `sheet_id` vuoto e errore temporaneo Drive. Risultato: **due fogli**, registro sostituito con il secondo e primo foglio abbandonato. La prova usa API Google simulate.

Correzione proposta: conservare il riferimento e riprovare gli errori temporanei. La ricreazione deve dipendere da un'assenza verificata, non da qualunque eccezione.

## 8. P2 — Il contatore delle caparre considera lo stato dell'intera prenotazione

Riferimento: [portal-management.js, riga 302](../wordpress-plugin/modulo-iscrizioni/assets/portal-management.js#L302).

La tessera «Iscritti» mostra «Caparra versata» contando le persone in prenotazioni `CONFIRMED`. È già disponibile il contatore individuale `payment_counts.deposit_covered`, ma la tessera non lo usa.

Riproduzione PHP e browser: prenotazione di due persone, una sola ha versato la sua caparra di 30 €. Il backend restituisce correttamente `deposit_covered=1`; la prenotazione rimane `PENDING_PAYMENT` e la tessera mostra **«Caparra versata: 0»**.

Correzione proposta: usare il contatore individuale già calcolato, con gli stessi criteri del dettaglio pagamenti.

## Verifica automatica da aggiornare

[structure.test.mjs, riga 2549](../wordpress-plugin/tests/structure.test.mjs#L2549) cerca ancora il testo esatto `Sincronizza`; la 3.26.208 lo ha sostituito intenzionalmente con `Importa modifiche dal foglio`.

Questo è l'unico fallimento della suite Node: **375 superati su 376**. Interrompe `tools/verify.ps1` prima dei controlli successivi. È una verifica obsoleta, distinta dagli otto difetti applicativi. Anche il workflow GitHub esegue quella suite.

I controlli successivi sono stati eseguiti separatamente, mantenendo l'elenco delle suite PHP ricavato dal verificatore: **46 suite PHP superate**, **43 file PHP validi**, **7 test asset superati**, asset generati aggiornati e sanitizzazione superata.

## Riproduzioni e limiti

Tutti gli script diagnostici sotto indicati sono stati eseguiti con codice di uscita 0. Le loro asserzioni attestano la presenza dei difetti, non la loro correzione.

| Script nella cartella `.tmp` | Evidenza |
| --- | --- |
| `audit-2026-09-29-workspace.mjs` | Conflitto monetario e duplicazione del foglio. |
| `audit-2026-09-29-management.php` | Debito nascosto nel riepilogo e dati reali del contatore caparre. |
| `audit-2026-09-29-management-browser.cjs` | Rettifica assente in due scenari, controllo positivo e contatore errato; nessun errore JavaScript. |
| `audit-2026-09-29-registration-deadline.php` | Scadenza della risposta/email diversa da quella salvata. |
| `audit-2026-09-29-registration-options.php` | Limite quantità aggirato attraverso la creazione reale. |
| `audit-2026-09-29-outbox.php` | Email di altro evento mai selezionata durante un'eliminazione sospesa. |
| `audit-2026-09-29-remaining.ps1` | Asset, sanitizzazione, lint e suite PHP dopo l'interruzione Node. |

I log hanno gli stessi nomi con estensione `.log`; l'esecuzione originale del verificatore è in `audit-2026-09-29-verify.log`. Runtime usati: PowerShell 7, Node, PHP locale e Edge headless. WordPress, database e servizi remoti sono simulati nelle riproduzioni; i calcoli e i percorsi applicativi interessati sono quelli dei sorgenti. Non sono stati eseguiti test con MySQL reale né collaudi end-to-end sul deployment WordPress/Google.

Priorità proposta: correggere prima la base della sincronizzazione, poi la visualizzazione dei debiti e l'accessibilità delle rettifiche; seguono coda email, scadenze comunicate, limiti dei servizi, recupero creazione foglio e contatore caparre. Il test obsoleto va aggiornato per ripristinare il verificatore completo.
