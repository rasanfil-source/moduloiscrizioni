# Eliminazione completa di un evento

Stato: implementata nella 3.25.3, collaudo online in corso. Il seguito conserva la specifica di riferimento; non tutte le verifiche elencate sono già concluse.

La pulizia riprende dalla pagina aperta o dal comando WordPress «Eliminazioni»; gli errori richiedono «Riprendi eliminazione». Non è previsto un worker autonomo per riprendere gli errori. La vecchia eliminazione automatica delle bozze è disattivata. Non considerare conclusa una cancellazione finché compare «Evento eliminato».

## Comando e conferma

Nel menu a tre puntini di Gestisci eventi aggiungere «Elimina definitivamente…».
Disponibile agli amministratori e ai gestori globali autorizzati; non basta poter
registrare pagamenti o essere assegnati a un singolo evento.

Il comando apre un riepilogo letto dal server, senza cancellare nulla:

- titolo, stato e ID dell'evento;
- numero di iscrizioni, partecipanti, movimenti di pagamento e camere;
- comunicazioni ancora in coda;
- presenza del foglio Google, suo titolo e collegamento;
- eventuale pagina WordPress dedicata all'evento.

Scelta obbligatoria per il foglio presente:

1. «Sposta anche il foglio Google nel cestino»;
2. «Conserva il foglio Google come copia scollegata».

La seconda scelta conserva il documento e il suo accesso attuale, ma elimina
l'associazione e interrompe ogni sincronizzazione. Il riepilogo finale contiene
il collegamento al documento conservato. Il cestino Drive non equivale alla
distruzione definitiva del file: la dicitura deve essere precisa.

Conferma finale: casella «Ho compreso che i dati dell'evento saranno eliminati»
e pulsante «Elimina evento e dati». Per un evento con iscrizioni chiedere anche
di riscrivere il titolo. Una sola conferma, nessuna catena di finestre.

«Annulla evento», «Archivia» e «Elimina definitivamente» restano azioni distinte.
L'annullamento conserva i dati; l'eliminazione non invia email di annullamento.

## Dati da eliminare

### WordPress / MySQL

Per event_id: mi_registrations, mi_event_counters, mi_ticket_counters,
mi_event_revisions, mi_rooms, mi_management_state, mi_management_requests,
mi_booking_codes. Verificare i nomi reali delle variabili di schema prima delle query.

Per gli ID delle iscrizioni dell'evento: righe degli acquisti, partecipanti,
pagamenti, storico delle operazioni e coda email. Eliminare i figli prima delle
iscrizioni, con JOIN sull'ID e una transazione InnoDB.

Eliminare poi il post evento con le API WordPress e i suoi metadati; rimuovere
l'ID dagli ambiti _mi_event_scope degli operatori senza cancellare utenti o altri
eventi assegnati. Invalidare le cache relative all'evento e alle viste dei gruppi.

Una pagina di iscrizione si elimina soltanto se il plugin può dimostrarne la
proprietà esclusiva per quell'evento. Un semplice URL o un ID memorizzato non
autorizza a cancellare una pagina condivisa. In caso dubbio segnalarla nel risultato.

### Google Workspace

Prima di togliere le iscrizioni raccogliere tutti i codici ordine dell'evento.
Usare sia gli identificativi WordPress sia quelli presenti nella replica Google,
per includere eventuali vecchie righe residue.

- Per id_evento: Eventi, Fogli iniziative, Viste operative, Sistemazioni,
  Revisioni replica e modelli report esclusivi dell'evento.
- Per codice_ordine: Iscrizioni, Partecipanti, Pagamenti, Coda email,
  Operazioni segreteria, Stato operativo ed Elenco operativo.
- Gestire Registro controlli tramite riferimenti strutturati; mai cercare e
  cancellare righe con una corrispondenza libera sul titolo.
- Cestinare o scollegare il documento operativo secondo la scelta confermata.

Conservare Gruppi, Configurazione, modelli report condivisi, cartelle EVENTI,
EVENTI PASSATI e documenti degli altri eventi. Non cancellare immagini o allegati
condivisi della libreria media.

## Procedura riprendibile

MySQL e Google non condividono una transazione. Non promettere un rollback globale:
se Google è stato pulito e WordPress fallisce, occorre riprendere la procedura.

1. **Anteprima**: autorizzazioni, nonce, conteggi e impronta dei dati da eliminare.
2. **Conferma**: sotto protezione dalla concorrenza, verificare l'impronta e creare
   un lavoro persistente con request_id, event_id, attore, scelta sul file e ID
   Google. Se i dati sono cambiati mostrare il riepilogo aggiornato e riconfermare.
