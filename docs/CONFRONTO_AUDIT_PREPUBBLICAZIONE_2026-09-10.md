# Confronto del nuovo audit con la situazione iniziale e il candidato 3.26.0

## Valutazione

Il nuovo audit è utile come controllo della copertura del progetto. Descrive prevalentemente il codice precedente agli interventi, non il candidato locale 3.26.0. Molte osservazioni sono già superate; alcune segnalano completamenti effettivamente ancora mancanti. Va quindi corretta la precedente conclusione secondo cui restava soltanto il collaudo distribuito.

Confronto effettuato sul sorgente attuale, sul precedente audit del 9 settembre e, per la ricerca Pagamenti, anche sul codice Git precedente alle modifiche locali (`ac0e333`). Non è una verifica del sito pubblicato. Il documento allegato è trattato come proposta da valutare: le indicazioni dell’utente su tessere, gratuità, moduli e Sheet restano prevalenti. In questo passaggio non sono modificati codice applicativo o pacchetti e non viene pubblicato nulla.

## Confronto operativo

| Tema del nuovo audit | Situazione iniziale | Candidato locale attuale | Decisione consigliata |
|---|---|---|---|
| Una persona per riga | Elenco centrato sul referente | Vista Partecipanti nel riepilogo; vista Prenotazioni alternativa. Iscrizioni conserva le tessere di prenotazione, ma mostra i nominativi | Già recepito nel percorso evento. Non trasformare automaticamente tutti gli accessi nello stesso elenco |
| Ricerca dei partecipanti | Solo referente in Iscrizioni e Pagamenti | Entrambe le query raggiungono i nomi dei partecipanti tramite `EXISTS`; la lista evento ricerca tutti i risultati sul server | Già risolta la lacuna sui nomi, non tutta l’uniformità della ricerca |
| Ricerca per contatti | Comportamenti diversi | Iscrizioni cerca email/telefono del referente; Pagamenti cerca nomi e codice; i contatti individuali eventuali in `extra_json` non sono ricercati | Utile uniformare i campi e distinguere sempre contatto personale e del referente. Cercare solo nei campi configurati come contatto, non in tutto il JSON |
| Richieste particolari | Assenti dalla scheda operativa | Visibili, con verifica auditata e filtri presenti/da verificare/verificate; un testo cambiato invalida la verifica | Già recepito. Il divieto di scrittura dal foglio non implica più assenza di lettura nel portale |
| Caparra e saldo | Prima quota memorizzata, non esposta operativamente | Il residuo corrente è corretto, ma scheda e lista non distinguono ancora caparra mancante da caparra coperta con saldo residuo | Da integrare prima della pubblicazione del progetto completo |
| Rimborso dedotto da `paid > total` | Seguito amministrativo manuale | Variazioni di servizi, rettifiche motivate e movimenti di rimborso separati e auditati | Non recepire il filtro automatico proposto: contrasta con le indicazioni dell’utente e non determina da solo quanto restituire |
| Filtri attesa e richieste | Quattro filtri di criticità | Stato, richieste, scadenze offerte, camera e servizi combinabili | Già ampiamente recepito; resta il filtro caparra. Cruscotto multi-evento facoltativo, non necessario al rilascio |
| Un solo motore di ricerca | Tre implementazioni | Le query Iscrizioni/Pagamenti e la selezione della lista evento restano distinte; Pagamenti limita ancora a 30 risultati | Consiglio utile: condividere regole di corrispondenza e completare la raggiungibilità dei risultati in Pagamenti, mantenendo i diversi compiti delle schermate |
| Contesto dalla tessera evento | Nessun collegamento operativo diretto nel pannello evento | La scheda prenotazione e il ritorno alla ricerca sono condivisi; `event_management_card()` non espone ancora un collegamento alla gestione iscrizioni e la barra generale non conserva sempre l’evento | Da completare con un collegamento nel pannello già esistente, senza cambiare il sistema di tessere |
| Report movimenti nel portale | Report e CSV movimenti in wp-admin | CSV partecipanti/prenotazioni disponibile nel portale; report movimenti per date/fonte/tipo ancora in wp-admin | Utile rendere raggiungibile il report esistente dal contesto operativo, riusando autorizzazioni e filtri. Non serve ricostruirlo |
| Occupanti per camera | Solo capacità e occupazione numerica | Elenco nominativo, inventario evento indipendente dalle prenotazioni, scambi atomici | Già recepito |
| Conteggi servizi | Assenti nel riepilogo | Conteggi opzioni individuali e d’ordine distinti, con filtri per servizio | Già recepito per le opzioni acquistate. Le assegnazioni operative nei campi liberi non sono automaticamente lo stesso dato |
| Colonne di stampa | Stampa della vista | Stampa completa di tutti i risultati, ma le caselle di selezione colonne valgono solo per il CSV | Consiglio ancora valido: far rispettare anche alla stampa le colonne scelte e isolare la lista dai pannelli accessori |
| CSV dal portale | Assente | CSV completo e filtrato, con campi dinamici e importi una volta per prenotazione | Già recepito; non equivale al CSV analitico dei singoli movimenti |
| Contatore sulla tessera | Conteggi prenotazioni calcolati per operazioni di gestione | Ancora nessun nuovo contatore operativo nel pannello evento | Secondario. Se aggiunto, separare persone ammesse, prenotazioni e attesa; `active_count` conta prenotazioni, non persone |
| Endpoint annullamento ordine | Registrato senza comando UI trovato | Invariato; contiene comunque controlli di autorizzazione e nonce | Pulizia successiva, previa verifica degli utilizzatori. Non aggiungere un annullamento collettivo solo per giustificare la rotta |
| Nomi delle sezioni | Gestisci eventi / Riepilogo e gestione | Ambiguità ancora presente | Utile rinominare solo l’accesso operativo in «Gestione iscrizioni», conservando «Gestisci eventi» e le tessere approvate |
| Attesa e importi zero | Possibile ambiguità economica | Stato attesa visibile e escluso dagli incassi attesi; tabella Prenotazioni mantiene colonne monetarie numeriche | Rifinitura utile: «Pagamento non richiesto in questo stato», senza nascondere eventuali movimenti storici |

