# Stampa della prenotazione

La stampa della scheda esclude intestazione «Gestione iscrizioni», selettori evento, comandi di aggiornamento e apertura foglio, messaggi operativi, pulsanti di navigazione, barra salva/stampa/annulla e pannello di modifica servizi. I nomi dei partecipanti nel riepilogo rimangono visibili come testo.

Spaziatura compatta e campi disposti su due colonne riducono l'altezza senza tagliare i dati. La prova browser con prenotazione sintetica di due persone rientra nell'area A4 con margini di 10 mm; non è garantita una sola facciata per prenotazioni numerose o note lunghe. La stampa dell'elenco generale conserva il proprio formato.

Verifica: `tools/test-individual-management-browser.cjs`, inclusi controlli di esclusione degli elementi operativi e altezza utile in modalità stampa. Modifica inclusa nello ZIP 3.26.155.
