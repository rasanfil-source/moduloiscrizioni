# Modulo Iscrizioni 3.27.68

15 novembre 2026. Elenco compatto con impaginazione adattiva e campanella senza didascalie.

## Elenco compatto

Stampa ed Excel scelgono automaticamente da cinque a dieci colonne e la dimensione del carattere in base al numero di persone e alla lunghezza effettiva dei nominativi. Fino a 200 persone il rapporto viene adattato a una sola pagina A4 orizzontale, con margini di 10 mm. Mantiene titolo evento, ordine alfabetico da sinistra a destra, cognome e nome per i cognomi duplicati, testo completo e celle alternate bianche/grigio chiaro. Il calcolo tiene conto delle righe di testo, anche per cognomi composti e titoli estesi.

Con più di 200 persone continua sui fogli necessari, senza ridurre ulteriormente tutto l'elenco in proporzione alla dimensione dell'evento. Il titolo viene ripetuto. La stampa esclude anche lo spazio occupato dai contenuti esterni al rapporto, evitando pagine bianche. Alla chiusura della stampa la pagina del portale viene ripristinata.

L'Excel usa lo stesso numero di colonne, carattere e altezze delle righe; fino a 200 persone imposta anche l'adattamento a una pagina in larghezza e altezza. Filtri, caricamento completo e controlli delle modifiche concorrenti rimangono operativi.

## Campanella

Nascoste le didascalie accanto alla campanella su desktop e mobile. SVG, dimensioni dell'icona, colori, sfondi, bordi, barra diagonale, stati attivo/disattivo/bloccato e comportamento del comando sono invariati. Rimangono descrizione accessibile, tooltip e messaggi di esito.

## Distribuzione e verifica

Nuovi ZIP locale e pubblico 3.27.68, checksum SHA-256 e sorgente MODULI AUTONOMO invariato. Nessun nuovo deployment Google necessario; installazione WordPress a cura del gestore. Conservati gli ultimi cinque rilasci 3.27.64–3.27.68.

Verifiche: suite Node/PHP locale, confronto degli asset minificati, browser su sorgenti e minificati, PDF effettivi con 1, 50, 100, 150 e 200 persone in una pagina e 401 persone in due pagine, caso di 200 cognomi lunghi con titolo esteso, controllo visivo delle pagine renderizzate e presenza di tutti i record. Verifica dei file Excel tramite lettore indipendente. Confronto della campanella con la 3.27.67 nei diversi stati su desktop e mobile. Tutte le prove usano dati sintetici; nessuna richiesta di autorizzazione notifiche reale o iscrizione a servizi push reali.
