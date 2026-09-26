# Correzioni degli audit - 26 settembre 2026

Interventi sui sorgenti locali WordPress e Apps Script, dopo verifica delle segnalazioni autonome e dei due audit forniti. Nessuna distribuzione su WordPress o Google, nessun invio email reale. Conservate le modifiche preesistenti estranee al lavoro.

## Difetti autonomi corretti

1. **Scadenza dopo proroga:** il servizio ricontrolla stato e scadenza sotto il lock della prenotazione. Un candidato del cron nel frattempo prorogato non perde i posti. Rimane il controllo della copertura economica.
2. **Lista di attesa e caparra incoerente:** l'accettazione conserva modalita economica e condizioni originarie dello snapshot, invece di mescolare la nuova configurazione evento con le quote personali storiche. Una caparra nulla non apre un'attesa di pagamento impossibile.
3. **Retry con dati diversi:** salvata nello snapshot un'impronta canonica della richiesta; tutti e tre i percorsi di replay confrontano il contenuto. Stessa richiesta: stesso esito. Dati modificati: errore esplicito, senza falsa conferma o duplicazione. Le vecchie registrazioni prive dell'impronta richiedono verifica della segreteria: il sistema non dichiara verificato un contenuto che non puo confrontare con certezza.
4. **Email interrotta al quinto tentativo:** le righe esaurite diventano FAILED/TEST_FAILED, visibili e riaccodabili manualmente. Nessun sesto invio automatico con esito precedente incerto. La pulizia recupera anche le righe gia ferme in PENDING con cinque tentativi.

## Segnalazioni esterne corrette

| Area | Correzione | Verifica |
|---|---|---|
| Apertura facoltativa in wp-admin | Pubblicazione allineata al controllo delle date del servizio; apertura vuota consentita, chiusura invalida o passata respinta | Test PHP con e senza apertura e date impossibili |
| Svuotamento apertura nel portale | Il salvataggio vuoto elimina il meta precedente | Test sul metodo reale |
| Importi con virgola | La pubblicazione usa il parser in centesimi, non floatval | POST sintetici 0,50 e 0.50; il form HTML resta numerico |
| Extra Alloggio non modificabili | Solo i tipi camera standard passano dal percorso sistemazione; gli extra sono visibili e modificabili nei servizi, con ricalcolo dei prezzi | InnoDB: aggiunta, rimozione, totale, convivenza con camera standard |
| Gruppi esclusivi | La combinazione finale di servizi e sistemazione viene validata; un extra con gruppo esplicito non aggira la mutua esclusivita | InnoDB: rifiuto combinazione incompatibile e sostituzione valida |
| Chiavi personalizzate con trattino | Stessa grammatica in definizioni e sincronizzazione | InnoDB: salvataggio custom_food-note |
| Recapiti del referente nel foglio | Il confronto considera il valore effettivamente mostrato quando il contatto personale manca; la modifica scrive il partecipante, non il referente. Supportato il telefono nei profili minimi | InnoDB: telefono/email, ricevute, referente invariato, conflitto reale e rollback integrale |
| Colonne storiche | Le risposte personalizzate e i servizi acquistati rimangono visibili anche dopo la rimozione dallo schema corrente | Test Apps Script; non vengono ripristinate indiscriminatamente colonne di vecchi profili |
| Multilinea senza ricevuta | Il recupero riconosce anche il testo esatto, senza eliminare le nuove modifiche locali | Test Apps Script con a capo e modifica successiva |
| Presenze non rilevate | Esclusi da quel contatore partecipanti annullati e prenotazioni non ammesse | Test PHP; conservate le presenze effettive rilevate prima di un annullamento |
| Campi email senza effetto | Contatto, Reply-To e nome risolto sono mostrati in sola lettura; resta operativo il controllo esistente di personalizzazione identita | Suite email; preservata la precedenza del gruppo sugli indirizzi |
| Export iscrizioni wp-admin | Propagato e applicato filtro Workspace; aggiunta colonna Stato partecipante senza eliminare righe storiche | InnoDB: piu blocchi, filtro SYNCED, annullamento individuale, snapshot consistente |
| Filtri Pagamenti wp-admin | Conservate date nel form evento/fonte/movimento; riepilogo e controlli dentro wrap | Test di rendering del report |
| Default economico | Fallback del ledger allineato a REGISTRATION_ONLY | Test PHP |

## Segnalazioni non trasformate in modifiche

- Il primo audit cita una versione superata di registration_time_state: l'apertura vuota era gia accettata dal servizio pubblico. Erano invece reali il blocco distinto di wp-admin e lo svuotamento nel portale, ora corretti.
- La categoria Alloggio non implica automaticamente un gruppo esclusivo: una notte aggiuntiva deve poter essere cumulativa. Conservata questa regola, correggendo il blocco della gestione.
- Il Reply-To della modalita PROVA resta la casella di test, come previsto e verificato dalla suite di isolamento.
- Nessuna modifica all'assegnazione automatica dei gruppi misti o incompleti: non e stato dimostrato un difetto rispetto alle regole operative. La generazione demo con ambito ONE segue quell'ambito e non dimostra perdita di dati delle iscrizioni reali.
- La prevalenza dello schema evento sui profili generici e intenzionale. Ripristinati solo i dati storici effettivamente legati alle iscrizioni, non qualunque vecchia colonna salvata.
- Non si cancellano presenze effettive storiche solo perche la prenotazione viene successivamente annullata; ne si modifica la politica di riconoscimento delle identita senza una regola concordata.
- Segreteria.html come Web App, ritorno ad AUTOMATICO nel registro centrale e ripristino URL sono percorsi legacy non dimostrati raggiungibili dall'attuale interfaccia standalone. La proprieta dei 90 secondi riguarda i vecchi aggiornatori, non prova un blocco della proiezione diretta corrente.
- La condivisione in sola lettura tramite link e il comportamento implementato e testato: l'assenza della chiamata alla vecchia funzione di condivisione nominativa non e una prova di malfunzionamento.
- Nessun difetto QR/barcode dimostrato. Superati i controlli esistenti sui limiti; non eseguito un collaudo con scanner reale.

## Verifiche

- Suite tools/verify.ps1: 362 test Node, 7 test sugli asset generati, controlli PHP, lint e sanitizzazione superati.
- Nuove regressioni PHP integrate nella suite: audit-registration-regressions.php, audit-outbox-recovery.php, audit-publication-regressions.php. Aggiunto anche payment-ledger.php alla verifica ordinaria.
- InnoDB locale, solo localhost:33317 e database di test: individual-management-innodb.php, audit-management-innodb.php, sheet-receipts-innodb.php (comprende management-innodb.php) e csv-export-innodb.php superati.
- Aggiornate le fixture CSV, rimaste indietro rispetto alle query correnti, senza modificare tabelle esterne al database di prova.
- Asset minificati rigenerati e controllati. git diff --check superato.
- Database locale avviato per il collaudo e spento al termine. Nessun processo di test lasciato attivo.

Non eseguito il collaudo end-to-end su WordPress e Google Apps Script distribuiti. Non creati nuovi ZIP di rilascio e non aggiornati gli artefatti dist: le modifiche sono nei sorgenti e negli asset locali.
