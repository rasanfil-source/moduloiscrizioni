# Audit gestionale e UX — Segreteria eventi

Data: 9 settembre 2026. Oggetto: lavoro sulle iscrizioni già ricevute, sul codice locale del plugin 3.25.2 e del relativo Workspace.

**Giudizio:** esiste una buona base transazionale e una scheda gestionale già condivisa. Il portale però presenta soprattutto prenotazioni e referenti, mentre molti compiti richiedono persone, servizi e situazioni da risolvere. Serve un'evoluzione dei percorsi e delle proiezioni operative; non un rifacimento completo.

## Metodo e limiti

Ho ricostruito i percorsi dai renderer PHP, dagli eventi JavaScript e dalle letture/scritture dei servizi MySQL; ho controllato separatamente report, viste e accessi Workspace. I documenti README e UX-CONTRACT sono stati usati come contesto, non come prova dell'effettivo comportamento.

Questo è un audit statico del comportamento implementato, non una sessione osservata con operatori. Il tentativo di consultare l'endpoint pubblico tramite lo strumento web non è riuscito; non ho verificato la versione distribuita né effettuato accessi autenticati, letture di iscrizioni reali o operazioni sul portale. Non ho eseguito il collaudo browser sintetico del repository: le valutazioni mobili derivano dal CSS e dai flussi. Tempi e numero di passaggi sono ricostruzioni, non misure di usabilità. Le priorità descrivono rischi nei casi previsti dal codice, non incidenti di produzione accertati.

Nessuna modifica al codice applicativo, nessun diff applicativo, nessuna implementazione o pubblicazione. Questo documento è l'output dell'audit.

### Riferimenti alle evidenze

I richiami E1–E12 usati nel rapporto rimandano a questi punti di ingresso; quando una conclusione richiede più componenti sono indicati insieme.

| Rif. | Codice e responsabilità verificata |
|---|---|
| E1 | [class-mi-portal.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal.php>): filtri Iscrizioni; `registrations_view`, riga 1352: ricerca, limite 30, carte e indicatori; `booking_detail`, riga 1395: scheda condivisa. Navigazione in `render`, riga 856. |
| E2 | [portal-management.js](<../wordpress-plugin/modulo-iscrizioni/assets/portal-management.js>): `summary`, `detail`, `mutate`, salvataggi, annullamento, stampa, sincronizzazione e protezione delle bozze. |
| E3 | [class-mi-management-service.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php>): definizioni; `booking`, `summary`, `save_participant`, `save_room`, `save_sheet`: dati effettivamente esposti e modificabili. |
| E4 | [class-mi-payment-ledger.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-payment-ledger.php>): normalizzazione, calcolo netto, vincoli, storico e riconciliazione dello stato. |
| E5 | [class-mi-portal-payments.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-payments.php>) e [portal-payments.js](<../wordpress-plugin/modulo-iscrizioni/assets/portal-payments.js>): ricerca, form unico, retry e ritorno all'inserimento. |
| E6 | [class-mi-registration-service.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php>): riepilogo economico iniziale; `cancel_participant`, riga 779; `promote_waitlisted_locked`, riga 997; `validate_participants`, riga 1140. Schema in [class-mi-activator.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-activator.php>). |
| E7 | [portal.js](<../wordpress-plugin/modulo-iscrizioni/assets/portal.js>): modale, precedente/successiva, cache, tastiera, cronologia. [portal-management.css](<../wordpress-plugin/modulo-iscrizioni/assets/portal-management.css>): stampa e mobile. |
| E8 | [Segreteria.gs](<../workspace-apps-script/src/Segreteria.gs>): configurazione/generazione elenchi; profili alla riga 347; valori e contatti alla riga 404. [Report.gs](<../workspace-apps-script/src/Report.gs>): modelli e generazione report. |
| E9 | [Setup.gs](<../workspace-apps-script/src/Setup.gs>) e [Segreteria.html](<../workspace-apps-script/src/Segreteria.html>): accessi Workspace, selezione colonne, modelli standard e personalizzati. [FogliOperativi.gs](<../workspace-apps-script/src/FogliOperativi.gs>): fogli evento. |
| E10 | [class-mi-admin.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-admin.php>): CSV iscrizioni; `export_payments`, riga 189: CSV movimenti. |
| E11 | [class-mi-portal.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal.php>) e [class-mi-spedizione-email.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-spedizione-email.php>): preparazione comunicazioni e dati economici dei destinatari. |
| E12 | [class-mi-field-schema.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-field-schema.php>), [public.js](<../wordpress-plugin/modulo-iscrizioni/assets/public.js>), [class-mi-portal-management.php](<../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-management.php>): profili, dati individuali e instradamento operativo. |

## A. Sintesi esecutiva

1. **Correggere prima gli indicatori economici di Iscrizioni.** Il campo della rata successiva prevista viene interpretato come residuo corrente; può mostrare «Saldato» senza incassi.
2. La stessa confusione alimenta la preparazione dei promemoria saldo. Il percorso del portale impone l'anteprima: il rischio accertato è preparare destinatari/importi errati, non un invio reale già avvenuto.
3. Le persone sono individuali nel database e nella scheda; gli elenchi principali e la ricerca restano centrati sul referente. Questo non soddisfa il requisito delle iscrizioni multiple.
4. «Iscrizioni» e «Riepilogo e gestione» condividono già la scheda: non servono due editor nuovi. Occorre unire contesto, ricerca e accesso alle viste.
5. «Pagamenti» ha un compito distinto e utile: registrare incassi consecutivi. Va conservato come accesso rapido, consentendo lo stesso form anche dentro la prenotazione.
6. Le opzioni individuali, le opzioni d'ordine e le richieste particolari sono raccolte, ma non esposte dalla scheda MySQL corrente. I campi modificabili non equivalgono alle opzioni acquistate.
7. I conteggi «attivi» comprendono anche attesa e posto proposto; non indicano da soli le persone ammesse o attese sul posto.
8. L'annullamento individuale libera correttamente il posto, ma non determina un nuovo dovuto o una pratica di rimborso. Il seguito amministrativo resta manuale.
9. Le camere esistono già con assegnazione individuale e controllo della capienza. Manca la vista evento con nomi degli occupanti, richieste e persone da sistemare.
10. I profili operativi e gli elenchi adattivi esistono in Workspace; il portale continua a mostrare pagamenti, camere e pullman anche dove non servono.
11. Colonne di stampa e modelli report esistono fuori dal portale. I filtri dichiarati nei modelli non sono applicati dal generatore esaminato.
12. «Stampa vista» stampa le righe caricate, inizialmente 30, non automaticamente tutti i risultati. Non è ancora uno strumento affidabile per preparare una lista completa.
13. Nel report economico per partecipanti gli importi dell'ordine si ripetono per ogni persona: sommare la colonna sovrastima il totale nelle iscrizioni multiple.
14. I salvataggi hanno buone protezioni transazionali, ma modificare più partecipanti prima di salvarne uno può far perdere le altre bozze nell'interfaccia.
15. Le criticità devono essere filtri operativi contestuali, con una spiegazione e un'azione; non una nuova dashboard sempre obbligatoria.
16. La lista d'attesa ha già proposte, scadenze e accettazione. Serve visibilità per l'operatore, senza sostituire il motore esistente.
17. Per contare quattro partecipazioni annue servono presenze individuali e riconciliazione prudente dell'identità; email e cellulare condivisi non identificano automaticamente una persona.
18. L'ordine consigliato è: correttezza e protezione del lavoro → persone e scheda completa → stampa/filtri → logistica e analisi annuale. Conservare MySQL autorevole e ruoli attuali.

