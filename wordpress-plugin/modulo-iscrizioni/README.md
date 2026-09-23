# Aggiornamento 3.26.114

Email istituzionali: aggiunti 12 px tra l'intestazione «Segreteria parrocchiale · Portale eventi» e il logo. Apps Script invariato rispetto alla 3.26.113.

# Aggiornamento 3.26.113

Cancellazione evento con avanzamento AJAX e timeout Google dedicato di 110 secondi. Aggiornare anche EliminazioneEvento.gs nella distribuzione Apps Script per attivare la cancellazione per intervalli contigui. Permessi, conferme, lock e ripresa idempotente conservati.

Export CSV a blocchi con snapshot coerente, residuo corrente e importi una volta per prenotazione; replica Workspace con versato e residuo dal ledger. Prezzi con virgola normalizzati e limite del totale verificato alla creazione. Query elenco eventi semplificata. Asset minificati con fallback ai sorgenti, favicon Segreteria scelta dall'operatore.

Verifiche locali PHP, Node e InnoDB completate; collaudo WordPress/Google su evento fittizio ancora necessario prima della produzione.

# Aggiornamento 3.26.112

Avviso automatico agli indirizzi delle prenotazioni attive o in attesa per annullamento/eliminazione evento. Contatto del gruppo o segreteria nel testo e Reply-To. L’avviso di eliminazione sopravvive alla pulizia dati e parte solo dopo completamento; deduplicato per indirizzo. Modalità prova rispettata. Il gestore può inviare ulteriori spiegazioni facoltative prima di eliminare i contatti.

# Segreteria eventi — aggiornamento 3.26.111

La Segreteria eventi ha ora un’icona propria per la scheda del browser e i collegamenti salvati: riprende croce, fiore e baldacchino del logo della Parrocchia Sant’Eugenio e aggiunge un piccolo registro dorato, simbolo del lavoro di segreteria. L’icona è limitata al portale operativo e non sostituisce quella del sito nelle pagine pubbliche.

## Aggiornamento 3.26.110

Gestione iscrizioni offre “Tutti gli eventi”: risultati per persona, evento e data, con ricerca e caricamento progressivo. Rimossa la voce separata di navigazione; i vecchi collegamenti aprono la gestione condivisa.

La scheda mostra quota, versato, residuo o credito della sola persona. “Vedi tutta la prenotazione” raccoglie tutte le persone, sistemazioni e servizi, quote individuali e totali. I crediti individuali sono separati dai debiti delle altre persone.

Aggiunta e rimozione servizi presentano l’anteprima economica e salvano insieme servizi e variazione del totale, senza alterare quote e pagamenti degli altri iscritti. La caparra individuale viene conservata (ridotta solo se supera la nuova quota); il saldo assorbe la variazione. Il salvataggio mantiene aperta la persona scelta. Rimborsi non automatici. Lo schema aggiunge deposit_due_cents ai partecipanti durante l’aggiornamento del plugin.

Storico preservato: versamenti di gruppo precedenti senza attribuzioni sono indicati come da attribuire; quote comuni o vecchie rettifiche non attribuite sono indicate come da verificare. Non vengono presentati importi complessivi come personali. Rimane il limite della 3.26.109 sui rimborsi di movimenti attribuiti: richiedono un flusso individuale dedicato.

Email: alla prima pubblicazione viene accodata la notifica “Congratulazioni! Il tuo evento è stato pubblicato.”, con logo e banner propri/ereditati, data e luogo. Destinatario del gruppo o segreteria; mantenuta la notifica del gestore prevista dal flusso di attivazione. Gli aggiornamenti non duplicano il messaggio. Le notifiche di prenotazione alla segreteria riportano “Partecipanti: N” e due pulsanti affiancati: “Apri prenotazione”, per la prenotazione ricevuta anche quando comprende più persone, e “Apri elenco iscritti”, per la lista dell’evento in Gestione iscrizioni. Modalità test rispettata.

Verifiche: integrazione InnoDB per aggiunta/rimozione, credito, isolamento quote, retry e ricerca autorizzata; browser per ricerca, riepilogo completo, anteprima, permanenza sulla persona, ritorno ai risultati e larghezze 320–1024px. Installazione sul sito da completare; Apps Script invariato.

