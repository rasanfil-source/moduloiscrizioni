# Collaudo isolato Google del 6 settembre 2026

Esito: SUPERATO, 15 verifiche OK. Eseguita soltanto `collaudaFogliIncrementaliIsolati` nel progetto MODULI, dalle 08:07:50 alle 08:09:05 (Europe/Berlin), su un nuovo file con dati fittizi.

Il primo tentativo aveva fallito prima della prima verifica: Google rifiutava i developer metadata su un intervallo delimitato numericamente. Il sorgente ora usa riferimenti a colonne intere (`A:A`, ecc.) sia per `MI_CAMPO` sia per `MI_BASE_VUOTA`. Aggiornato e salvato Codice.gs dal bundle rigenerato; CollaudoIsolato.gs non ha richiesto modifiche.

Verifiche superate:

- Refresh ripetuto senza duplicati e conservazione degli zeri iniziali.
- Identità della colonna dopo spostamento e rinomina.
- Conservazione delle modifiche manuali e riconoscimento dei conflitti.
- Nuove colonne adiacenti e gruppo comprimibile.
- Conservazione di colonne storiche e formule manuali.
- Righe incomplete non acquisite e ripristino della riga fittizia eliminata.
- Migrazione con conservazione delle divergenze.
- Struttura Pagamenti, convalida esplicita e modalità con elenco.

Anche i 50 test locali Apps Script passano. Il doppio di test rifiuta ora i metadati su intervalli delimitati, per intercettare la regressione.

Nessun deployment, timer, invio email o accesso a DB_MODULI. I flussi centrali, l'acquisizione effettiva dei pagamenti e l'integrazione WordPress non sono coperti da questo collaudo e restano da verificare. Il superamento non autorizza la messa in produzione.

## Verifiche successive: flussi centrali e WordPress

- Suite locale completa: 196/196 test Apps Script e WordPress superati. Comprende i test di incassi/rimborsi, limiti economici, retry senza duplicati e riconciliazione di APPEND_REGISTRATION dopo una consegna parziale. Questi test usano simulazioni locali; non certificano un flusso reale completo.
- Sul sito WordPress: comando «Verifica collegamento firmato» riuscito, risposta in modalità ANTEPRIMA.
- Sul sito WordPress: comando «Verifica schema Workspace» riuscito, versione dichiarata 1.8.0. STATO_SCHEMA restituisce le intestazioni definite dal codice: non legge né certifica le intestazioni effettive di DB_MODULI.
- L'URL configurato in WordPress coincide con il deployment attivo «Workspace 1.8.0 — cartella EVENTI nella radice Drive — ANTEPRIMA», versione 43 del 5 settembre 2026, ore 17:28. Esecuzione come proprietario e accesso Chiunque, protetto a livello applicativo da firma e anti-replay. Configurazione soltanto consultata.
- Il deployment versione 43 precede la correzione e il collaudo del 6 settembre: le verifiche firmate riuscite non provano ancora il nuovo aggiornamento incrementale via WordPress.

Per collaudare il percorso completo con il nuovo codice occorre prima un'autorizzazione esplicita all'aggiornamento del deployment esistente, mantenendo URL e impostazioni di accesso. Non è stato eseguito alcun deployment né attivato alcun timer. Non sono state inviate iscrizioni o email né modificati dati centrali durante queste verifiche.

## Aggiornamento autorizzato del deployment

Dopo autorizzazione esplicita dell'utente, il 6 settembre 2026 alle 08:51 il deployment esistente è stato aggiornato dalla versione 43 alla 44. Prima della pubblicazione il testo di Codice.gs è stato confrontato integralmente con il bundle collaudato: corrispondenza esatta, normalizzando soltanto i fine riga.

URL e ID del deployment invariati; mantenuti esecuzione come proprietario e accesso Chiunque. Descrizione: «Workspace 1.8.0 — fogli incrementali, metadati corretti — collaudo 15/15 — ANTEPRIMA».

Dopo l'aggiornamento, PING firmato e STATO_SCHEMA eseguiti da WordPress sono entrambi riusciti. Nessun timer attivato, nessuna email inviata. Queste verifiche confermano il collegamento, non ancora il percorso completo di iscrizioni e pagamenti con dati centrali.

## Preparazione del collaudo centrale dopo il deployment

Verificata nella pagina Spedizione email l'effettiva selezione «Anteprima — nessun invio», senza modificare impostazioni. Il registro WordPress mostra una replica sincronizzata e sette in attesa. La prenotazione dimostrativa MI-260827-FNB4T2WP, creata da admin_demo, risulta PENDING con zero tentativi, nessun errore e nessuna data di sincronizzazione.

Non sono state create nuove iscrizioni né riaccodate quelle esistenti: entrambi i percorsi chiamano accoda_sincronizzazione_workspace, che programma un'esecuzione singola WordPress mi_sync_workspace_registration tramite wp_schedule_single_event. Il divieto di timer richiede di chiarire l'autorizzazione a questa esecuzione singola prima del test. È distinta dal timer periodico Apps Script, che resta disattivato. Nessun pagamento dimostrativo registrato in questa fase.

## Tentativo di replica e comando immediato

Dopo la richiesta dell'utente di proseguire autonomamente, riaccodata soltanto la prenotazione dimostrativa MI-260827-FNB4T2WP. WordPress conferma queued, ma dopo la rilettura lo stato resta PENDING con zero tentativi: non è dimostrata l'esecuzione del lavoro differito. Non sono stati registrati pagamenti.