## Le integrazioni che raccomando

1. **Caparra leggibile e filtrabile.** Solo per `DEPOSIT_BALANCE`: caparra prevista, quota ancora necessaria a coprirla e residuo complessivo. «Caparra coperta, saldo da completare» è un’informazione derivata dal versato netto corrente, non un nuovo stato della prenotazione. Una prima quota uguale a zero non deve essere descritta come un versamento effettuato. Escludere attesa e prenotazioni chiuse dai filtri di incasso.
2. **Percorso diretto dall’evento.** Aggiungere nel pannello esistente un comando «Gestisci iscrizioni di questo evento» e mantenere l’evento nei passaggi pertinenti. Il collegamento deve restare disponibile anche nello storico, utile per controllare disdette e restituzioni.
3. **Stampa con colonne scelte.** Riutilizzare la selezione già presente per il CSV; stampare una lista dedicata con evento, filtri e numero di risultati. La stampa completa è già realizzata: manca questa personalizzazione.
4. **Ricerca coerente.** Allineare nomi, ordine nome/cognome, codice e contatti del referente tra gli accessi; mostrare quale partecipante ha prodotto il risultato. Completare la ricerca Pagamenti oltre i primi 30 risultati. Valutare i contatti individuali soltanto sulla base dello schema reale dell’evento.
5. **Accesso al report movimenti esistente.** Collegare il report per periodo/fonte/tipo dal portale conservando l’evento selezionato. Distinguere chiaramente lista delle prenotazioni e registro dei movimenti.

Naming e testo per le prenotazioni in attesa possono accompagnare queste integrazioni. Il contatore nel pannello evento è facoltativo; non deve comportare una riprogettazione delle tessere.

## Proposte da non applicare alla lettera

- Il confronto `paid > total` può indicare un importo da esaminare dopo una rettifica concordata, ma non stabilisce da solo una restituzione dovuta. Una disdetta può richiedere una decisione manuale sul dovuto anche senza produrre immediatamente quella disuguaglianza. Restano valide le istruzioni: restituzioni per disdetta o qualsiasi variazione concordata, nessun rimborso automatico o filtro generico di eccedenze.
- Una `JOIN` diretta ai partecipanti può duplicare le prenotazioni e gli importi. L’attuale `EXISTS` è appropriato per trovare una prenotazione tramite uno dei suoi partecipanti; non va sostituito solo per aderire alla formulazione dell’audit.
- Il numero di prenotazioni non è il numero di persone. La proposta di esporre `active_count` come «iscritti» va corretta, non copiata.
- Il report economico non va ricostruito se già esiste. La stessa cautela vale per scheda condivisa, pagamenti rapidi e strumenti Workspace.
- Non eliminare schermate o endpoint soltanto perché sembrano ridondanti. Una rotta senza pulsante trovato non dimostra da sola una vulnerabilità, né prova che non abbia utilizzatori esterni.

## Evidenze nel sorgente

- [Portale](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal.php): `registrations_view()` ricerca con `EXISTS` e pagina con `LIMIT 31`; `event_management_card()` non contiene ancora il collegamento operativo; la barra di navigazione genera accessi generici.
- [Pagamenti](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-payments.php): `search()` comprende i nomi individuali, ma non i contatti e resta limitata a 30 risultati.
- [Ledger](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-payment-ledger.php): `detail()` restituisce totale/versato/residuo/movimenti; `save()` usa `initial_due_cents` per riconciliare lo stato. L’esposizione gestionale della caparra resta da aggiungere.
- [Servizio gestione](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php): richieste, presenze, servizi e rettifiche; [selezione liste](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-list.php): filtri e pagine server.
- [Interfaccia gestione](../wordpress-plugin/modulo-iscrizioni/assets/portal-management.js): occupanti, servizi e filtri; `data-export-column` è letto dall’esportazione CSV, mentre `printList` carica tutte le righe e ristampa la tabella ordinaria.
- [Amministrazione](../wordpress-plugin/modulo-iscrizioni/includes/class-mi-admin.php): `payments_page()` ed `export_payments()` conservano report e CSV dei movimenti; `cancel_registration()` è ancora registrato e protetto.

La valutazione resta statica: i test precedenti attestano i casi verificati, non la completezza di tutti i requisiti dell’audit. Il candidato 3.26.0 rimane non pubblicato; prima del rilascio consiglio le integrazioni sopra e poi il collaudo WordPress/Workspace già previsto.