# Segreteria eventi — aggiornamento 3.26.109

Pagamenti per persone selezionate: la ricerca mette in primo piano il partecipante trovato, le spunte determinano a chi attribuire il movimento e l’importo deve corrispondere esattamente alla caparra, al saldo successivo o al totale residuo. Le attribuzioni sono conservate in MySQL con il movimento e il retry rimane idempotente. Storico esistente preservato: prenotazioni multiple con pagamenti non attribuiti o quote comuni/rettifiche non individualizzate richiedono verifica prima di nuovi incassi. I rimborsi indistinti su movimenti già attribuiti sono bloccati per non alterare i saldi personali. Nessun reset automatico. La scheda persona omette Presenza effettiva; nell’elenco il codice lascia posto alla data d’iscrizione.

## Aggiornamento 3.26.108

Rifiniture del selettore e delle tessere, preservando immagini, loghi e blu originale. Il riepilogo omette Confermata e il doppione Pagamento atteso quando compare il residuo. Pagamenti esclude gli eventi gratuiti dalla ricerca e decodifica i titoli anche nella scheda aperta. Comunicazioni distingue la modalità test e prepara l’invio esclusivamente al destinatario di prova quando configurato. Verificato nel browser il percorso della barra azioni del cambio sistemazione, inclusi anteprima, conferma e retry. Installare lo ZIP aggiornato per caricare gli asset della nuova versione.

## Aggiornamento 3.26.107

Ripristina tutte le modifiche successive alla 3.26.82 e aggiorna la gestione camere: nell’elenco appare la sigla della sistemazione richiesta quando la camera non è ancora assegnata, il comando Gestisci è più compatto e il pannello camere è più riconoscibile. Due richieste di doppia matrimoniale nella stessa prenotazione vengono abbinate automaticamente anche in presenza di partecipanti con sistemazioni diverse; con tre o più richieste l’assegnazione resta manuale. In Assegna stanze e Cambia tipo di abitazione, verifica, conferma e annullamento restano visibili in una fascia fissa in fondo allo schermo. Caricare lo ZIP per sostituire il plugin esistente.

## Note delle versioni precedenti

# Segreteria eventi — aggiornamento 3.26.5

Riepilogo delle persone, filtro Stato condizionale, gestione camere per sistemazione e progressivi automatici S/DM/DS/T/M. Il comando Cambia sistemazione mostra l’anteprima per persona e per iscrizione e aggiorna servizi, camera e dovuto in un’unica transazione. Conserva le tariffe dell’iscrizione, gli altri servizi e le rettifiche precedenti. I rimborsi restano manuali. Apps Script non richiede aggiornamenti rispetto alla distribuzione 3.26.1. Installazione sul sito da completare.

# Modulo Iscrizioni — versione 3.23.32

Plugin WordPress per configurare e pubblicare moduli, raccogliere iscrizioni e consegnarle in modo firmato alla console operativa Google Workspace.

## Funzioni principali

