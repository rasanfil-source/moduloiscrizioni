# Spostamenti e passaggi privati

La funzione è indipendente dal gruppo, dal profilo dei dati e dalla modalità economica dell’evento. Il contributo al conducente comprende andata e ritorno e non attraversa il registro dei pagamenti.

## Dati e concorrenza

La configurazione è nel metadato `_mi_travel` e nella revisione pubblicata dell’evento. La tabella InnoDB `mi_travel` contiene una riga per partecipante, identificata da `participant_id`, con `registration_id` ed `event_id` indicizzati. Sono conservati modalità, posti offerti, contributo in centesimi, punto e orario di partenza UTC, località richiesta, dipendenza dal passaggio, stato, conducente assegnato, scadenza della proposta e data di ingresso in coda.

Le scritture usano la stessa transazione dell’iscrizione e il lock operativo dell’evento. La disponibilità considera assegnazioni e proposte non scadute, esclusivamente per partecipazioni attive ammesse all’evento. Il salvataggio controlla nuovamente disponibilità e contributo mostrato. Un conflitto annulla l’intera iscrizione, compresi contatori e outbox. Gli invii ripetuti conservano l’idempotenza del servizio di registrazione.

## Stati

- `NONE`: nessun passaggio richiesto; include viaggio autonomo, mezzi pubblici e offerta auto.
- `WAITING`: richiesta di passaggio in attesa.
- `OFFERED`: posto riservato per 86.400 secondi, con conducente, condizioni e scadenza.
- `ASSIGNED`: passaggio scelto direttamente o proposta accettata.
- `DECLINED` / `EXPIRED`: proposta rifiutata/scaduta; il rientro in coda è esplicito.
- `CLOSED`: passaggio liberato perché la partecipazione non è più attiva.

Lo stato della prenotazione `TRAVEL_PENDING` riserva la capienza dell’evento, ma non autorizza incassi, registrazione presenze o assegnazioni camere. La quota dell’evento resta disponibile come preventivo; il primo versamento e la scadenza di pagamento restano sospesi. Dopo l’assegnazione dei passaggi indispensabili, il servizio ricalcola la copertura individuale con il calcolatore economico esistente e passa a `CONFIRMED` oppure `PENDING_PAYMENT`. Lo storico dei versamenti rimane intatto.

L’attesa dell’evento continua a usare `WAITLISTED` e `WAITLIST_OFFERED`. Le richieste di passaggio di queste prenotazioni vengono registrate, ma non occupano posti auto. All’accettazione del posto all’evento viene valutata anche l’eventuale dipendenza dal passaggio.

## Accesso e proiezione

`GET /modulo-iscrizioni/v1/events/{id}/travel` espone soltanto codice del viaggio, partenza, orario, posti disponibili e contributo, senza dati di contatto e con cache disabilitata.

I collegamenti personali sono firmati con HMAC e sale WordPress. Le modifiche richiedono POST e nonce; una risposta a una proposta verifica anche conducente e scadenza mostrati, per respingere pagine obsolete. Il collegamento consente di agire solo sulla persona identificata. La segreteria vi accede dalla scheda dell’evento dopo i normali controlli d’ambito.

Le proiezioni complete e incrementali aggiungono campi `travel_*` ai dati operativi del partecipante. La lettura completa carica i viaggi dell’evento una sola volta per richiesta. I collegamenti firmati non vengono esportati nei fogli. La cancellazione definitiva dell’evento include la nuova tabella e la sua impronta di verifica.

## Verifica mirata

`wordpress-plugin/tests/travel.php` esegue le verifiche di validazione senza database. Con `MI_TRAVEL_TEST_PORT` esegue anche l’integrazione su un MariaDB **locale dedicato**, nel database sintetico `mi_travel_synthetic`, ricreato per la prova. Richiede `mysqli` e `mbstring`; non va collegato a dati di produzione.

`tools/test-travel-browser.cjs` esercita i componenti e il modulo completo. Accetta `MI_PLAYWRIGHT_MODULE` per il percorso di Playwright e `MI_TEST_MIN=1` per gli asset minificati.
