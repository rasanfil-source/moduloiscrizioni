# Contratto della gestione web

## Contesto e decisioni

Le variazioni operative comprendono tutti i servizi configurati, non soltanto la sistemazione. Ogni cambio conserva motivo, prima e dopo; non ricalcola automaticamente il dovuto e non crea rimborsi. La rettifica del dovuto richiede il permesso pagamenti; il rimborso effettivo rimane un movimento separato. Le presenze non sono dedotte dalle iscrizioni e i collegamenti personali per il rapporto annuale richiedono verifica e conferma esplicite. Gli omonimi e i contatti familiari non vengono uniti automaticamente.

La scheda e l’accesso Pagamenti separato proteggono bozze e richieste con esito incerto. Il comando di chiusura del dettaglio consulta il blocco della gestione prima di rimuovere il contenuto; le etichette del modulo pagamenti usano identificativi univoci per istanza. I filtri della vista individuale e le colonne dinamiche si applicano anche all’esportazione completa. Gli eventi con inizio futuro restano attivi anche se le iscrizioni sono già chiuse.

Il progetto è in sviluppo con dati fittizi. MySQL è il registro operativo. Il portale e il comando Sincronizza dal foglio evento usano gli stessi servizi. I fogli mantengono le correzioni locali finché non sono confermate; i pagamenti si inseriscono dal portale. La cronologia Git rimane; si conservano tre versioni degli artefatti e delle note di rilascio.

Il modello autorizzativo è definito da `class-mi-access.php`: sessione personale, account non sospeso, capacità richiesta e controllo dell'evento lato server. `MI_Payment_Ledger` conserva importi positivi e tipi di movimento; i rimborsi sottraggono dal versato. Annullamenti e disponibilità delle iscrizioni restano responsabilità di `MI_Registration_Service`. `MI_Management_Service` valida partecipanti, capienza camere e conflitti delle modifiche da Google.

## Canonical UI Map

| Capability | Canonical owner | Source of truth | Allowed variants | Verification |
|---|---|---|---|---|
| Select/Listbox | Select HTML del portale | Questo contratto | Native: popup e tastiera del sistema operativo | Browser, tastiera, viewport stretta |
| Date | Input HTML date | Form pagamenti e definizioni evento | Native: calendario del browser in locale utente | Test date server, browser it-IT |
| Form | portal-management.js / portal-payments.js | MI_Management_Service / MI_Payment_Ledger | Modifica partecipante; movimento immutabile | Test InnoDB e browser |
| Scrollbar | portal.css | DESIGN.md | Baseline confinata alla .mi-portal | Verifica mobile e overflow tabella |
| Toast | Regione role=status della schermata | Esito server | Informazione, errore, successo confermato | Browser e retry |
| CRUD | MI_Portal_Management | Servizi MySQL | Salva e rimani; sincronizza le celle in blocco | management-innodb.php |

## Comportamenti

- Gestione camere offre il filtro Tutte / Assegnate / Da assegnare, inizialmente Da assegnare, basato sul codice camera salvato delle persone della sistemazione scelta. Il filtro resta selezionato dopo il salvataggio; se non ci sono risultati viene indicato come tornare a Tutte. Come il cambio di sistemazione, il cambio di filtro è bloccato con bozze o salvataggi pendenti. Le colonne sono Persona iscritta, Codice, Numero; i riferimenti di iscrizione restano interni.

- Cambia sistemazione richiede permesso pagamenti, persone ammesse, tariffa storica della nuova sistemazione; le annotazioni sono facoltative e inizialmente chiuse. L’anteprima è di sola lettura e mostra vecchia/nuova camera e differenza del dovuto per ogni iscrizione; conserva altri servizi e rettifiche pregresse. Conferma aggiorna servizi, camera e dovuto in un’unica transazione, con controllo della versione comprensiva dei versamenti, retry e audit consultabile. Caparra prevista conservata entro il nuovo totale; rimborsi manuali. Una defezione non converte automaticamente la sistemazione dei rimanenti. Le funzioni generiche Cambio servizi e Rettifica dovuto restano disponibili per gli altri casi.

