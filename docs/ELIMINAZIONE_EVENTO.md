# Eliminazione evento

Il servizio Apps Script rimuove i dati in gruppi di righe contigue, in ordine decrescente. Ogni richiesta elabora al massimo 100 righe e limita il ciclo di lavoro per lasciare tempo alla risposta. La cancellazione resta idempotente e non modifica i fogli condivisi.

Il portale avanza la procedura tramite `wp_ajax_mi_delete_event`, applicando permessi, nonce associato all'evento e conferme iniziali. Le richieste procedono una alla volta. Una risposta valida può avviare il passaggio successivo; un errore applicativo, di rete o di sessione interrompe la sequenza e lascia disponibile la ripresa esplicita.

La pagina si aggiorna al termine per mostrare l'esito e lo stato del foglio conservato. Senza JavaScript resta disponibile il modulo POST con avanzamento manuale. Ogni chiamata remota può attendere la risposta Google; non viene creato un worker con privilegi impliciti.

Il timeout WordPress per `ELIMINA_DATI_EVENTO` è 110 secondi. `LEGGI_MODIFICHE_FOGLIO` usa 45 secondi. I limiti del server PHP, del proxy e dell'hosting restano indipendenti.