## B. Mappa dell'interfaccia attuale

### Navigazione e tre accessi operativi

La shell standalone serve gli asset dedicati. La navigazione include Riepilogo e gestione, Gestisci eventi, Crea evento, Iscrizioni, Pagamenti, Comunicazioni, Gruppi e Operatori secondo capability. La vista Elimina è instradata separatamente: non risulta una voce ordinaria della barra principale nel renderer esaminato. Creazione, pubblicazione e amministrazione restano fuori dall'audit, salvo gli effetti operativi qui descritti. [E1, E12]

| Accesso | Cosa mostra e permette oggi | Limite operativo |
|---|---|---|
| Riepilogo e gestione | Un evento; periodo; aggiornamento, sincronizzazione, apertura foglio, stampa. Totali prenotazioni/partecipanti, residuo, dati mancanti, senza camera. Tabella referente/codice/stato/versato/residuo. Ricerca locale e «Mostra altre 30». | Nessun elenco individuale; un solo filtro di criticità alla volta; nessun filtro per servizio. Totali generali e righe filtrate hanno perimetri diversi. |
| Iscrizioni | Ricerca trasversale agli eventi del periodo; filtri evento e stato; contatto del referente; scheda in modale con precedente/successiva. | Solo 30 prenotazioni, senza paginazione nel renderer; le persone aggiuntive non sono ricercabili. |
| Scheda condivisa | Nome referente e codice, totale/versato/residuo, storico aperto, link a Pagamenti; un form per persona, campi dinamici, camera, annullamento individuale; «Gestisci camere» richiudibile. | Mancano contatti del referente nel payload, opzioni, richieste particolari, caparra e stato prenotazione visibile nella scheda. Tutte le persone hanno i campi esposti con gli stessi indicatori di obbligatorietà. |
| Pagamenti | Ricerca asincrona nome/cognome referente o codice; selezione risultato; totale, versato, residuo, storico; importo, metodo, data, incasso/rimborso/storno, riferimento, nota e conferma. | Entrando dalla scheda il codice viene cercato, ma occorre ancora selezionare il risultato. Nessun ritorno contestuale esplicito alla prenotazione. |
| Comunicazioni | Preparazione per evento di promemoria e tipi previsti. | Non nasce da un elenco operativo filtrato; nel percorso analizzato produce anteprime, non invii reali. |
| Workspace | Foglio evento, celle operative, elenchi con colonne configurabili, modelli standard e personalizzati, raggruppamenti e ordinamenti. | È un altro ambiente e una replica. Il link al foglio evento non equivale all'accesso diretto al configuratore report nel file centrale. |
| Amministrazione WordPress | CSV iscrizioni e movimenti, consultazioni amministrative. | Funzioni esistenti, ma non integrate nella shell della segreteria; il CSV iscrizioni è ricco di colonne tecniche e JSON. |

### Relazione prenotazione–persone–servizi

| Oggetto | Dati effettivi | Implicazione gestionale |
|---|---|---|
| Prenotazione | Referente con nome, cognome, email, cellulare; quantità originaria; stato; richieste particolari; opzioni d'ordine; totale e quota iniziale; fotografia della configurazione. | Contatto e unità economica dell'ordine. La quantità originaria non è il numero attuale di partecipanti attivi dopo annullamenti. |
| Partecipante | ID individuale, nome e cognome propri, tipologia, campi aggiuntivi, opzioni individuali, stato ACTIVE/CANCELLED, camera assegnata. | La persona resta distinta anche quando condivide contatto e prenotazione. ACTIVE non significa presenza effettiva all'evento. |
| Campi aggiuntivi | Configurabili, anche personalizzati; in modalità ONE gli obblighi riguardano il primo partecipante; in ALL riguardano tutti. | Il primo partecipante è una posizione del modulo, non un'identità globale del referente. Gli altri possono comunque avere dati aggiuntivi. |
| Opzioni | Ambito TICKET per persona o ORDER per prenotazione, codice, nome, quantità e prezzo; alloggi alternativi e servizi. | Un campo testuale «pullman» assegnato dal gestore non sostituisce la tratta/opzione selezionata. Un'opzione d'ordine non va moltiplicata per tutte le persone. |
| Camere | Inventario per evento, codice, nome, capienza; assegnazione tramite room_code individuale. | La camera fisica e la tipologia di alloggio richiesta sono concetti distinti, oggi non confrontati nella scheda. |
| Movimenti | Ledger per prenotazione, importi positivi archiviati con tipo; rimborsi e storni diminuiscono il netto. | Il denaro è dell'ordine; non esiste una ripartizione individuale implicita. |

Il modulo pubblico precompila il primo partecipante dal referente, ma conserva dati distinti. Correggere nome/cellulare individuale nella gestione non aggiorna automaticamente il referente cercato dagli elenchi. La scheda MySQL restituisce il referente con soli nome e cognome. [E3, E6, E12]

### Stati e conteggi oggi