- Numerazione automatica all’iscrizione ammessa e all’accettazione dalla lista d’attesa: S e M individuali; DM/DS condivisa soltanto per iscrizioni di esattamente due persone con identica richiesta; T condivisa soltanto per iscrizioni di esattamente tre persone con identica richiesta. Non sovrascrive codici esistenti e non abbina iscrizioni diverse. Il progressivo segue il massimo esistente per prefisso nell’evento, sotto il lock condiviso delle assegnazioni. Nessun pulsante per proporre numeri automatici: i casi incompleti restano da assegnare e le successive variazioni sono gestite dall’operatore.

- Gestione camere è una sezione principale dopo il comando Nuova iscrizione. Il selettore Tipo di sistemazione usa le opzioni dell’evento e le richieste già registrate; mostra le persone ammesse da qualsiasi prenotazione, raggruppate per codice, con Da assegnare per prime. S/DM/DS/T hanno rispettivamente uno/due/due/tre posti per codice. M è un progressivo individuale in camerate senza limite: il vincolo di unicità del codice non rappresenta la capienza della camerata. Nessuna persona viene assegnata senza Salva assegnazioni. I progressivi proposti restano una bozza. Salvataggio atomico, controllo della richiesta corrente, capienza finale, conflitti prima/dopo, retry idempotente e audit usano il servizio condiviso con il foglio. La creazione del codice e le assegnazioni si annullano insieme in caso di errore. Importi e sistemazioni richieste restano invariati.

- Il filtro unico Stato sostituisce i precedenti filtri Stato e Caparra: assente negli eventi gratuiti; Tutti gli iscritti / Da saldare / Saldato per pagamento unico; Tutti gli iscritti / Nessun versamento / Caparra da completare / Caparra versata / Saldato per caparra e saldo. Default Tutti gli iscritti. I filtri economici usano il versato netto e il residuo della prenotazione anche nell’elenco individuale, nella stampa e nel CSV.

- Gestione iscrizioni presenta un unico elenco, una riga per persona, senza selettore partecipanti/prenotazioni. Il riepilogo conta le persone per stato e non mostra il numero delle prenotazioni. Gli importi rimangono aggregati una sola volta per prenotazione; il modello dei dati e i fogli degli eventi sono preservati.

- Ricerca della gestione locale al riepilogo, senza trasmettere nomi nei parametri URL; pagine di 30 righe con «Mostra altre 30». I codici di evento e ordine identificano il contesto del collegamento. I dati personali e le bozze restano in memoria, senza localStorage.
- Il web mostra soltanto un modulo pagamenti. La scheda prenotazione vi conduce con il codice già cercato. Totali e storico sono letti dal medesimo registro centrale.
- I salvataggi attendono il server. Il pulsante impedisce doppie attivazioni. Il retry riusa l'identificativo e il contenuto; un identificativo con dati diversi è respinto. Gli importi sono in centesimi.
- La scheda modifica un modulo alla volta: al primo cambiamento blocca gli altri form finché il salvataggio non è confermato o l'operatore scarta esplicitamente la bozza. Annullamenti e cancellazioni non scartano implicitamente una modifica. Il recupero dopo errore conserva il contenuto e il retry originale.
- Liste, dettaglio e preparazione comunicazioni distinguono piano originario e residuo corrente: i movimenti netti MySQL determinano il residuo. PRICE_ONLY non è presentato come un incasso da effettuare; l'attesa e i posti proposti non contribuiscono al totale da incassare.
- Negli eventi a pagamento il riepilogo mostra prima le persone iscritte (confermate e con pagamento atteso, escluse lista d’attesa e iscrizioni chiuse). Con caparra e saldo seguono Persone confermate e Persone con saldo completato; con pagamento unico segue Persone con pagamento completato. Queste righe successive appaiono solo con conteggio positivo. Il saldo completato usa il residuo nullo, come il filtro Saldato; chi ha saldato resta incluso negli iscritti e nelle confermate. Negli eventi senza incassi resta Persone confermate. Persone in lista d’attesa e Persone con posto proposto appaiono ciascuna solo con conteggio positivo. Il versato netto dell'evento comprende anche le prenotazioni chiuse. Gli obblighi ONE restano associati al primo partecipante originario anche se annullato.
- Una versione obsoleta viene respinta; l'operatore ricarica i dati. Le transazioni MySQL applicano tutto o niente. Il comando Sincronizza mostra prima/dopo, conserva il contenuto e l’identificativo nei tentativi ripetuti e permette scambi tra camere piene verificando la capienza finale.
- La conferma di annullamento o cancellazione camera è un dialog HTML accessibile. Escape chiude, il fuoco torna al comando precedente. Le modifiche non salvate richiedono conferma prima della navigazione interna.
- Una camera occupata non può essere cancellata; la capienza non può scendere sotto gli occupanti. Svuotare la selezione camera rimuove l'assegnazione. I campi facoltativi possono essere svuotati.
- Nuove iscrizioni aprono il modulo esistente dell'evento; l'annullamento individuale usa il servizio già responsabile dei posti. Non si introducono prezzi arbitrari o modifiche dirette ai contatori.
- I report esistenti rimangono disponibili. La stampa della gestione rappresenta la vista corrente, non un nuovo archivio.

