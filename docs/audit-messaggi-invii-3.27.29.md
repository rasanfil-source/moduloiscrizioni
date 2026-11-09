# Audit Messaggi e invii — 3.27.29

Analisi del 9 novembre 2026 sui sorgenti locali inizialmente alla versione 3.27.28. Correzioni realizzate nella nuova 3.27.29.

Il timeout segnalato è coerente con due percorsi bloccanti confermati nei sorgenti. Non sono stati consultati i log del sito né la distribuzione Google effettiva: non è quindi accertato quale percorso abbia provocato quello specifico timeout. Non sono state inviate email reali o apportate modifiche alla produzione.

## Difetti confermati e corretti

| Priorità | Difetto nella 3.27.28 | Conseguenza | Correzione 3.27.29 |
|---|---|---|---|
| P1 | La prova dalla schermata Messaggi chiamava il worker prima del reindirizzamento. Il trasporto attende Google fino a 60 secondi, con richieste aggiuntive per i reindirizzamenti. | La pagina può superare il timeout del server/proxy anche per una sola prova. | Il POST salva un lavoro persistente e torna alla schermata; nessuna chiamata Google nel gestore del modulo. |
| P1 | La conferma preparava tutte le prenotazioni nella richiesta del browser, includendo calcoli, modelli, immagini e scritture in una transazione. | Il costo cresce con i destinatari; 198 email o molti collegamenti personali possono prolungare eccessivamente la richiesta. | La richiesta salva l'elenco; ogni worker prepara al massimo dieci prenotazioni, rileggendo idoneità e saldi. |
| P2 | Il tentativo immediato non anticipava un cron già fissato nel futuro. I passaggi successivi ripartivano a +30 secondi. | Ritardo iniziale e pause accumulate durante un invio numeroso, oltre alle attese dovute al cron. | Il cron futuro viene anticipato; un risveglio HTTP autenticato avvia e concatena i worker. Rimane il recupero cron persistente. |
| P2 | Ogni destinatario richiedeva un'intera chiamata firmata a Apps Script. | Per 198 email servivano 198 avvii remoti, oltre ai reindirizzamenti HTTP. | Una busta firmata contiene fino a dieci messaggi individuali: venti chiamate nella campagna sintetica senza errori. |
| P1 | La quota esaurita consumava i tentativi come un errore definitivo; dopo cinque rifiuti la riga diventava FAILED. | Messaggi ancora da spedire potevano essere abbandonati prima del ripristino della quota. | La coda resta persistente, i tentativi non vengono consumati e la ripresa è differita di 15 minuti. Google occupato produce una pausa di 30 secondi. |
| P2 | EMAIL_DELIVERY_UNCERTAIN veniva riprovato fino a cinque volte. Il registro Google impediva il reinvio, ma la coda continuava a interrogarlo. | Attese inutili e ritardo nell'evidenziare il problema all'operatore. | L'esito diventa subito un errore visibile per quella riga; le altre email proseguono. Occorre verificare la consegna prima di rigenerarla. |
| P2 | In ANTEPRIMA il worker usciva prima del recupero delle righe TEST_SENDING. | Una prova interrotta al quinto tentativo poteva rimanere indicata come in invio anche dopo 15 minuti. | Manutenzione delle righe interrotte prima dei controlli di modalità: TEST_FAILED visibile, senza un nuovo invio. |
| P2 | La schermata poteva annunciare invii operativi quando mancava la verifica iniziale della casella di prova. La coda avrebbe generato soltanto PREVIEW. | L'operatore poteva attendere email che non sarebbero partite. | Errore esplicito prima del salvataggio del lavoro; una perdita successiva dell'abilitazione sospende la preparazione. |
| P2 | Il conteggio finale per riprogrammare il worker comprendeva anche email di un canale diverso da quello attivo. | Potevano essere riprogrammati passaggi incapaci di elaborare quelle righe. | La riprogrammazione usa il filtro del canale attuale e delle prove esplicite. |

I precedenti difetti di composizione, ripristino bozza, conferme disabilitate e dipendenza del canale PROVA_PUNTUALE, descritti nell'audit precedente, risultano già affrontati dalla 3.27.28. Questo rilascio conserva tali correzioni.

## Comportamento per una campagna da 198 prenotazioni

1. L'operatore controlla anteprima e destinatari e conferma.
2. La pagina salva una richiesta persistente, senza preparare le 198 email o contattare Google.
3. Un worker prepara un gruppo di massimo dieci prenotazioni e acquisisce atomicamente le righe della coda.
4. Google riceve un lotto con destinatari, corpi e chiavi separati. Non si creano email con 198 indirizzi in A/CC/CCN.
5. Ogni esito aggiorna la propria riga. Le chiavi rimangono identiche dopo timeout e ripetizioni della richiesta.
6. I worker successivi ripartono in background; chiudere la pagina non cancella la richiesta.
7. La schermata mostra preparate, accettate da Google, attese, errori, anteprime e ultima quota rilevata. L'ultimo lavoro è recuperato anche tornando nella sezione Messaggi.

Un'email di prova appena richiesta mantiene priorità sulla preparazione e sulla consegna massiva. I lavori di altre modalità restano sospesi; le vecchie email di prova non vengono spedite in modalità operativa.