- Prenotazioni: confermata, pagamento atteso, lista d'attesa, posto proposto, annullata e scaduta. «Confermata» può significare caparra raggiunta, non saldo completato.
- Persone: ACTIVE o CANCELLED. Non risultano un registro strutturato delle presenze/assenze né un'identità personale trasversale.
- Criticità esistenti: residuo positivo, campi obbligatori vuoti, mancata camera se esiste almeno una camera nell'evento.
- Il riepilogo considera attivo tutto ciò che non è annullato/scaduto, quindi include WAITLISTED e WAITLIST_OFFERED. I contatori di disponibilità del servizio iscrizioni distinguono invece i posti impegnati dall'attesa.
- «Incassato» è il netto dei movimenti delle sole prenotazioni attive. Non è il totale storico degli incassi dell'evento, perché esclude ordini chiusi anche se conservano denaro.
- Il filtro «tutte» elenca anche annullate/scadute, mentre il pulsante che lo attiva conta soltanto le attive. Le criticità contano persone ma restituiscono righe di prenotazioni. [E2, E3, E4, E6]

### Mappa dei workflow attuali

Un passaggio è una scelta, apertura o conferma significativa; la compilazione di più campi è contata come una fase. Partenza dalla relativa area, salvo dove specificato. Non sono stime in secondi.

| Compito | Percorso attuale e passaggi | Attrito |
|---|---|---|
| Quanti sono gli iscritti? | Riepilogo → scegli evento: 1–2 scelte, anche periodo se necessario. | Numeri disponibili, ma ammissioni, attesa e prenotazioni non sono abbastanza separati. |
| Cercare e contattare il referente | Iscrizioni → digita → Cerca → leggi contatto: 2–3 passaggi. | Il numero è testo nella carta; se assente compare l'email. |
| Cercare una seconda persona dell'ordine | Ricerca diretta non disponibile → ricordare il referente → aprire scheda → individuare persona. | Percorso non determinabile se non si conosce il referente; non risolto da più precisione nella ricerca. |
| Correggere dati di una persona | Risultato → scheda → modifica → salva: 3 passaggi. | Il salvataggio ricarica tutta la scheda e può eliminare modifiche negli altri form. |
| Cambiare una tratta/pranzo/alloggio acquistato | Nessun percorso nell'editor MySQL attuale. | Il campo extra modificabile può essere scambiato per l'opzione, ma non la modifica. |
| Annullare una persona | Scheda → Annulla partecipazione → conferma: 2 azioni dalla scheda. | Posto liberato; rimborsi separati. |
| Annullare tutta una prenotazione multipla | Ripetere annullamento e conferma per ogni persona attiva: 2 × N azioni, con ricariche. | Esiste il servizio per annullare l'ordine, ma manca il comando nella scheda. |
| Registrare un incasso dalla scheda | Registra pagamento → scegli risultato prefiltrato → compila → conferma verifica → registra: 5 fasi. | Cambio pagina e nessun ritorno dedicato al contesto iniziale. |
| Assegnare una camera | Scheda → trova persona → scegli camera → salva: 3 azioni più scorrimento. | Non si vedono i nomi degli altri occupanti. |
| Creare una camera | Apri una prenotazione → Gestisci camere → compila → salva: 4 fasi. | Inventario dell'evento amministrato dentro una singola prenotazione. |
| Scambiare due persone tra camere piene | Web: liberare una camera tramite disassegnazione, quindi riassegnare entrambe; almeno 3 salvataggi. | Stato intermedio incompleto. La sincronizzazione Sheets sa già validare uno scambio in blocco. |
| Stampare lista completa | Stampa vista: 1 azione, preceduta da ripetuti «Mostra altre 30» se necessari. | Nessuna selezione colonne; non garantisce tutte le righe filtrate. |
| Stampare colonne scelte | Accesso al file Workspace appropriato → menu elenco/report → evento → colonne/modello → genera → apri PDF: circa 5–7 fasi. | Funzione reale già presente, ma esterna e basata sulla replica. |
| Filtrare chi usa un servizio e deve saldare | Non eseguibile come filtro combinato nella shell. | L'operatore deve incrociare informazioni o usare il foglio. |
| Recuperare attesa/offerte | Iscrizioni → evento → stato: 2 scelte, oltre all'accesso. | Stato visibile, priorità, scadenza e posti richiesti non riuniti. |
| Sincronizzare correzioni Sheets | Modifica foglio → portale → evento → Sincronizza → controlla prima/dopo → conferma: circa 6 fasi. | Buona anteprima; nomi delle persone e dei campi spesso sostituiti da numero/chiave. |
| Trovare persone con almeno 4 presenze annuali | Nessun percorso dedicato. | Mancano presenza effettiva e riconciliazione individuale affidabile. |

## C. Problemi individuati

P0: errori, perdita di controllo o grave inefficienza; P1: miglioramento operativo importante; P2: utile non urgente; P3: rifinitura. Complessità B/M/A = bassa/media/alta relativa, non preventivo: include dati, interfaccia e verifica. I rischi dell'intervento sono riportati nell'ultima colonna.

