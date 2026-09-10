# Avanzamento locale della gestione — 10 settembre 2026

Il progetto complessivo non è ancora concluso. Nessuna pubblicazione sul sito, nessun invio email e nessuna cancellazione di Sheet sono stati eseguiti. Le verifiche InnoDB usano esclusivamente il database locale di prova `mi_ledger_test`, porta 33317.

## Implementato dopo la fase 1

- Riepilogo evento con viste Partecipanti e Prenotazioni. Ogni persona è visibile e ricercabile; contatti esplicitamente attribuiti al referente. Stato e filtro operativo si combinano con la ricerca. Il ritorno dalla scheda conserva ricerca, vista e numero di righe mostrate.
- Ricerca dei partecipanti aggiuntivi anche in Iscrizioni e Pagamenti. Iscrizioni ha navigazione fra pagine da 30 prenotazioni, con lettura della riga successiva per rilevare la pagina seguente.
- Scheda con contatti, richieste, opzioni individuali e d’ordine, tipologia, note dei movimenti, scadenza offerta e stato replica.
- Riutilizzo del modulo Pagamenti nella scheda con ordine preselezionato. Bozza protetta, scarto esplicito prima dell’invio, retry della stessa richiesta dopo errore e aggiornamento dei totali visibili. Accesso Pagamenti separato mantenuto.
- CSV di tutti i risultati filtrati, con scelta delle colonne individuali; importi una volta per prenotazione. Stampa con caricamento di tutte le righe filtrate e ripristino successivo della vista.
- Vista camere con occupanti e scambio atomico fra due persone. Il backend riusa la transazione del batch operativo, distinguendo nell’audit `ROOM_SWAP` da `SHEET_SYNC` e consentendo solo due celle camera per il nuovo comando.
- Conteggi delle opzioni individuali delle persone ammesse, distinguendo unità e persone.
- Consultazione pubblica del saldo: errori di lettura non diventano falsi insoluti, pagamento unico distinto dalla caparra, istruzioni soltanto per prenotazioni ammesse con pagamento gestito. Variante di consultazione `mi_status=balance` predisposta; non è un gateway di pagamento.
- L’etichetta Gratuito sulle tessere eventi dipende sempre e solo da `pricing_mode=ZERO`. Nelle prenotazioni a importo zero il testo è «Nessun importo dovuto», evitando di attribuire una dichiarazione di gratuità all’evento.

## Verificato

- 227 test Node superati (Workspace e struttura plugin).
- Test PHP della posizione economica e del saldo pubblico superati.
- Test ledger PHP: normalizzazione, retry, rollback e coda superati.
- Browser fase 1: bozze, errori, conteggi, mobile e accesso sincronizzazione superati.
- Browser con 65 persone: secondo partecipante ricercabile, focus sulla persona, contesto conservato, CSV di 65 righe, stampa di 65 righe e importi per ordine superati.
- Browser pagamento contestuale: scelta diretta dell’ordine, scarto bozza, blocco degli altri moduli, retry idempotente e aggiornamento saldo superati.
- InnoDB: concorrenza sull’ultimo posto, scambio di camere piene, retry, conflitto con rollback completo e nuovo comando ROOM_SWAP superati.
- Sintassi PHP/JavaScript e controllo del diff eseguiti sulle modifiche.

## Lavoro ancora aperto

1. Verifica integrata visiva e funzionale dell’intero portale WordPress: i test browser attuali usano risposte simulate. Verificare anche navigazione indietro del browser con bozze, più schede/modali e assenza di identificativi HTML duplicati.
2. Paginazione server della vista individuale: oggi il riepilogo carica tutte le persone dell’evento e mostra blocchi da 30. La ricerca trasversale Iscrizioni ha invece paginazione server.
3. Uniformare le entrate Iscrizioni/Riepilogo e completare filtri logistici combinabili, conteggi servizi d’ordine e apertura delle relative liste. I conteggi attuali non attribuiscono opzioni d’ordine alle singole persone.
4. Riuso/correzione dei modelli di report Workspace, colonne dinamiche e ordinamento. Il CSV locale non sostituisce ancora i modelli Workspace.
5. Filtri richieste/verifica e riepilogo delle scadenze d’attesa. L’inventario camere dal riepilogo è stato completato nel passaggio successivo descritto sotto.
6. Variazioni di opzioni e dovuto con audit, seguito dei rimborsi, presenze facoltative e rapporto annuale per gruppo con identità riconciliate esplicitamente.
7. Completare il percorso dedicato al saldo e i collegamenti dai promemoria, verificando le indicazioni dell’organizzazione. Nessun IBAN o collegamento di pagamento è stato inventato.
8. Verifiche finali, documentazione, versione/pacchetto di rilascio. Nessun rilascio è stato dichiarato pronto.

