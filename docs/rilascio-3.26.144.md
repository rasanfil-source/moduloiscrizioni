# Rilascio 3.26.144 — valutazione audit e correzioni

## Correzioni adottate

- Apertura dei fogli gratuiti in sola lettura: ricevuta persistente legata all'impronta dei dati, senza scadenza arbitraria a cinque minuti. Permessi, coda, configurazione, sostituzione del foglio e segnalazione di file mancante restano verificati. Corretto il controllo URL del reindirizzamento diretto. Preparazione in background accodata anche alla prima creazione del collegamento, con zero iscrizioni. I fogli modificabili conservano il confronto remoto delle celle pendenti.

- Preparazione Workspace: rilascio del lease MySQL durante l'attesa di Google e nuova verifica della cancellazione prima di salvare i collegamenti. La pubblicazione attende fino a tre secondi il lock Workspace; la replica rimane non bloccante.
- Coda email: il lease dell'evento viene rilasciato al termine di ciascuna riga, anche in caso di errore o `continue`. Resta protetto l'invio corrente contro una cancellazione concorrente. Evento occupato restituisce HTTP 409 invece di un errore generico 500.
- Il rapporto stampabile centrale conserva l'evento proprietario; la cancellazione ne rimuove i dati anche se le intestazioni non sono nella prima riga. Generazione e cancellazione condividono il lock. I rapporti precedenti senza proprietario vengono svuotati conservativamente perché sono copie rigenerabili.
- Organizzazione Drive: tre eventi per esecuzione, cursore e continuazione; nessun limite permanente ai primi 200 eventi. Timeout di 60 secondi per le chiamate del lotto. Spostamento richiesto soltanto quando cambia destinazione o identificativo del foglio. Stato archiviato aggiornato solo per gli elementi confermati; quelli falliti restano riprovabili nel ciclo successivo.
- Prenotazioni scadute: controllo ogni cinque minuti, con migrazione della vecchia pianificazione oraria. L'effettiva puntualità dipende dall'esecuzione del cron.
- Indici MySQL su stato/scadenza e prenotazione/tipo evento; InnoDB include già la chiave primaria nell'indice secondario.
- Replica partecipanti: sovrascrittura diretta quando numero e contiguità delle righe coincidono; eliminazione a blocchi negli altri casi.
- Pagamenti e stato operativo indicizzati: niente scansione completa per ogni persona e colonna. Le cache sono legate a una singola lettura, senza riutilizzare dati vecchi dopo nuove letture.
- Riutilizzo dei partecipanti già letti nella costruzione delle colonne; eliminata l'apertura dello spreadsheet nel semplice controllo di esistenza Drive.
- Assegnazione automatica camere saltata quando le opzioni validate non contengono alloggi selezionati.
- Verifica busta tollerante a payload assente.

Le motivazioni sono riportate anche nei commenti accanto alle decisioni principali.

## Proposte non applicate automaticamente

- **Email incerta:** quota e formato vengono già verificati prima di `SENDING`. Non interpretiamo il testo localizzato di un'eccezione di `sendEmail` come prova del mancato invio: un retry cieco può duplicare il messaggio.
- **Apostrofo in celle testo:** il rilievo è un'ipotesi da collaudare sul servizio Google, non un bug dimostrato. Non rimossa la neutralizzazione delle formule né alterati indiscriminatamente gli apostrofi degli utenti.
- **Cache globali, dirty flag, protezioni e metadati:** richiedono invalidazione completa di tutti i percorsi di scrittura e delle modifiche manuali. Questo rilascio usa ottimizzazioni circoscritte, non introduce una cache persistente del riepilogo economico.
- **Eliminazione dei lock:** una lettura del flag di cancellazione non sostituisce l'esclusione reciproca. Non rimossi i lock delle transazioni e degli invii in corso.
- **Batch email/repliche, vista inviata direttamente da MySQL, colonna evento nella coda, retention e separazione snapshot:** interventi architetturali rinviati; non dichiarati completati da questo rilascio.
- **Rimozione del codice presunto morto:** nessuna cancellazione senza verifica di menu, chiamanti e funzioni richiamabili dall'editor. La coda Workspace mantiene anche funzioni di collaudo.
- **Ricerca saldo con semplice uguaglianza SQL e apertura immediata dei fogli modificabili:** non adottate senza dimostrare equivalenza della normalizzazione e protezione delle modifiche pendenti. Le scelte già richieste sul saldo pubblico restano valide.
- `MI_Portal::url()` dispone già di memoizzazione per richiesta; il suggerimento era parzialmente superato.

## Validazione e installazione

Test Node su WordPress/Workspace, regressioni PHP (saldo pubblico, servizi, presenze, apertura fogli, percorsi guidati, paginazione), nuovi casi su rapporti cancellati, indici pagamenti e organizzazione dei fogli. Controlli sintattici PHP e sanitizzazione del repository. I test MariaDB vengono eseguiti anche da GitHub Actions.

Aggiornare Web App Apps Script e plugin. Workspace non cambia schema né autorizzazioni e non richiede di rieseguire la configurazione. Lo ZIP del plugin deve essere installato dall'utente su WordPress.