| ID / Priorità | Area | Problema: situazione attuale | Conseguenza | Intervento consigliato | Complessità | Rischi / evidenza |
|---|---|---|---|---|---|---|
| 01 · P0 | Economia | Iscrizioni decide «Saldato» da balance_cents persistito, che nasce come rata successiva, mentre la scheda usa totale meno movimenti netti. | Pagamento unico non versato può apparire saldato; saldo completato di un evento con caparra può continuare a mostrare la rata futura. | Usare la stessa proiezione del ledger in tutte le viste, separando piano iniziale e residuo corrente. | M | Non cambiare il significato storico del campo per scorciatoia; verificare anche PRICE_ONLY. E1/E4/E6. |
| 02 · P0 | Comunicazioni | Il portale deriva versato e residuo dalla rata prevista e li passa al motore dei promemoria. | Anteprime di sollecito con destinatari esclusi/inclusi o importi errati. | Preparare destinatari e importi dalla medesima lettura economica corrente; conservare anteprima. | M | Verificare gli altri chiamanti del motore; il portale qui forza allow_operational=false. E11. |
| 03 · P0 | Modifiche | Più form persona sono editabili; salvare uno azzera dirty e ricostruisce l'intera scheda. Annullare una persona ricostruisce analogamente. | Le modifiche non inviate degli altri partecipanti possono sparire senza conferma dedicata. | Tracciare bozze per persona; salvare e aggiornare solo il form interessato, oppure impedire il cambio form finché la bozza non è risolta. | M | Conservare versioni e retry; evitare un «salva tutto» che aggiri transazioni e conflitti. E2. |
| 04 · P0 negli eventi con attesa | Liste e disponibilità | Riepilogo ed elenco Workspace includono attesa/offerta fra gli attivi. Nei report lo stato persona ACTIVE può prevalere su WAITLISTED della prenotazione. | Liste utilizzate come elenco ammessi possono contenere persone non ancora ammesse. | Distinguere ammessi, posti in proposta, attesa e annullati; mostrare stato prenotazione e persona separati e il perimetro della lista. | M | Non alterare i contatori di disponibilità esistenti; non cancellare l'attesa dai report che la richiedono. E3/E6/E8. |
| 05 · P1 | Persone | Carte «referente + N partecipanti»; ricerca esclusivamente sul referente o codice. | Una persona nota al sistema resta introvabile se non è il referente; requisito esplicito non soddisfatto. | Vista individuale, ricerca su tutti i partecipanti; nella vista ordini mostrare comunque ciascun nome su riga distinta. | M | Evitare duplicazioni di ordini/importi nei risultati. E1/E3/E5. |
| 06 · P1 | Contatti | Scheda senza telefono/email del referente; contatti individuali solo se presenti fra gli extra. Aggiornamenti persona e referente separati. | La scheda «completa» può non contenere il numero necessario; ricerca può mantenere un nome precedente. | Sezione referente esplicita e modificabile con validazione; contatto individuale o «contatto del referente» dichiarato; azioni chiama/copia. | M | Non propagare automaticamente un contatto a tutte le persone. E1/E3/E12. |
| 07 · P1 | Servizi e richieste | booking() omette options_json, order_options_json e special_requests; l'editor modifica nomi, extra e camera. | Non si possono verificare o cambiare servizi acquistati, né leggere richieste che condizionano l'organizzazione. | Prima esporre dati già raccolti; poi introdurre variazione controllata delle opzioni con effetto economico esplicito. | B lettura / A modifica | Nessun ricalcolo silenzioso e nessuna riscrittura della fotografia originaria. E3/E6. |
| 08 · P1 | Annullamenti e rimborsi | Annullamento libera posti, ma conserva totale/quota iniziale e non apre una pratica economica. Totali «Incassato» escludono le prenotazioni chiuse. | Rimborso da valutare può essere dimenticato; un rimborso su ordine parzialmente attivo può far riapparire debito verso il totale originario. | Esito con partecipanti residui e «posizione economica da verificare»; distinguere rimborso concordato, da eseguire, eseguito. | A | Non assumere rimborso integrale o quota proporzionale: dipende dalle condizioni dell'evento. E2/E3/E4/E6. |
| 09 · P1 | Economica contestuale | Caparra, scadenza e stato economico non sono riuniti nella scheda; il form sta su un'altra pagina. | Più navigazione e calcoli mentali per capire chi deve cosa. | Riutilizzare il form unico nel contesto e mostrare quota iniziale richiesta, mancante alla caparra, residuo totale e scadenze esistenti. | M | Distinguere scadenza quota iniziale da eventuale scadenza saldo non modellata. E3/E4/E5/E6. |
| 10 · P1 | Stampa | window.print() usa il DOM corrente, inizialmente 30 righe, senza configurazione delle colonne. | Elenco operativo parziale o pieno di dati inutili; stampa di una bozza può sembrare dato salvato. | Stampa dedicata di tutti i risultati filtrati, colonne scelte, conteggio, data e indicazione chiara delle bozze. | M | La stampa in modale e i salti pagina richiedono verifica browser reale. E2/E7. |
| 11 · P1 | Report | I modelli salvano filtri nominali, ma generaReportDaModello passa solo ordinamento e raggruppamenti. | «Documenti mancanti» o un filtro economico dichiarato non restringono realmente il report. | Rendere espliciti i filtri effettivi e implementare criteri con valori; riusare i modelli e le colonne già presenti. | M | Migrare configurazioni senza attribuire ai filtri storici valori mai salvati. E8/E9. |
| 12 · P0 per uso contabile | Report economici | Il modello pagamenti è per persona, ma total/paid/balance restituiscono importi interi dell'ordine su ogni riga. | Somme manuali o successive esportazioni possono moltiplicare il denaro per il numero di persone. | Report economico per ordine con nomi distinti sotto l'ordine; aggregati calcolati una volta per prenotazione. | M | Non dividere arbitrariamente gli importi fra persone. E8/E9. |
| 13 · P1 | Camere | Inventario dentro la scheda; posti occupati/liberi senza elenco degli altri occupanti o confronto con l'alloggio richiesto. | Abbinamenti fatti a memoria e passaggi fra molte schede. | Vista camere per evento con persone non assegnate, occupanti nominativi, richiesta e capienza; scambio atomico. | M/A | Scambi simultanei, richieste incompatibili e camera fisica distinta da tariffa. E2/E3/E6. |
| 14 · P1 | Filtri e conteggi servizi | Riepilogo ha un filtro singolo di criticità; nessuna intersezione su opzioni/campi, nessun conteggio generalizzato dei servizi nella shell. | Conteggi manuali per pullman, pasti e assicurazione; difficile passare dal totale alle persone. | Filtri combinabili e riepiloghi dei soli campi utili, cliccabili per vedere le persone sottostanti. | M/A | Distinguere persone, quantità e ordini; dato non raccolto non equivale a «no». E2/E3/E8. |
| 15 · P1 | Adattività | Storico, valori monetari, camere e campo pullman vengono esposti anche senza necessità. | L'evento semplice sembra un viaggio complesso e genera controlli inutili. | Usare caratteristiche evento e profili già esistenti, consentendo una piccola configurazione delle colonne. | M | Non nascondere vecchi dati/movimenti solo perché la configurazione attuale è cambiata. E2/E3/E8/E12. |
| 16 · P1 | Dati mancanti | Controllo di obbligatorietà su extra vuoti; in ONE il riepilogo usa il primo fra i partecipanti ancora ACTIVE. L'editor marca richiesti gli stessi campi per tutti. | Se il primo viene annullato, il successivo può risultare impropriamente incompleto; requisiti non coerenti fra conteggio e form. | Applicabilità esplicita per persona e configurazione originaria; motivazione del campo mancante e filtro sul nominativo. | M | Decidere separatamente l'eventuale nuovo referente, senza trasferire dati personali per posizione. E2/E3/E6. |
| 17 · P1 | Continuità elenco | Iscrizioni restituisce al massimo 30; dopo modifica viene svuotata la cache del dettaglio, non aggiornata la carta già presente. Il ritorno al riepilogo ricrea filtri/ricerca. | Prenotazioni meno recenti difficili da sfogliare, carte obsolete, perdita del punto di lavoro. | Paginazione esplicita; aggiornare carta e conteggi; conservare filtri e posizione al ritorno. | M | Gestire righe che dopo la modifica escono dal filtro. E1/E2/E7. |
| 18 · P1 | Periodo operativo | «Passati» può dipendere dalla chiusura iscrizioni, anche prima della data evento; eventi annullati esclusi dal selettore Iscrizioni. | L'evento sparisce da «in corso» proprio quando iniziano saldo, liste e rimborsi. | Distinguere iscrizioni chiuse da evento concluso; mantenere accessibili eventi con pratiche aperte. | M | Conservare archiviazione e ruoli; chiarire il criterio con esempi di calendario. E1/E12. |
| 19 · P2 | Attesa | Stato filtrabile e motore automatico disponibili, ma senza pannello di scadenze, quantità richieste e motivi del mancato avanzamento. | L'operatore non sa rapidamente chi contattare o perché un ordine successivo ha ricevuto una proposta. | Vista contestuale della coda con persone, posti richiesti, offerte e scadenze. | M | L'algoritmo scorre per anzianità ma salta gruppi che non entrano: non promettere FIFO assoluta. E1/E6. |
| 20 · P2 | Tracciabilità | Le note amministrative sono salvate e restituite dal ledger, ma non mostrate nei due storici web. Lo storno non seleziona il movimento originario. | Perde visibilità il motivo di una rettifica; ricostruzione manuale. | Espandere riga movimento con nota; per rettifiche guidare la scelta del movimento e del motivo, senza duplicare il ledger. | B nota / M storno | Conservare distinzione tra rettifica ed effettivo denaro restituito. E2/E4/E5. |
| 21 · P2 | Analisi annuale | Nessuna presenza strutturata né riconciliazione individuale trasversale. | «Quattro iscrizioni» viene facilmente scambiato per «quattro partecipazioni». | Presenze facoltative e rapporto per gruppo/anno con corrispondenze da verificare. | A | Contatti familiari, omonimi, dati storici insufficienti. E6/E8. |
| 22 · P2 | Sincronizzazione | Buona anteprima prima/dopo, ma persona espressa tramite numero e campo con chiave; nessuno stato replica per prenotazione nel riepilogo. | Controlli più lenti; dato appena salvato può non essere ancora nel foglio da stampare. | Nomi ed etichette leggibili, numero come supporto; stato replica distinto dall'esito del salvataggio MySQL. | B/M | Non dichiarare sincronizzato ciò che è soltanto accodato. E2/E3/E12. |
| 23 · P3 | Comandi e feedback | «Aggiorna riepilogo» anche nel dettaglio; filtro attivo non marcato esplicitamente; titoli Gestione/Riepilogo non uniformi. | Piccole esitazioni e scarsa riconoscibilità del contesto. | Etichette contestuali, filtro selezionato visibile, riepilogo dei criteri applicati. | B | Verificare tastiera, lettori di schermo e viewport stretta. E2/E7/E12. |

