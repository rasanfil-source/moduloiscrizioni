# 3.26.180 — ordinamento iscritti per contesto

In Iscrizioni, selezionando «Tutti gli eventi», gli iscritti sono ordinati per data e ora della prenotazione dalla più recente alla meno recente. A parità di data e ora, l'ID della prenotazione mantiene un ordine deterministico; le persone della stessa prenotazione restano raggruppate nel proprio ordine di inserimento. L'ordinamento viene applicato prima della paginazione e vale anche con ricerca e filtri.

Selezionando un singolo evento, l'ordine iniziale resta alfabetico per cognome e poi nome, con i comandi di ordinamento manuale già disponibili.

Verificati i test PHP della ricerca cross-evento, della paginazione SQL e della selezione dell'elenco; controllo di sintassi PHP e degli asset. I pacchetti ZIP vengono verificati file per file e accompagnati da checksum SHA-256.

Nessuna modifica a Workspace/Google Apps Script. Lo ZIP locale conserva la configurazione privata, mentre la release GitHub contiene solo lo ZIP pubblico. Il caricamento sul sito resta a cura dell'utente.