Il numero 198 rappresenta le prenotazioni selezionate se ciascuna prevede una comunicazione: non equivale necessariamente a 198 persone o a 198 indirizzi distinti. Una prenotazione familiare riceve il riepilogo al proprio referente; prenotazioni differenti sullo stesso indirizzo conservano le rispettive personalizzazioni.

## Verifiche eseguite

- Suite di progetto: **434 test Node superati**, più **7 test sugli asset generati**.
- Sintassi di tutti i file PHP del plugin, asset e sanitizzazione: superati.
- **82 script PHP** della verifica standard: superati, inclusi iscrizioni, rollback, pagamenti, comunicazioni, instradamento e recupero della coda.
- Tre ulteriori regressioni su MariaDB/InnoDB locale: pubblicazione, destinatari gruppo/segreteria e consegne operative. Tabelle temporanee, trasporto simulato.
- Nuova integrazione con tabelle InnoDB temporanee, preparazione e worker applicativi effettivi:
  - 198 destinatari elaborati in venti chiamate Google simulate;
  - doppio POST: un solo lavoro;
  - secondo worker escluso tramite GET_LOCK da una connessione SQL distinta;
  - quota esaurita dopo 17 invii, nessuna riga abbandonata, successivo completamento a 198;
  - risposta HTTP persa dopo accettazione, ripresa con le stesse chiavi, nessun duplicato;
  - un esito incerto fermato al primo tentativo, altre 197 email completate;
  - cambio modalità sospende il lavoro, ripristino della modalità originale lo riprende;
  - prova anonimizzata inviata soltanto all'indirizzo sintetico scelto;
  - endpoint worker privo di firma respinto;
  - modalità operativa non abilitata respinta esplicitamente;
  - prova prioritaria davanti ai 188 destinatari rimasti;
  - prova interrotta al quinto tentativo recuperata anche in anteprima, senza reinvio.
- Test Apps Script: ripetizione dell'intero lotto, quota parziale, lock occupato, esito incerto, limite temporale, dimensione e chiavi duplicate.
- Test frontend: avanzamento 198/198, arresto del polling al completamento, pausa quota, errore visibile e perdita temporanea della connessione.

Il salvataggio della campagna ha richiesto pochi millisecondi nel test locale; questo dato riguarda esclusivamente dati sintetici e macchina locale. Non è una stima dei tempi di invio sul sito. La durata effettiva dipende da Google, carico del server, quota e disponibilità dei richiami HTTP.

Ripetizione della verifica dedicata: tools/verify-email-bulk.ps1, con MariaDB locale di test già avviato. Nessun servizio SMTP reale è usato.

## Limiti da verificare sul sito

- L'accettazione da parte di Google non prova l'arrivo nella posta in entrata; recapito e spam vanno verificati con una prova reale.
- Non sono note quota residua e categoria dell'account che esegue MODULI AUTONOMO. Google documenta 100 destinatari/giorno per account consumer e 1.500 per Google Workspace, con limiti ulteriori per account di prova e altri servizi associati. Per 198 invii nello stesso giorno deve esserci quota sufficiente; il software non aggira questi limiti. Fonte: [Quote Apps Script](https://developers.google.com/apps-script/guides/services/quotas).
- La catena rapida richiede che l'hosting consenta richieste HTTP dal sito verso il proprio admin-ajax.php. In caso di blocco resta il cron di recupero, la cui puntualità dipende dall'hosting. Il cron WordPress ordinario dipende dalle visite: [wp_schedule_single_event](https://developer.wordpress.org/reference/functions/wp_schedule_single_event/). Il nuovo risveglio evita anche di affidare ogni lotto al limite di avvio del cron: [spawn_cron](https://developer.wordpress.org/reference/functions/spawn_cron/).
- I lavori falliti durante la preparazione mostrano l'errore e i conteggi parziali. Prima di creare una nuova campagna occorre controllare quali email siano già state accettate. Riaccodamento e rigenerazione delle singole email conservano le azioni di verifica già presenti nel registro.
- Non sono state installate le correzioni sul sito, interrogate le iscrizioni reali o inviate email ai partecipanti.

## Installazione coordinata

1. Nel solo progetto **MODULI AUTONOMO**, sostituire Codice.gs con Codice-Workspace-Progetto-3.27.29.gs. Salvare e aggiornare la distribuzione Web App esistente mantenendo lo stesso URL.
2. Installare modulo-iscrizioni-3.27.29.zip, oppure la variante pubblico.
3. Dalla sezione Messaggi preparare una comunicazione, richiedere la prova al proprio indirizzo e attendere l'esito nell'avanzamento. Verificare anche l'effettiva ricezione.
4. Confermare poi la campagna corretta, controllando i destinatari mostrati e i conteggi. Se Google segnala quota esaurita, le righe in attesa vengono conservate.

La nuova azione INVIA_EMAIL_LOTTO richiede il sorgente Google aggiornato. Se il vecchio servizio risponde ACTION_NOT_ALLOWED, la coda viene sospesa con errore visibile senza consumare i tentativi: aggiornare la distribuzione prima della ripresa.

Nessun sorgente o pacchetto per progetti Google dismessi è previsto. Gli archivi precedenti, incluso 3.26.226, non vengono ricostruiti né sostituiti.