### Tre casi che rendono concreti i P0

**Economia:** evento a pagamento unico di 100 euro, nessun movimento. Il riepilogo iniziale salva quota iniziale 100 e rata successiva 0. Iscrizioni interpreta 0 come «Saldato»; il ledger restituisce versato 0 e residuo 100. Con totale 100 e caparra 30, la rata successiva rimane 70 anche dopo l'incasso completo. Questo è un conflitto di significato fra viste, non un problema estetico.

**Bozze:** l'operatore corregge i dati della prima e della seconda persona; preme «Salva partecipante» sulla seconda. Viene inviato solo il secondo form, poi `detail()` ricrea tutti i form con i dati server. La prima correzione non era stata inviata e viene perduta. La protezione generale contro l'uscita non copre questo caso.

**Denaro nelle liste individuali:** prenotazione con due persone, totale 200 euro e versato 100. Il report Workspace può mostrare 200/100 su ciascuna delle due righe. Il codice non esegue una somma sbagliata in quel punto, ma la lista induce una somma di 400/200. Gli importi sono dell'ordine e vanno presentati e aggregati come tali.

## D. Ridondanze

| Elemento | Valutazione | Decisione consigliata |
|---|---|---|
| Elenco di Riepilogo e lista Iscrizioni | Entrambi conducono alla stessa scheda, con ricerca e indicatori diversi. Il primo è per evento, il secondo trasversale. | Accorpare l'area e conservare i due ambiti come scelte di contesto, con identiche regole di ricerca e stato. |
| Scheda prenotazione | Già condivisa mediante MI_Portal_Management. | Conservare e completare; nessun editor parallelo. |
| Storico economico in scheda e Pagamenti | Ripetizione utile perché entrambi i task richiedono il contesto economico. | Riutilizzare una presentazione coerente, senza eliminare l'informazione da uno dei due task. |
| Pagamenti separati | Giustificati per chi riceve una serie di bonifici/contanti e passa fra prenotazioni. | Conservare l'accesso «Registra movimento» e usare lo stesso form anche in scheda. |
| Periodo/evento nel contenitore e nella scheda modale | Doppio livello di navigazione; dentro una scheda si può tornare al riepilogo o cambiare evento. | Nella scheda mostrare contesto leggibile, lasciando il cambio evento all'area di lavoro. |
| Gestisci camere in ogni prenotazione | È lo stesso inventario evento riproposto. | Spostare l'inventario nella vista camere; mantenere l'assegnazione sulla persona. |
| «Stampa vista» e report | Hanno obiettivi diversi: cattura della vista contro elenco organizzativo. | Un accesso Stampa/esporta con perimetro chiaro; conservare la stampa della scheda come variante esplicita. |
| Schermate storiche Workspace di gestione | Rimangono frammenti di editor; alcuni comandi ora rinviano al web. La presenza del codice non prova che siano tutti raggiungibili nell'uso corrente. | Non ripristinarli come secondo gestionale. Verificare chiamanti e accessi prima di rimuovere ciò che è obsoleto; conservare report e proiezioni utili. |

Sono eliminabili dall'esperienza ordinaria: la necessità di scegliere fra due elenchi quasi equivalenti, il secondo clic su una prenotazione già identificata per il pagamento, i pannelli di logistica negli eventi semplici e le promesse di filtri report non operativi. Non sono da eliminare i servizi che garantiscono correttezza né le funzioni Workspace soltanto perché meno visibili nel portale.

## E. Funzioni mancanti realmente giustificate