3. **Evento in eliminazione**: impedire nuove iscrizioni, pagamenti, modifiche,
   pubblicazioni, duplicazioni e sincronizzazioni per quell'evento. Tutti i canali
   devono rispettare lo stesso blocco, inclusi REST, cron e amministrazione WP.
   Un controllo eseguito solo prima della transazione non basta: impedire che
   richieste già avviate scrivano dopo l'inizio della cancellazione.
4. **Attendere le operazioni avviate**: fermare l'acquisizione di nuovi lavori email
   e replica e attendere quelli già presi in carico. I worker devono riverificare
   il blocco prima degli effetti esterni. Non tenere una transazione SQL aperta
   durante una chiamata Google.
5. **Pulizia Google**: API firmata dedicata, idempotente, con lock Apps Script e
   identificativo della cancellazione. Registrare un marcatore minimo di evento
   eliminato per respingere repliche tardive e impedirne la ricreazione. Eliminare
   a blocchi con avanzamento persistente; non affidarsi a un unico lungo ciclo.
6. **Pulizia MySQL**: cancellare in transazione tutte le righe applicative. Poi
   completare post, pagina esclusiva, ambiti utente e cache con passi idempotenti.
   Gli errori devono restare visibili e riprovabili, non essere assorbiti.
7. **Completamento**: mostrare «Evento eliminato» soltanto quando tutti i passi
   obbligatori sono riusciti. Se il documento è conservato mostrare anche il link.

Il lavoro persistente e il marcatore minimo contengono soltanto gli identificativi
tecnici necessari a evitare replay e riprendere errori, non copie dei dati personali.
Un retry con lo stesso request_id riusa il lavoro; una scelta differente per lo
stesso request_id è respinta. Il refresh della pagina non avvia una seconda rimozione.

Stati visibili: «In eliminazione», «In attesa di Google», «Da riprovare», «Eliminato».
Mostrare il passo riuscito e quello ancora da completare. Se Google non è mai stato
usato per l'evento, saltare la fase remota solo dopo una verifica affidabile:
l'assenza del solo metadato sheet non dimostra l'assenza di repliche centrali.

## Cancellazione dall'amministrazione WordPress

Intercettare anche cestino, eliminazione definitiva, azioni multiple e pulizie cron.
L'eliminazione definitiva nativa deve essere bloccata o instradata verso la stessa
procedura; non basta agganciare deleted_post, quando i riferimenti sono già persi.
La rimozione finale interna deve avere un bypass circoscritto al lavoro autorizzato.

Sostituire la pulizia differita attuale di MI_Portal::purge_trashed_drafts con la
stessa procedura, rispettando le regole del cestino. Non attivare retroattivamente
una cancellazione massiva di eventi esistenti durante l'aggiornamento del plugin.

## Problemi verificati nel codice attuale

- Nessuna pulizia completa delle tabelle custom agganciata alla cancellazione evento.
- purge_trashed_drafts gestisce soltanto eventi senza iscrizioni e il documento.
- eliminaFoglioEventoDaWordPress_ assorbe l'errore Drive e cancella ugualmente il
  collegamento: deve distinguere file già cestinato, file assente e accesso negato.
- Eliminare il documento non elimina le righe dei fogli centrali.

## Verifiche necessarie prima di attivare il comando

- Bozza senza dati, bozza con sheet, evento con iscrizioni e pagamenti.
- Conservazione del documento e cestinazione, inclusa ripetizione della richiesta.
- Evento gemello dello stesso gruppo e immagini condivise completamente invariati.
- Conteggi cambiati tra anteprima e conferma.
- Richieste simultanee: registrazione, pagamento, gestione, replica ed email.
- Timeout Google prima e dopo l'effetto remoto; ripresa senza duplicazioni.
- Accesso Drive negato: nessun falso successo e nessuna perdita del collegamento.
- Errore SQL e errore WordPress dopo la fase Google: lavoro ancora riprendibile.
- Vecchia replica in arrivo dopo la cancellazione: nessuna ricreazione.
- Cancellazione nativa WP, multipla e cron incapaci di aggirare la procedura.
- Permessi, nonce e riutilizzo del request_id con contenuto differente.

La funzione Duplica evento resta indipendente: crea una bozza senza collegamento
Google; l'utente avvia successivamente la creazione di un nuovo documento.
