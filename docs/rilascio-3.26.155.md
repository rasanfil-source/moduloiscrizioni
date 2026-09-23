# Rilascio 3.26.155 — scheda personale e stampa

Include le modifiche della [3.26.154](rilascio-3.26.154.md) e le successive rifiniture:

- «Vai a Elenco partecipanti» sopra e sotto la scheda, con bordo azzurrino.
- «Salva iscritto» visibile solo quando i dati personali differiscono dai valori caricati; ripristinando i valori iniziali il pulsante si nasconde. Dopo un salvataggio riuscito la scheda ricaricata costituisce il nuovo riferimento.
- Stampa compatta senza intestazione della gestione, navigazione, messaggi operativi e pannelli di modifica; nomi dei partecipanti conservati. Il caso sintetico di due partecipanti rientra nell'area utile A4, senza garantire una pagina per prenotazioni numerose o note lunghe. Dettagli in [stampa prenotazione](stampa-prenotazione.md).

## Installazione e GAS

Caricare `modulo-iscrizioni-3.26.155.zip` in WordPress e confermare la sostituzione. Questo ZIP locale conserva la configurazione privata e non va pubblicato. GitHub distribuisce soltanto `modulo-iscrizioni-3.26.155-pubblico.zip`, che la esclude.

GAS rimane al sorgente **3.26.154**, deployment **9**, nel progetto dal nome storico **MODULI AUTONOMO 3.26.149**: nessuna modifica funzionale GAS in questa release. I bundle Workspace 3.26.155 differiscono soltanto per il commento di versione; non occorre distribuirli. URL, proprietà e trigger restano invariati.

## Verifiche e limiti

Verifica generale con Node, asset generati, lint, suite PHP e sanitizzazione; prove browser su scheda individuale, modifiche/ripristino dei dati, stampa compatta e posizione/salvataggio della presenza. La 3.26.154 aveva inoltre superato 11 suite PHP/InnoDB e 6 suite browser integrate. Gli ZIP sono confrontati file per file con i sorgenti e accompagnati da SHA-256.

La segnalazione di errori HTTP 500 durante il salvataggio di altre pagine del sito resta **non diagnosticata**: mancano i log PHP/server. Questa release non dichiara di risolverla. È stata individuata come ipotesi da verificare la ripetizione dell'aggiornamento schema in caso di migrazione fallita; non è una causa accertata. Non sono state alterate configurazioni di produzione per tentare di mascherare l'errore.

L'installazione WordPress e il successivo controllo sul sito restano da eseguire.
