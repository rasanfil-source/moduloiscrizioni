# Modulo Iscrizioni 3.27.75

17 novembre 2026. Nella scheda Iscrizioni, se nessun partecipante attivo ha dati mancanti e l’evento ha i passaggi auto attivi, la casella «Dati mancanti» è sostituita da «Passaggi auto»: persone in attesa, posti disponibili e posti già assegnati. Le persone con proposta ancora da accettare restano comprese nell’attesa; i posti riservati da proposte valide sono indicati separatamente ed esclusi dai disponibili. Non viene duplicata la vecchia casella di attesa dei passaggi.

Se emergono dati mancanti torna la casella originale. Senza passaggi auto il comportamento resta invariato. Un errore di lettura mostra «Riepilogo non disponibile» senza impedire l’apertura delle iscrizioni e senza presentare zeri come dati validi.

Il conteggio è condizionato al riepilogo del singolo evento selezionato, alle due condizioni sopra e ai permessi esistenti. Una sola query aggregata, con indici evento e conducente, restituisce quattro numeri senza caricare nominativi, contatti o token. Non aggiunge richieste HTTP, chiamate Google, operazioni di assegnazione o letture durante l’apertura generale del portale, l’elenco trasversale, le pagine successive o i pannelli differiti. Le proposte scadute non occupano posti; partecipazioni annullate, rilasciate o ancora in lista d’attesa evento non offrono disponibilità.

Verifiche: suite Node/PHP, condizioni di attivazione e fallback, test SQL su MariaDB per assegnazioni/proposte/scadenze/annullamenti/eventi distinti e test browser su desktop/mobile, con confronto del numero di richieste e controllo visivo. Dati sintetici, nessun invio reale.

Nuovi ZIP locale e pubblico con checksum SHA-256. MODULI AUTONOMO invariato: nessun aggiornamento GAS necessario. Installazione WordPress a cura del gestore. Conservati localmente i rilasci 3.27.71–3.27.75.