Le tessere eventi, il wizard iniziale, la pagina pubblica di iscrizione e i file Google degli eventi restano conservati. Il nuovo codice non introduce cancellazione o ricreazione degli Sheet.

## Passaggio successivo: inventario camere dal riepilogo

È possibile aggiungere, richiamare per modifica ed eliminare camere vuote direttamente dal riepilogo, anche se l’evento non ha ancora prenotazioni. Il modulo della scheda prenotazione resta disponibile. Entrambi usano le stesse regole di validazione e lo stesso blocco transazionale per evento.

Il nuovo comando verifica l’ambito dell’operatore, la versione dell’inventario e l’identificativo della richiesta; registra operatore, evento e hash della richiesta e accoda la replica delle prenotazioni esistenti. Non elimina file Google. Per un evento senza iscrizioni, l’inventario è conservato in MySQL e sarà incluso nella successiva replica delle iscrizioni.

Verifiche aggiuntive superate: creazione senza iscrizioni, retry idempotente, versione obsoleta rifiutata, evento fuori ambito rifiutato, eliminazione camera vuota e rifiuto camera occupata (InnoDB locale). Il browser verifica salvataggio dal riepilogo senza codice ordine e richiamo dei valori con Modifica. Restano superati anche i 227 test Node e i test browser della fase 1 e delle 65 persone.

## Ulteriori passaggi implementati

- Filtri combinati: richieste presenti/da verificare/verificate, camera, servizio individuale e offerte entro 24 ore o con termine trascorso. Il termine trascorso non modifica da solo lo stato dell’ordine.
- Conteggi distinti dei servizi individuali e dei servizi d’ordine; questi ultimi permettono di aprire le prenotazioni corrispondenti.
- Report Workspace: stato di ammissione della prenotazione conservato per le persone attive; importi esposti una volta per ordine, anche dopo ordinamento non contiguo. Nessuna cancellazione di file evento.
- Verifica richieste: registrazione di operatore, data e hash del testo nell’audit esistente. Un testo cambiato invalida la verifica. Nessuna nuova tabella o modifica al modulo pubblico.
- Presenze facoltative: Non rilevata/Presente/Assente, solo per persone ammesse, con audit e protezione dai conflitti. La presenza è disponibile nel CSV individuale; non viene inferita dallo stato dell’ordine.
- Rettifica del dovuto con permesso pagamenti, motivo obbligatorio, conferma, importi precedenti nell’audit e retry idempotente. Non cambia le opzioni acquistate e non crea movimenti. Il piano a pagamento unico segue il nuovo totale; la caparra già prevista viene soltanto limitata al nuovo totale, se inferiore. Le prenotazioni annullate rimangono annullate.
- Su indicazione dell’utente, nessun filtro generico per eccedenze. Le restituzioni riguardano disdette oppure variazioni dei servizi, ad esempio il passaggio da camera singola a doppia. Restano gestite manualmente: variazione del servizio, rettifica motivata del dovuto e registrazione del rimborso effettivo sono operazioni distinte e tracciate; nessun rimborso automatico.

Test aggiuntivi InnoDB e browser superati per verifica richieste, presenze e rettifiche. La suite Node comprende ora 229 test superati, inclusi i casi dei report economici e degli stati d’attesa.

Rimangono aperti il rapporto annuale con riconciliazione esplicita delle identità, la variazione delle opzioni acquistate, il completamento del percorso dedicato al saldo, paginazione server della lista individuale, integrazione completa dei modelli report e verifica/rilascio finale. La domanda sul codice personale già usato dal gruppo è stata posta mentre il lavoro prosegue.

## Aggiornamento successivo: servizi generali, rapporto annuale e saldo

La variazione dei servizi acquistati è ora implementata per opzioni individuali e d’ordine, usando la validazione originaria di quantità, ambito e alternative incompatibili. Non è limitata alle sistemazioni: il browser verifica anche pranzo e trasporto. Motivazione, scelte precedenti e nuove rimangono nello storico. Dovuto e rimborso restano operazioni manuali separate. Corretto anche il rifiuto delle quantità negative.