- revisioni pubblicate e snapshot ordine immutabili, identificati da hash SHA-256;
- ACL per gruppo applicate a elenchi, editor, azioni amministrative ed esportazioni;
- idempotenza risolta anche dopo chiusura evento, timeout o esaurimento posti;
- capienza globale e per tipologia protetta da lock transazionali, con lista d’attesa;
- partecipanti associati in modo univoco alla tipologia e alla posizione acquistata;
- opzioni quantitative per ordine o partecipante, ricalcolate sempre sul server;
- profili dati configurabili e approvazione esplicita dei campi privacy ad alto impatto;
- consenso privacy versionato e consenso facoltativo separato per comunicazioni su future iniziative;
- scadenza delle prenotazioni non saldate e annullamento con rilascio idempotente dei posti;
- lista d’attesa con email dedicate, proposta temporanea configurabile, accettazione o rinuncia tramite collegamento personale e passaggio automatico al candidato successivo;
- pagamenti e rimborsi manuali serializzati, auditati e privi di dati completi di carta;
- outbox email con anteprima, prova sintetica, modalità operativa protetta, retry e recupero;
- identificativo `NONE`, `TEXT`, `QR` o `BARCODE`, generato localmente anche nell’email;
- replica Workspace asincrona e riconciliante, marcata `SYNCED` soltanto dopo verifica completa;
- shortcode `[modulo_iscrizioni event="ID"]`, pagina concentrata e modulo Divi 4;
- indirizzo pubblico autonomo per il pulsante Iscriviti e indirizzo evento-specifico per consultare stato e saldo;
- schermata Produzioni con collegamenti pronti da copiare e foglio operativo Workspace associato all’evento.
- condivisione privata del foglio con il solo gestore responsabile e comunicazione dedicata, mantenuta in anteprima finché la spedizione generale non è operativa.
- anteprima riservata isolata dalla toolbar e dagli hook amministrativi del tema;
- scheda prenotazione in sovrimpressione accessibile sia nel pannello sia nel portale, con collegamento normale come fallback.
- gerarchia della scheda portale centrata sui partecipanti, con codice marginale e referente indicato solo nelle prenotazioni multiple;
- tessere con data e immagine; gli eventi passati sono separati dalla vista ordinaria e raggiungibili dal collegamento allo storico.
- scheda Iscrizioni con ricerca, filtri compatti e tessere operative che evidenziano referente, contatto, evento, stato e saldo;
- caricamento leggero del dettaglio prenotazione, cache nella sessione della pagina e rendering differito delle schede fuori schermo;
- ritorno esplicito all’elenco e spostamento recuperabile nel cestino delle sole bozze prive di iscrizioni;
- eventi annullati conservati nella gestione ordinaria come tessere compatte, attenuate e chiaramente contrassegnate;
- ruoli operativi distinti per tutto il servizio, gruppi assegnati o singoli eventi in corso, con migrazione automatica dei precedenti ruoli;
- consultazione riservata dello stato e del saldo mediante codice/email o collegamento firmato, senza esporre note o dati dei partecipanti;
- promemoria pre-evento e promemoria saldo preparati da Sheets e consegnati alla coda WordPress firmata;
- tipi di comunicazione personalizzati aggiungibili ed eliminabili dal Gestore iscrizioni, senza alterare i tipi di sistema o lo storico;
- gli eventi in bozza forzano sempre le comunicazioni operative nello stato `PREVIEW`.

## Migrazione amministrativa del gruppo

Quando un evento possiede già iscrizioni, il normale editor impedisce di cambiarne il gruppo. Un amministratore può usare **Modulo iscrizioni → Migrazione gruppo**: l’azione richiede evento, gruppo di destinazione e una frase di conferma che contiene entrambi gli ID. La migrazione aggiorna soltanto il collegamento dell’evento, verifica il numero di iscrizioni prima e dopo, conserva revisioni e istantanee storiche, registra un audit nei metadati e non pubblica contenuti né invia email.

## Limiti intenzionali

Il plugin non riscuote denaro e non conserva coordinate bancarie, numeri completi di carta o credenziali di provider. Bonifico, carta e contante sono soltanto fonti di movimenti registrati manualmente. L’email parte in `ANTEPRIMA`; `OPERATIVO` resta bloccato finché una prova con dati sintetici non è stata accettata dal sistema di posta.

## Installazione di prova

1. Comprimere la cartella `modulo-iscrizioni` in uno ZIP e aggiornare il plugin nell’ambiente autorizzato.
2. Verificare che l’upgrade abbia impostato `mi_db_version` alla stessa versione del plugin e aggiornato tabelle e indici.
3. Aggiornare Apps Script allo schema `1.8.0`, eseguire `configuraCartellaDiLavoro()` e verificare lo schema dal pannello WordPress.
4. Aprire e salvare gli eventi pubblicati per creare la prima revisione completa; ripubblicare quelli segnalati dopo modifiche al gruppo.
5. Collaudare capienza, retry, annullamento, scadenza, email e replica con sole identità fittizie.

Non usare dati reali durante il primo collaudo.