## Visual contract e verifica

`DESIGN.md` conserva l'identità. `portal.css` possiede i token runtime --ink, --navy e --line; portal-management.css li consuma. Il tema operativo è chiaro, in italiano; importi EUR e date giorno/mese/anno.

Test automatici: `node --test workspace-apps-script/tests/*.test.mjs wordpress-plugin/tests/*.test.mjs`.
Browser con backend sintetico: `tools/test-gestione-browser.cjs`, usando Playwright disponibile nell'ambiente tramite `MI_PLAYWRIGHT_MODULE`.
Il collaudo browser sintetico verifica la UI, non sostituisce una prova con WordPress, MySQL e Google Apps Script distribuiti.

Il filtro Periodo della gestione usa le stesse date e archiviazione di Iscrizioni. Cambiarlo azzera evento e scheda, preserva la protezione dalle modifiche non salvate e aggiorna il contesto URL. «Attivi» comprende eventi correnti, futuri e bozze non passati.
# Avanzamento gestione settembre 2026

La lista individuale attribuisce email e telefono al referente. Importi ed esportazioni economiche hanno unità prenotazione. La ricerca e i filtri si conservano tornando dalla scheda; stampa ed esportazione includono tutti i risultati filtrati. Il pagamento contestuale riusa il modulo Pagamenti e protegge la bozza; gli scambi camere usano una transazione unica con verifica del valore precedente e della capienza. Lo stato completo delle verifiche e delle funzionalità ancora aperte è in docs/VERIFICA_GESTIONE_AVANZAMENTO_2026-09-10.md.

Le righe complete della lista evento arrivano dal server a pagine di 30. Ricerca, CSV e stampa condividono la selezione server. Le esportazioni leggono tutti i risultati a blocchi e si interrompono se i dati cambiano fra le letture. Il riepilogo conserva nominativi e assegnazioni per camere e servizi; il suo costo server resta proporzionale all’evento.
## Completamento 3.26.1

La caparra è calcolata sul versato netto corrente e compare soltanto per il piano caparra/saldo; una caparra prevista zero non implica un versamento. Le colonne selezionate governano CSV e stampa dedicata. La ricerca usa parole in qualsiasi ordine sui nomi, sul codice e sui contatti del referente; Pagamenti permette di caricare ulteriori risultati. Il pannello evento conserva le tessere ed espone il collegamento diretto alle iscrizioni; il report movimenti esistente è raggiungibile senza duplicarne il registro.
## Disposizione contestuale della gestione iscrizioni

Prima della scelta evento compaiono solo titolo e selettori necessari; nessun titolo visibile Periodo. Dopo Evento: riepilogo e totali, comandi e collegamenti operativi, aggiornamento, nuova iscrizione evidenziata, ricerca e strumenti contestuali. Il rapporto annuale precede le colonne CSV/stampa. Camere, incassi e caparra dipendono dalla configurazione e dai dati dell’evento, non sono pannelli universali. La navigazione evidenzia Gestione iscrizioni quando attiva.