| Esigenza | Già disponibile | Parte da completare |
|---|---|---|
| Trovare una persona | Identità e dati individuali salvati. | Ricerca e lista individuale nel portale, anche per contatti personali. |
| Correggere l'iscrizione completa | Editor campi persona e camera. | Referente, richieste e opzioni; variazioni economiche controllate. |
| Capire cosa fare oggi | Tre indicatori di criticità e stato prenotazione. | Regole contestuali, motivazioni nominative, combinazione dei filtri, presa in carico delle richieste quando necessaria. |
| Preparare elenchi utili | Colonne, modelli e PDF Workspace; CSV amministrativi. | Accesso integrato, filtri realmente applicati, risultati completi e separazione persone/denaro. |
| Organizzare servizi | Opzioni strutturate e campi operativi. | Conteggi per servizio e lista delle persone che li compongono. |
| Sistemare persone | Camere e capienza, assegnazione individuale, scambi atomici via sincronizzazione. | Quadro globale nominativo e scambi atomici direttamente nel web. |
| Chiudere annullamenti | Servizi di annullamento e registrazione rimborsi. | Annullamento dell'intero ordine nella scheda e pratica amministrativa residua. |
| Gestire attesa | Motore di proposte/accettazioni/scadenze. | Supervisione operativa, non un secondo motore. |
| Contare partecipazioni annuali | Eventi associati a gruppi, nomi e talvolta contatti. | Presenze individuali e abbinamenti verificabili fra registrazioni. |

Non propongo una dashboard separata delle anomalie più un pannello «da fare» più nuove viste per ciascun badge: sarebbe una nuova duplicazione. Un elenco filtrabile delle cose da risolvere, dentro l'evento, è sufficiente.

## F. Architettura proposta

### Un'area di lavoro, con due unità di lettura

La voce **Iscrizioni** diventa l'accesso al lavoro: ricerca trasversale negli eventi autorizzati oppure scelta dell'evento. Dopo la scelta, testata compatta con titolo, periodo operativo e contatori espliciti. Le funzioni di configurazione generale dell'evento mantengono la collocazione attuale.

Le viste di base sono **Partecipanti** e **Prenotazioni**. La prima è quella ordinaria per liste, telefonate, dati e logistica. La seconda serve per referente, rapporto economico e annullamento dell'ordine. Ogni nome resta distinto anche nella vista prenotazioni: intestazione dell'ordine, righe separate delle persone, poi importi una sola volta.

Esempio interamente fittizio di una prenotazione multipla:

| Prenotazione | Persona | Contatto utilizzabile | Servizio |
|---|---|---|---|
| ORD-DEMO | Ada Esempio | Personale | Tratta A |
| ORD-DEMO | Bruno Prova | Del referente Ada Esempio | Tratta B |

Nella vista Prenotazioni lo stesso ordine contiene entrambe le righe e un unico riepilogo economico. Non attribuire il debito dell'intero ordine a ciascuno come se fosse personale.

La scheda condivisa si apre sulla persona cercata, mantenendo il contesto dell'intera prenotazione. Su desktop può restare un pannello/modale ampio; su mobile una vista a tutta larghezza con ritorno alla lista. Non introdurre contemporaneamente un nuovo drawer e una seconda scheda completa: prima correggere e rendere progressivo il componente esistente.

### Adattività senza un altro configuratore complesso

| Caratteristiche evento | Visibile subito | Disponibile su richiesta |
|---|---|---|
| Nominativo e cellulare, nessuna economia | Persone, contatto, stato, ricerca, stampa. | Altri dati raccolti e annullati. |
| Pullman/pranzo o pochi servizi | Base più colonne e conteggi dei servizi previsti. | Filtro combinato e stampa del singolo servizio. |
| Prezzo senza gestione incassi | Prezzo dove serve, senza indicatore «da incassare» automatico. | Dettaglio della quota. |
| Gestione incassi | Prenotazioni, versato netto/residuo, azione movimento. | Storico e dettagli per metodo. |
| Caparra e saldo | Quota iniziale, mancante alla quota iniziale, residuo totale e scadenze pertinenti. | Storico completo. |
| Pernottamento | Vista camere, richiesta di alloggio e assegnazione. | Inventario, anomalie e scambi. |
| Documenti/richieste particolari | Campi pertinenti e criticità se presenti. | Dettaglio documentale, escluso dalle stampe generiche. |
| Presenze da rilevare | Comando presente/assente e stato «non rilevato». | Riepilogo annuale solo per i gruppi interessati. |

Riutilizzare i profili operativi già definiti come valori iniziali. La configurazione dell'evento e quella fotografata nelle prenotazioni determinano le possibilità; i valori presenti aiutano a scegliere le colonne ma non bastano a stabilire se un dato è richiesto. Consentire «Colonne» e salvataggio di poche viste utili, evitando un costruttore universale.

### Scheda: ordine delle informazioni

1. Evento, codice, stato prenotazione e referente con contatti.
2. Eventuali criticità reali, con persona interessata, motivo e azione.
3. Persone distinte, ciascuna con stato, campi pertinenti, servizi richiesti e assegnazioni.
4. Economia dell'ordine, se prevista: totale dovuto corrente, quota iniziale, versato netto, residuo, eventuale rimborso concordato; stesso form di Pagamenti.
5. Storico e note espandibili; dati meno frequenti progressivi.

Per una modifica a opzioni/prezzo: mostrare prima e dopo, persona interessata, differenza sul dovuto e motivo. Salvare come variazione tracciata. Il ledger registra movimenti di denaro; una variazione di prezzo non deve diventare un incasso/rimborso fittizio.

### Regole delle criticità

Separare quattro dimensioni: stato della prenotazione, situazione economica, completamento operativo e presenza. Una persona può essere ammessa, appartenere a un ordine con caparra ricevuta, non avere ancora camera e non avere ancora presenza rilevata. Un unico menu «Stato» non può rappresentare correttamente questa combinazione.

