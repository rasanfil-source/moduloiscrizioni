# Gestione eventi — architettura corrente

Versione 3.24.0, ambiente di prova con dati fittizi. Il portale WordPress autenticato è il punto operativo unico. Il gestore apre la stessa gestione dal portale, dal collegamento del foglio evento o dal menu di DB_MODULI.

WordPress mantiene autorizzazioni, iscrizioni e capienza; Workspace conserva il registro pagamenti, le correzioni operative e le proiezioni dei fogli. I pagamenti sono convalidati lato server, con identificativo persistente per i retry. Il riepilogo evento non moltiplica gli importi per il numero dei partecipanti.

Il gestore corregge nomi e dati aggiuntivi, assegna/rimuove camere, crea camere con capienza e cancella quelle vuote. Può annullare partecipanti tramite il servizio WordPress. Le nuove iscrizioni passano dal modulo dell’evento già esistente. Il ricalcolo dei prezzi e le modifiche alle opzioni economiche non vengono effettuati scrivendo celle.

I fogli evento sono consultabili e rigenerati dal centrale. Le righe locali non sono più ingressi: non esistono checkbox di acquisizione né merge delle celle. Il trigger conserva cursore e retry per recuperare aggiornamenti non completati.

Per il rilascio e il collaudo vedere [3.24.0](rilascio-3.24.0.md). Il contratto frontend è in [UX-CONTRACT.md](../UX-CONTRACT.md).
