# Modulo Iscrizioni 3.26.279

Rilascio del 6 novembre 2026. Introduce l’organizzazione facoltativa degli spostamenti per tutti i gruppi, anche negli eventi senza quote.

## Funzionamento

- Nella creazione dell’evento, in Servizi e quote, è disponibile **Organizza gli spostamenti dei partecipanti**: indicazioni di viaggio, contributo fisso A/R per persona (anche zero) oppure stabilito dal conducente, termine delle assegnazioni.
- La modalità economica è denominata **Quote in base ai servizi scelti**.
- Ogni partecipante sceglie viaggio autonomo, mezzi pubblici, auto con 0–8 posti offerti oppure ricerca di un passaggio. I posti offerti escludono conducente e accompagnatori già previsti.
- Chi offre posti indica partenza e orario; nella modalità libera indica anche il contributo. Chi cerca un posto vede disponibilità e contributi correnti, verificati nuovamente al salvataggio.
- **Il contributo A/R è versato direttamente al conducente**: non viene aggiunto a quote, caparre, saldo o registro degli incassi dell’evento.
- La lista d’attesa auto richiede una scelta esplicita e l’email del referente. Il partecipante indica se partecipa solo con un passaggio oppure si organizzerà comunque.
- L’abbinamento automatico segue l’ordine delle richieste compatibili con la località: per esempio, «Roma» può ricevere una proposta da «Roma, Piazza Esempio». Il punto e l’orario precisi compaiono nella proposta prima dell’accettazione.
- Ogni proposta riserva un posto per **24 ore complete** dalla sua emissione e viene notificata attraverso la coda email esistente. L’ultima proposta automatica parte almeno 24 ore prima del termine delle assegnazioni. La scelta diretta durante l’iscrizione è possibile fino al termine finale.
- Rifiuto o scadenza liberano il posto per il successivo. Il rientro in lista è esplicito e riparte dal fondo. Chi si organizza diversamente può comunicarlo dal collegamento personale.
- La prenotazione con un passaggio indispensabile ancora mancante riserva i posti dell’evento e resta **In attesa di passaggio**, senza richieste o incassi di pagamento. Con più persone nella stessa prenotazione, il pagamento si attiva quando risultano assegnati tutti i passaggi indispensabili ancora richiesti.
- Al termine finale vengono annullate le sole partecipazioni subordinate a un passaggio rimasto indisponibile. Le altre persone della prenotazione conservano la partecipazione.
- La lista d’attesa dell’evento resta distinta: i passaggi vengono assegnati soltanto dopo l’ammissione all’evento. Le auto dei conducenti ancora in attesa di ammissione non sono pubblicate come disponibili.
- Il ritiro dei posti o l’annullamento del conducente avvisa i passeggeri e li riporta in attesa. Una partecipazione subordinata al passaggio torna sospesa; eventuali somme già versate restano nel registro e gli eventuali rimborsi si gestiscono separatamente.
- Il collegamento personale permette di consultare il viaggio, rispondere alle proposte e ritirare i posti. I contatti sono visibili alle persone assegnate al viaggio; l’elenco pubblico dei posti non espone nomi, email o collegamenti personali.
- La scheda evento contiene il riepilogo degli spostamenti e i collegamenti di gestione per la segreteria. Condizioni economiche e termine dei passaggi sono conservati quando esistono già viaggi registrati.

## Installazione

1. Aggiornare il plugin con uno dei due ZIP del rilascio. La nuova tabella dei passaggi viene creata automaticamente, senza modificare le iscrizioni esistenti.
2. Nel solo progetto **MODULI AUTONOMO**, aggiornare `Codice.gs` con `dist/Codice-Workspace-Progetto-3.26.279.gs` e aggiornare il deployment Web App.
3. Attivare gli spostamenti negli eventi interessati. Gli altri eventi mantengono il normale flusso d’iscrizione.

I fogli evento espongono modalità di viaggio, posti offerti, partenza, orario, stato, conducente, dipendenza dal passaggio, contributo diretto e scadenza della proposta. La configurazione prevede queste colonne anche prima della prima iscrizione; le viste personalizzate già salvate conservano le proprie scelte. Le modifiche ai passaggi si eseguono dai collegamenti di gestione nel portale.

Scorrimento e chiusura finale usano il cron WordPress già previsto ogni cinque minuti, la cui esecuzione dipende dalla configurazione del sito. L’accettazione di una proposta scaduta viene comunque respinta immediatamente. Le comunicazioni rispettano la modalità email configurata: anteprima, prova o operativa.

## Verifiche

- Suite Node e PHP del progetto, sintassi PHP, manifest e asset generati, sanitizzazione.
- Test mirati su MariaDB locale con dati sintetici: registrazione reale, contributi esclusi dal totale evento, posto riservato, finestra di 24 ore, token non valido, accettazione e attivazione pagamento, ritiro conducente, scadenza e scorrimento, rifiuto e rientro, chiusura finale e rollback.
- Due processi PHP concorrenti sull’ultimo posto: una sola assegnazione e nessuna iscrizione parziale.
- Browser desktop/mobile su sorgenti e asset minificati: campi condizionali, zero posti, disponibilità protette da HTML, consenso all’attesa, conservazione delle scelte, invio completo e soppressione delle richieste di pagamento durante l’attesa.
- Fogli evento gratuiti con spostamenti: colonne dedicate e stato corretto, senza introdurre colonne di pagamento per i contributi ai conducenti.

Non eseguite installazioni o prove sul sito e sui servizi Google in produzione.

## Distribuzione

- `dist/modulo-iscrizioni-3.26.279.zip`: pacchetto locale.
- `dist/modulo-iscrizioni-3.26.279-pubblico.zip`: pacchetto pubblico senza `public-balance-config.json`.
- `dist/Codice-Workspace-Progetto-3.26.279.gs`: sorgente unico per MODULI AUTONOMO.

Gli archivi precedenti restano invariati, incluso il rilascio 3.26.226. I nuovi archivi vengono verificati rispetto ai sorgenti e accompagnati dai checksum SHA-256.
