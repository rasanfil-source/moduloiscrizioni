# Pullman aggiunto dopo l'apertura delle iscrizioni

Collaudo locale del 13 novembre 2026: **superato**, senza modifiche al codice operativo del plugin.

## Requisito di riferimento

Il pullman all'apertura delle iscrizioni non esiste nel catalogo: nessuna tratta, nessuna opzione a zero e nessuna scelta preliminare richiesta al partecipante. Solo dopo, anche in base al numero degli iscritti, l'organizzatore definisce tratte e quote e le aggiunge allo stesso evento. La pagina pubblica del saldo deve consentire agli iscritti precedenti di selezionarle e confermare il dovuto effettivo, conservando alloggio, servizi originari e incassi.

Questo è il flusso richiesto dall'utente, indipendentemente dalla posizione dei campi nel wizard. I riferimenti funzionali originari sono `GAS.txt`, `HTM.txt` e `saldo_cammino.html` nella cartella sorella `ISCRIZIONI`; sono stati consultati senza modificarli. Non sostituire questo percorso pubblico con una rettifica manuale della segreteria.

## Scenario verificato

| Passaggio | Esito atteso e verificato |
|---|---|
| Iscrizione già presente, senza pullman | Quota base 300 €, alloggio 200 €, colazione 20 €, assicurazione 30 €: totale 550 € |
| Caparra registrata | Un incasso di 150 €, saldo iniziale 400 € |
| Modifica dello stesso evento pubblicato | Quattro nuove tratte: Roma–Fiumicino 10 €, Porto–Tui 15 €, Santiago–A Coruña 15 €, Fiumicino–Roma 10 € |
| Consultazione dell'iscritto precedente | Compaiono tutte e quattro le nuove tratte, assenti dalle condizioni storiche |
| Scelta delle prime due tratte | Totale 575 €, versato 150 €, saldo 425 €, nessuna caparra aggiuntiva (piano con caparra fissa) |
| Annullamento della conferma | Nessuna scrittura del nuovo dovuto |
| Conferma | Due tratte memorizzate; servizi originari e incasso conservati |
| Riepilogo email | Testo e HTML con tratte e saldo 425 €, archiviati per il recapito scelto dal richiedente |
| Replica Google | Revisione accodata e proiezione con totale 575 €, versato 150 €, saldo 425 €, due tratte personali e catalogo corrente di quattro tratte |
| Richiesta ripetuta | Nessun doppio addebito e nessun secondo riepilogo per la stessa richiesta |
| Cambio delle tratte | Le nuove scelte sostituiscono le precedenti senza sommare due volte i supplementi |
| Rinuncia a tutte le tratte | Ritorno al totale 550 € e al saldo 400 € |
| Prezzo cambiato fra preventivo e conferma | Conferma respinta senza applicare un importo diverso da quello mostrato |
| Due persone nella stessa prenotazione | Scelte distinte, totale 1.150 €, versato 300 €, saldo 850 € |

## Prove ripetibili

- `wordpress-plugin/tests/event-late-bus-save.php`: due salvataggi sullo stesso evento pubblicato attraverso il gestore applicativo del wizard. La fixture intercetta il salvataggio del catalogo: non esegue pubblicazione o creazione di fogli reali.
- `wordpress-plugin/tests/public-balance-late-bus.php`: calcolo, conferma, conservazione degli incassi, ricevuta email, richiesta ripetuta, successive modifiche, conflitto tariffe e due persone. Usa le classi applicative PHP e un adattatore di archivio sintetico.
- `wordpress-plugin/tests/late-bus-projection.php`: costruzione della proiezione Google dalle scelte e dagli importi confermati usando il generatore applicativo.
- `tools/test-late-bus-balance-browser.cjs`: Edge headless alle larghezze 390 e 1280 px, pagina saldo originale e API PHP applicative. Le tratte sono quelle prodotte dal test del wizard. Verifica stato iniziale senza pullman, selezione, importi, annullamento/conferma, riepilogo archiviato, importo delle istruzioni di pagamento e riapertura con scelte persistenti.

I tre test PHP sono inclusi in `tools/verify.ps1`. La verifica locale completa è terminata con successo; anche il collaudo browser è passato. Screenshot e risultati JSON sono in `output/collaudo-pullman-successivo`.

## Limiti del collaudo

Le iscrizioni iniziali e la caparra sono predisposte come dati sintetici già registrati: questo test mirato non ripercorre il modulo di prima iscrizione né un bonifico bancario. Non sono state inviate email reali, eseguiti pagamenti o scritti fogli Google online. Sono stati controllati l'archiviazione del messaggio, l'accodamento e il contenuto della proiezione destinata a MODULI AUTONOMO. Non è una certificazione del deployment attualmente installato sul sito.

Il caso economico usa una caparra **fissa**. Le caparre percentuali seguono il ricalcolo già previsto dal sistema e non vanno descritte come necessariamente immutate dopo un aumento della quota.

Nessuna nuova distribuzione necessaria: sono stati aggiunti test e documentazione, senza cambiare sorgenti operativi, versioni o ZIP.
