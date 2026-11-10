# Rilascio 3.27.42 — scadenza del riepilogo invio in Messaggi

10 novembre 2026. Correzione WordPress; resta valido il sorgente MODULI AUTONOMO 3.27.41.

Il riepilogo dell'ultimo invio non resta più permanente in Messaggi/Comunicazioni: si nasconde dopo 30 minuti dalla prima rilevazione della conclusione, anche se contiene errori. Funziona anche con la pagina aperta e riaprendo un vecchio URL con mi_comm_job. Gli invii ancora in preparazione, in coda o sospesi restano visibili.

L'istante di conclusione rilevata è persistente e non viene rinnovato ricaricando la pagina. Per gli invii conclusi prima dell'aggiornamento il conteggio parte dalla prima visualizzazione con questa versione. Se una coda viene riaperta e poi conclusa, il riepilogo riceve una nuova scadenza.

Si nasconde soltanto il riepilogo: job, email, ricevute e dettagli degli errori restano consultabili nel registro. Nessun reinvio o rigenerazione automatica. L'esito “120 accettate, 7 errori” conserva il suo significato; la quota residua non indica che quelle sette email siano state accettate.

Installare dist/modulo-iscrizioni-3.27.42.zip; la variante -pubblico esclude la configurazione privata del saldo. Non occorre aggiornare Google.

Verifiche: regressioni PHP su scadenza, refresh, lavori storici, errori, ripresa della coda e permessi; regressioni JavaScript sulla scomparsa a pagina aperta e sugli invii in corso. Verifica standard del progetto, asset generati e controllo dei due archivi. Nessuna email reale inviata.