| Criticità | Criterio proposto | Unità |
|---|---|---|
| Quota iniziale incompleta | Gestione economica applicabile e netto versato inferiore alla quota iniziale richiesta. | Prenotazione |
| Saldo residuo | Dovuto corrente meno netto versato positivo; «scaduto» solo con scadenza applicabile. | Prenotazione |
| Rimborso da eseguire | Importo riconosciuto al rimborso meno rimborsi pertinenti già eseguiti. Annullamento con versato apre prima una verifica, non un debito automatico. | Prenotazione |
| Dati obbligatori mancanti | Campo richiesto e applicabile a quella persona, valore assente. | Persona |
| Dati incoerenti | Regola esplicita del campo/evento, ad esempio scadenza documento incompatibile con il viaggio. | Persona |
| Richiesta da verificare | Richiesta presente e non ancora valutata; stato gestionale leggero con nota/esito. | Prenotazione o persona secondo provenienza |
| Senza camera | Persona che richiede pernottamento, ammessa, senza assegnazione valida. | Persona |
| Pullman da definire | Servizio richiesto ma assegnazione necessaria assente; nessun avviso per chi non usa il servizio. | Persona |
| Offerta in scadenza | Proposta attiva con scadenza nota dal motore. | Prenotazione, mostrando ogni persona |

Una stessa persona può avere più criticità: il totale «persone da verificare» conta persone distinte, non la somma dei badge. Il filtro spiega perché ciascuna riga compare; l'avviso si risolve automaticamente quando il dato torna coerente, salvo richieste che richiedono un esito umano.

### Camere e pullman

Per le camere, due zone nella stessa vista evento: persone da assegnare e camere con occupanti. Ogni persona ha nome, richiesta di singola/doppia/tripla, eventuale preferenza dichiarata e ordine di provenienza. Ogni camera mostra codice/nome, posti totali, occupati, liberi e nomi individuali. La camera piena resta leggibile.

Azioni semplici «Assegna», «Sposta», «Scambia», utilizzabili anche senza trascinamento. Trascinamento eventuale solo come scorciatoia desktop. Bloccare superamenti di capienza; segnalare incompatibilità con richieste esplicite e registrare eccezioni deliberate. Non dedurre incompatibilità da caratteristiche personali non richieste.

Non proporre un algoritmo automatico di abbinamento in questa fase: prima rendere visibili richieste e occupanti. L'inventario fisico non va generato automaticamente dalle tariffe doppia/tripla.

Per i pullman iniziare con raggruppamento per tratta richiesta e mezzo assegnato, contatori e liste. Una pianta dei sedili non è giustificata finché non serve numerazione concreta dei posti; un foglio operativo per tratta può bastare.

### Conteggi, filtri, stampa ed esportazione

Filtri combinabili: evento, stato ammissione, situazione economica e campi/servizi effettivamente previsti. Le condizioni fra campi si intersecano; più valori dello stesso campo possono essere alternativi. Mostrare i criteri attivi e un comando per azzerarli.

Il sistema deve distinguere **numero di persone**, **quantità di servizi** e **numero di prenotazioni**. Due colazioni per persona sono due unità, ma una persona. Un contatto del referente non è una seconda persona. Un'opzione d'ordine non viene replicata su tutte le righe nei conteggi. In modalità ONE, ciò che non è stato raccolto per gli altri partecipanti resta «non raccolto», senza inventare valori individuali.

Conteggi compatti, apribili in dettaglio: «Tratta A: 24 persone» porta alla lista di quelle 24 persone. Nessuna nuova dashboard per ogni servizio. Le colonne numeriche personalizzate si sommano solo quando il campo rappresenta una quantità, non un numero di documento o un codice.

Stampa/esportazione condividono selezione e filtri; la stampa usa tutti i risultati, indipendentemente dalle righe caricate. Anteprima con titolo evento, filtro, persone incluse, data di estrazione e colonne. Impostazioni iniziali: elenco contatti, pullman, ristorante, camere; modelli già disponibili da riusare dove appropriati. I documenti personali non entrano nell'elenco ristorante per il solo fatto di essere raccolti.

Il report economico lavora per prenotazione o per movimento, esplicitamente. Conservare il CSV amministrativo come estrazione dettagliata; aggiungere l'accesso operativo a un CSV leggibile senza JSON obbligatori. Le estrazioni autorevoli si leggono da MySQL; il foglio replica resta utile, con aggiornamento dichiarato.

### Analisi annuale circoscritta

Percorso: scegli gruppo → anno/intervallo → «presenze effettive» → soglia almeno 4 eventi distinti → verifica corrispondenze dubbie → elenco risultante con eventi di supporto.

Serve prima una presenza individuale: «non rilevata», «presente», «assente», con operatore e data della modifica. Non trasformare automaticamente un iscritto confermato in presente. Per gli anni senza rilevazione offrire un rapporto denominato «iscrizioni», evidenziando che non dimostra la partecipazione.

Per riconoscere una persona: nomi normalizzati più email/cellulare personali quando disponibili; contatti condivisi del referente sono solo indizi di prenotazione. Conservare i valori originali. Proporre corrispondenze forti e separare casi dubbi; omonimia o telefono familiare da soli non devono fondere persone. L'operatore può confermare o separare una corrispondenza con traccia reversibile, limitata al rapporto/gruppo. Contare una persona al massimo una volta per evento, anche con più registrazioni.

Non occorrono anagrafica CRM generale, marketing o profili permanenti completi. Se dati individuali e presenza non sono sufficienti, il rapporto deve mostrare «non determinabile» invece di un conteggio apparentemente certo.

### Leggerezza e uso mobile

Caricare il dettaglio al bisogno, paginare gli elenchi con totale reale e calcolare i conteggi lato server sullo stesso perimetro dei filtri. La paginazione corrente del riepilogo limita il DOM ma scarica già tutte le prenotazioni dell'evento: non è paginazione dei dati.

Su mobile privilegiare ricerca, telefono, lettura della persona, correzioni puntuali, incasso e presenza. Per camere complesse favorire desktop ma consentire assegnazione mediante selettori. Il CSS attuale ha campi a colonna singola e comandi di almeno 44 px; le tabelle hanno però larghezza minima 650 px sotto 600 px e richiedono scorrimento laterale. Conservare le buone basi di focus, etichette e dialoghi, verificando i percorsi reali con tastiera e telefono.

## G. Confronto attuale → proposto

