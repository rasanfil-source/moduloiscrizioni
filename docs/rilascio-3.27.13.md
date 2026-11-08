# Modulo Iscrizioni 3.27.13

Rilascio del 7 novembre 2026. La scelta di partecipare anche senza passaggio è esplicita e viene richiesta anche quando l’utente ha già selezionato un’auto. Le nuove richieste e offerte chiudono 24 ore prima del termine; nelle ultime 24 ore il conducente può ridurre o ritirare i posti, ma non attivarne di nuovi.

Chi perde un passaggio vicino al termine riceve un collegamento personale per scegliere come partecipare o annullare. La finestra di scelta dura fino a 24 ore dalla perdita del passaggio e non oltre l’inizio dell’evento. Le email di attesa portano alla gestione del viaggio e presentano separatamente l’annullamento. Le comunicazioni informative prima dell’evento includono chi attende un passaggio; i promemoria di pagamento continuano a escluderlo.

Gli aggiornamenti raggruppano i messaggi per prenotazione con collegamenti individuali. Le persone in lista d’attesa dell’evento non generano avvisi prematuri ai conducenti. Il registro conserva scelta, attore, conducente, posti e dettagli precedenti; `travel_booking` partecipa alla chiave anti-duplicazione. La migrazione aggiunge la scadenza della scelta dopo la perdita del passaggio. Le cancellazioni automatiche spiegano il motivo e indicano alla segreteria che gli eventuali rimborsi richiedono gestione manuale.

Verifica: suite completa `tools/verify.ps1`, test di integrazione del viaggio su MariaDB sintetico e verifica di sintassi del test browser. Il test browser Playwright non è stato eseguito perché la dipendenza non è installata.

Pacchetti `modulo-iscrizioni-3.27.13.zip` e variante `-pubblico`. Nessuna installazione in produzione; nessuna modifica Google richiesta. Archivi precedenti invariati.