Il rapporto annuale per gruppo/anno/soglia è disponibile ed esportabile. Conta gli eventi distinti con presenza effettiva registrata. I collegamenti personali richiedono anteprima del riferimento e conferma esplicita; possono essere rimossi con audit. I test verificano omonimi, eventi duplicati, ultima rilevazione, anno, rimozioni e accesso soltanto a gruppi/eventi autorizzati. Non sono stati introdotti abbinamenti automatici o un’anagrafica CRM.

I promemoria saldo puntano alla consultazione dedicata. Alla preparazione delle comunicazioni gli importi vengono riletti dal ledger; un errore di lettura interrompe la preparazione. Nessuna email è stata inviata in queste verifiche. I vecchi collegamenti alla consultazione rimangono validi.

Ulteriori miglioramenti: nomi individuali nelle tessere delle prenotazioni, pagina conservata aprendo la scheda, protezione delle bozze su chiusura/navigazione, identificativi accessibili univoci del modulo pagamenti, CSV con campi specifici dell’evento e ordinamento. Gli eventi con iscrizioni chiuse ma inizio futuro rimangono nella categoria attiva; la chiusura delle iscrizioni continua a impedire nuove iscrizioni secondo le regole esistenti.

I report Workspace applicano ora filtri combinati di esecuzione per stato, camera, trasporto e ricerca; continuano a mostrare gli importi una volta per ordine. La suite è salita a 230 test Node superati. Test PHP, InnoDB e browser coprono le funzionalità descritte; il comportamento su un’installazione WordPress completa deve ancora essere collaudato.

Restano aperti: paginazione server della vista individuale (il riepilogo attuale carica le persone dell’evento e le mostra a blocchi), verifica integrata sul portale WordPress e su Workspace reali, rifinitura dell’accesso unificato e confezionamento/rilascio. I test non attestano un deploy. Il codice conserva il numero di versione già presente nel repository; non è stato sovrascritto un pacchetto di rilascio esistente.

## Stato aggiornato: candidato locale 3.26.0

Questo aggiornamento supera gli elenchi dei lavori aperti nei passaggi precedenti. La vista dell’evento richiede ora pagine di 30 righe complete al server. Ricerca, filtri e ordinamento sono applicati dalla stessa selezione PHP utilizzata da esportazione e stampa, che leggono blocchi al massimo di 200 righe. Una modifica dei risultati fra due blocchi interrompe l’esportazione, evitando file mescolati. La ricerca attende brevemente la digitazione e le risposte superate vengono ignorate.

Il riepilogo mantiene solo i dati individuali necessari a nomi, camere e conteggi servizi; i campi completi arrivano nelle pagine. Il calcolo server del riepilogo continua però a leggere l’intero evento: non è stato dimostrato un costo costante delle query su eventi molto grandi.

Le entrate dalla ricerca Iscrizioni e dal riepilogo convergono sulla stessa scheda operativa, con pagamenti contestuali e ritorno al contesto precedente. Sono conservati l’accesso rapido Pagamenti e gli strumenti Workspace.

Verifiche finali locali: 230 test Node superati; test PHP su economia, saldo, servizi, presenze e paginazione superati; InnoDB con concorrenza, rollback, audit e passaggio MySQL → riepilogo → pagina superato. I quattro test browser precedenti restano superati. Un quinto collega il JavaScript reale alla selezione PHP reale con 265 persone sintetiche e verifica pagine, filtri, ricerca finale, CSV/stampa completi, ripristino dopo stampa e interruzione su dati cambiati. Non è una prova del runtime WordPress completo.

Versione e pacchetti locali distinti: 3.26.0, candidato per collaudo. I vecchi pacchetti restano conservati. Consultare [contenuto, limiti e collaudo integrato](rilascio-3.26.0.md). La successiva revisione del nuovo audit ha corretto la valutazione di completezza: oltre al collaudo restano integrazioni consigliate su caparra, collegamenti evento, colonne di stampa, ricerca e accesso ai report movimenti. Il [confronto prepubblicazione](CONFRONTO_AUDIT_PREPUBBLICAZIONE_2026-09-10.md) distingue quanto già realizzato da quanto resta utile. Nessuna modifica al servizio reale o agli Sheet degli eventi è stata eseguita.