| Area | ATTUALE → PROPOSTO → MOTIVAZIONE |
|---|---|
| Accesso al lavoro | Due elenchi con criteri diversi → un'area Iscrizioni, contesto globale/evento → un solo posto da imparare e regole coerenti. |
| Identità | Referente più quantità → ogni persona distinta, anche dentro l'ordine → trovare chi si presenta o telefona. |
| Scheda | Form completi in sequenza e storico sempre aperto → apertura sulla persona cercata, sezioni pertinenti → meno scorrimento e dati essenziali subito. |
| Ricerca | Tre ricerche con campi diversi → ricerca condivisa di persone, referenti e codici → risultato prevedibile. |
| Economia | Rata prevista in lista, residuo reale in scheda → unica proiezione del ledger → evitare falsi saldi e solleciti impropri. |
| Incasso | Cambio pagina e nuova selezione → stesso form in scheda, più accesso rapido separato → rapidità nei due task reali. |
| Servizi | Campi modificabili ma opzioni non esposte → richiesta e assegnazione separate, variazioni tracciate → non confondere un appunto con una scelta acquistata. |
| Camere | Inventario nella prenotazione → quadro evento con persone e posti → abbinamenti senza memoria esterna. |
| Criticità | Tre totali isolati → filtri contestuali con motivo e azione → il gestore vede ciò che deve risolvere. |
| Liste | Stampa DOM o passaggio a Workspace → stampa completa filtrata con modelli riutilizzati → elenco adatto al destinatario. |
| Conteggi | Attivi generici e servizi da ricostruire → ammissioni/attesa distinte e totali servizio → quantità utilizzabili nell'organizzazione. |
| Periodo | Chiusura iscrizioni assimilata al passato → evento ancora operativo finché necessario → saldo e preparativi restano trovabili. |
| Attesa | Solo stato e automatismo → coda leggibile con proposte e scadenze → supervisione senza rifare la logica. |
| Annuale | Registrazioni isolate → presenze e corrispondenze verificabili nel gruppo → conteggio motivabile senza CRM. |
| Sheets | Correzioni e report in altro ambiente → supporto con stato replica e accessi chiari → conservare utilità senza creare un secondo master. |

## H. Interventi consigliati

### Piccoli interventi ad alto rendimento

- Mostrare contatti del referente, stato prenotazione, richieste particolari e opzioni già raccolte in sola lettura: completa subito la scheda senza cambiare i prezzi.
- Rendere visibili le note dei movimenti già restituite dal ledger.
- Esplicitare «versato netto sulle prenotazioni attive», perimetro dei contatori e filtro selezionato.
- Rinominare i comandi in funzione del contesto; mostrare «30 visualizzate» dove non esiste un totale reale.
- Nell'immediato dichiarare l'ambito di «Stampa vista», prima del successivo sviluppo della stampa completa.
- Rendere leggibile il prima/dopo della sincronizzazione con nominativi e nomi dei campi.

Le correzioni P0 economiche e di perdita bozze vanno affrontate prima o insieme a questi interventi, anche se richiedono più lavoro di una modifica di etichette.

### Interventi medi

- Proiezione economica coerente per elenco, scheda, conteggi e preparazione comunicazioni, mantenendo la semantica del piano originario.
- Protezione delle bozze per partecipante e aggiornamento delle righe dopo il salvataggio.
- Ricerca individuale, lista per persone, paginazione e conservazione dei filtri.
- Esposizione della stessa registrazione movimento nella scheda, con ritorno al contesto.
- Adattività basata su profili e caratteristiche già esistenti.
- Stampa/esportazione con colonne e filtri effettivi; report economico con unità corretta.
- Coda d'attesa leggibile e distinzione fra chiusura iscrizioni e fine del lavoro operativo.

### Cambiamenti strutturali circoscritti

- Variazioni delle opzioni con ricalcolo tracciato del dovuto e gestione amministrativa di annullamenti/rimborsi.
- Quadro camere e spostamenti atomici nel portale, riusando vincoli e logica transazionale disponibili.
- Metadati minimi per conteggi e regole sui campi dell'evento: natura del dato, ambito persona/ordine e unità di misura quando necessaria.
- Presenze e rapporto annuale del gruppo con riconciliazione delle identità.

### Sequenza e criteri di accettazione

| Ordine | Risultato verificabile prima di procedere |
|---|---|
| 1. Correttezza | 100 euro non pagati non risultano saldati; dopo il saldo il residuo è zero in ogni vista e anteprima. Due persone editate non perdono bozze. Attesa e ammessi non si confondono nelle liste. |
| 2. Gestione quotidiana | Cercando il secondo partecipante lo si trova direttamente; il numero è accompagnato dalla provenienza; opzioni e richieste sono leggibili; ritorno alla lista conserva i criteri. |
| 3. Output operativo | Un elenco da 65 persone stampa 65 persone; i filtri si applicano; un ordine multiplo non moltiplica i totali monetari. |
| 4. Logistica | Camera inizialmente vuota, camera piena, persona senza richiesta di alloggio e scambio fra camere piene producono risultati coerenti anche con due operatori. |
| 5. Annuale | Due persone che condividono il telefono non vengono fuse; una persona iscritta due volte allo stesso evento conta una sola presenza; gli eventi senza rilevazione restano dichiarati. |

Questi sono casi di collaudo proposti per i futuri interventi, non test eseguiti durante l'audit. Prima di stimare giornate di lavoro sono necessarie le regole del dovuto dopo annullamento, la semantica di quantità dei servizi e quali gruppi richiedono presenze: non servono per riconoscere i problemi attuali, ma governano l'implementazione.

## I. Cose da NON cambiare

1. MySQL come registro autorevole di iscrizioni, pagamenti, camere e dati operativi.
2. Il sistema dei ruoli e degli scope già definito; le nuove viste devono usare le stesse autorizzazioni.
3. La distinzione strutturale fra prenotazione e partecipanti individuali.
4. La scheda gestionale già condivisa: completarla, non affiancarle un secondo editor.
5. Il ledger e le protezioni contro doppi invii, importi superiori al residuo e rimborsi superiori al versato.
6. Versioni, transazioni e controlli di capienza, compresa la sincronizzazione atomica delle modifiche Sheets.
7. La separazione fra annullamento del posto e movimento di rimborso: collegare i task senza confondere i fatti.
8. Il motore della lista d'attesa, le proposte a scadenza e l'accettazione; renderne comprensibile il funzionamento.
9. Le configurazioni e fotografie dei moduli: proteggono il significato dei dati raccolti nel tempo.
10. I report, le colonne configurabili e i profili operativi già utili in Workspace; integrarli correggendo i limiti verificati.
11. L'accesso rapido a Pagamenti per registrare movimenti consecutivi e l'assenza di un gateway nel portale.
12. Le basi di accessibilità e sobrietà: comandi espliciti, etichette italiane, focus, dialoghi di conferma e form semplici.

La proposta conserva quindi il nucleo applicativo e concentra il lavoro su tre risultati: persone riconoscibili, informazioni coerenti e operazioni completabili senza ricostruzioni manuali.

