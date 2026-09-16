# Saldi e replica Workspace — 14 settembre 2026

## Contratto dei dati

`mi_registrations.balance_cents` conserva il piano della seconda rata alla creazione/ultima modifica economica. Non è un residuo contabile e non deve essere aggiornato quando arriva un pagamento. Il residuo corrente è quello di `MI_Payment_Ledger::position()`: totale meno versato netto, entrambi limitati inferiormente a zero. Gli storici conservano gli importi dell'epoca.

## Mappa degli utilizzi

| File / percorso | Significato e trattamento |
| --- | --- |
| `class-mi-activator.php` | Colonna DB del piano rate. Nessuna migrazione. |
| `class-mi-registration-service.php`: creazione, `riepilogo_economico`, accettazione lista d'attesa | Scrittura del piano rate. |
| Stesso file: replay idempotente e `riepilogo_salvato` | Restituiscono il piano salvato, non la posizione pagamenti. |
| Stesso file: snapshot originale e snapshot legacy | Dati economici conservati, non aggiornati dai successivi incassi. |
| Stesso file: `sync_workspace` | Corretto: invia residuo e versato corrente, includendo anche i movimenti originati da Workspace. La lettura precede il controllo della revisione. |
| Stesso file: `public_status` | Residuo già ricalcolato dai pagamenti; la SELECT include anche il piano senza usarlo per il residuo. |
| `class-mi-management-service.php`: dettaglio | Posizione corrente da ledger. |
| Stesso file: cambio sistemazione, servizi, rettifica | Aggiornano il piano rate; l'audit della rettifica conserva anche il valore precedente. |
| `class-mi-public-balance.php` | Aggiorna il piano quando cambia il totale. |
| `class-mi-admin.php`: lista | Piano selezionato ma non mostrato come residuo. |
| Stesso file: dettaglio | Seconda rata esplicitamente pianificata; versato e residuo corrente visibili anche senza movimenti. Il primo versamento non è chiamato caparra perché può essere l'intero totale. |
| Stesso file: CSV | Residuo e versato da `positions()`. Importi una sola volta per prenotazione e indicatore di riepilogo. |
| Stesso file: anteprima email conservata | Snapshot storico, intenzionalmente invariato. |
| `class-mi-portal.php`: comunicazioni, lista e consultazione | Comunicazioni/lista usano le posizioni; consultazione usa `public_status`. Alcune SELECT includono il piano senza mostrarlo come residuo. |
| `class-mi-spedizione-email.php` | Solleciti e comunicazioni ricevono il residuo aggiornato; i payload delle email inviate restano storici. |
| `class-mi-modello-email.php`: valori economici | Formattatore del riepilogo ricevuto: piano per conferme originali, residuo per comunicazioni costruite con posizione corrente. Non è una fonte autonoma del saldo. |
| Apps Script `WebApp.gs` | Scrive il `balance_cents` ricevuto nella colonna `saldo_centesimi`: qui arrivava il piano stantio. |
| Apps Script `Config.gs` | Definisce le intestazioni, inclusa la colonna saldo. |
| Apps Script `Segreteria.gs` / `Segreteria.html` | Dettaglio, pulsante saldo, stato pagamenti, destinatari e report derivano già dai movimenti. Non sono tutti affetti dal dato stantio replicato. |

## Replica e limiti della patch

La nuova proprietà `paid_cents` è disponibile nel payload; l'attuale Apps Script non la salva in una nuova colonna e continua a ricavare il versato dal registro. I movimenti Workspace restano esclusi dall'elenco da replicare, ma inclusi nella somma autorevole MySQL. Errori di lettura lasciano la prenotazione PENDING; una revisione cambiata impedisce l'invio del payload misto.

Il client usa JSON stabile e HMAC-SHA256. Il primo redirect viene validato come HTTPS su googleusercontent.com; il successivo GET permette fino a tre redirect. Il retry sul 404 è specifico di PREPARA_PRODUZIONI_EVENTO. Questi controlli non costituiscono una verifica completa di sicurezza del canale.

L'accodamento di tutte le prenotazioni per modifica camere esiste sia nel percorso inventario sia in quello di gestione prenotazione. Il recupero periodico preleva 10 righe, ma ci sono anche invii singoli programmati e retry. Il rischio di arretrato è reale; escludere soltanto CANCELLED/EXPIRED ridurrebbe alcuni invii ma non elimina il lavoro proporzionale all'evento. Nessuna modifica di questa politica nella patch.

Le righe già SYNCED non vengono automaticamente riscritte: riceveranno il residuo aggiornato alla prossima risincronizzazione. La patch è locale e non esegue aggiornamenti sul sito o sui fogli. La colonna del foglio è una fotografia alla replica, non un calcolo continuamente aggiornato.

## Verifica

`workspace-balance.php`: assenza pagamenti, parziale, saldato, netto dopo rimborso, credito, errore DB e revisione concorrente. Verifica anche che il netto possa essere presente quando l'elenco dei movimenti da replicare è vuoto.

Passati i controlli sintattici dei due file modificati e 12 test Node su coda, report e ricezione Apps Script. Il test preesistente `payment-ledger.php` presenta un errore della simulazione già riprodotto sul codice HEAD nel precedente intervento.