Preparata la versione WordPress 3.23.10 con il comando «Sincronizza ora questa prenotazione». Il comando riusa la replica firmata di una singola iscrizione e i controlli esistenti di capacità, nonce e accesso all'evento; non pianifica un nuovo timer. In caso di mancata consegna mostra un avviso e rimanda alla diagnostica, senza dichiarare successo. Il comando di riaccodamento esistente resta disponibile. Suite locale 196/196 superata.

Il pacchetto è stato caricato in WordPress, che mostra correttamente versione attuale 3.23.9 e caricata 3.23.10. La sostituzione è stata respinta dalla revisione automatica perché modifica il plugin attivo e richiede autorizzazione esplicita all'installazione e backup confermato. La 3.23.10 NON è installata. È disponibile localmente il precedente pacchetto 3.23.9 per ripristinare il codice; questo non equivale a un backup completo del sito e del database.

## Installazione e collaudo centrale autorizzati

Dopo successiva autorizzazione esplicita alla sostituzione e conferma della disponibilità del backup sito/database, installato e mantenuto attivo il plugin 3.23.10.

Il collaudo centrale è limitato alla prenotazione dimostrativa `MI-260827-FNB4T2WP` (evento 7342). Verificata la presenza della replica nel registro centrale e nel foglio dell'evento. Il tentativo immediato WordPress resta però PENDING: osservato `mi_workspace_unreachable`, mentre una precedente esecuzione Google doPost è durata 218,508 secondi a fronte del timeout ordinario WordPress di 15 secondi. La conclusione dell'esecuzione Google, da sola, non certifica il successo della risposta applicativa. Anche il successivo tentativo immediato non completa la replica dal punto di vista WordPress.

Registrati tramite «Convalida pagamenti selezionati», sulle sole righe 2–3 di Inserimento pagamenti:

- Incasso fittizio 10 EUR, idempotency key `collaudo-20260906-incasso`, ID centrale `pay_bad387deda9e45e78de26e74`.
- Rimborso fittizio compensativo 10 EUR, idempotency key `collaudo-20260906-rimborso`, ID centrale `pay_61a2a19209544b86a642e758`.

Entrambi risultano CONVALIDATO. La seconda convalida restituisce «Movimento già acquisito» per entrambe le righe; la ricerca del solo codice autorizzato nel registro Pagamenti trova ancora esattamente due occorrenze (B4 e B5). Effetto economico netto zero.

La consultazione pubblica «Controlla stato e saldo», con codice ed email dimostrativi, ha riconciliato soltanto questo ordine tramite ELENCA_PAGAMENTI. Il dettaglio amministrativo WordPress mostra i due riferimenti COLLAUDO-INCASSO e COLLAUDO-RIMBORSO. Ripetuta la consultazione, il dettaglio mostra ancora due movimenti da 10 EUR: versato netto 0 EUR, residuo 500 EUR, prenotazione in attesa di pagamento, caparra ancora da versare.

Il percorso centrale → WordPress dei due movimenti e la ripetizione senza duplicati sono verificati sul sistema reale. Restano aperti il completamento riconosciuto della replica iscrizione WordPress → Workspace e la verifica della proiezione aggiornata dei pagamenti nel foglio evento. Il collaudo complessivo non è dichiarato superato. Nessuna email inviata e nessun timer Apps Script attivato; nessun nuovo deployment in questa fase.

## Correzione del controllo CI dopo il cambio versione

Il workflow «Sanitization check» sul commit d3d8f3a aveva il job sanitization superato e il job tests fallito (195/196): il test bootstrap richiedeva ancora 3.23.9. Il precedente risultato 196/196 non copriva quindi il successivo cambio versione. Aggiornata l'attesa a 3.23.10 e verificata anche la costante MI_VERSION; nuova esecuzione locale della suite completa: 196/196 superati. Nessuna regola di sanificazione modificata o esclusa.

## Interfaccia guidata dei movimenti

Preparata la versione 3.23.11 e aggiunto nel progetto Apps Script il file InterfacciaMovimenti.gs. Eseguita soltanto `apriInserimentoMovimentoGuidato`: nel DB_MODULI è stata creata la scheda «Registra movimento», senza registrare nuovi movimenti.

La scheda segue l'ordine Prenotazione → Movimento → Tracciabilità → Verifica e registra. Usa tendine native per codice ordine, tipo, rata e metodo; mostra stato, evento, referente, totale, versato e residuo prima della conferma; richiede una casella di controllo e conserva una chiave idempotente nascosta. Il menu «Modulo iscrizioni» espone apertura, aggiornamento riepilogo e registrazione guidata.

Collaudato il solo riepilogo della prenotazione dimostrativa autorizzata: evento Cammino di Santiago 2027, stato PENDING_PAYMENT, totale 500 EUR, versato netto 0 EUR e residuo 500 EUR. Il messaggio finale invita a controllare i dati e spuntare la conferma. Non è stato premuto «Registra movimento guidato» e non è stato creato alcun ulteriore incasso o rimborso.

Il sorgente locale aggiunge anche `STATO_REPLICA_ISCRIZIONE`, controllo firmato che consente al retry WordPress di riconoscere una replica già completa dopo un timeout. Suite aggiornata: 199/199 test e sanificazione superati. La nuova azione non è ancora nel deployment 44 e WordPress 3.23.11 non è ancora installato: servono autorizzazioni esplicite separate prima di aggiornare il deployment e sostituire il plugin attivo. Nessuna email inviata e nessun timer attivato.
