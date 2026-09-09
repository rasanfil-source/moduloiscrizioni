# Contratto della gestione web

## Contesto e decisioni

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

- Ricerca della gestione locale al riepilogo, senza trasmettere nomi nei parametri URL; pagine di 30 righe con «Mostra altre 30». I codici di evento e ordine identificano il contesto del collegamento. I dati personali e le bozze restano in memoria, senza localStorage.
- Il web mostra soltanto un modulo pagamenti. La scheda prenotazione vi conduce con il codice già cercato. Totali e storico sono letti dal medesimo registro centrale.
- I salvataggi attendono il server. Il pulsante impedisce doppie attivazioni. Il retry riusa l'identificativo e il contenuto; un identificativo con dati diversi è respinto. Gli importi sono in centesimi.
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
