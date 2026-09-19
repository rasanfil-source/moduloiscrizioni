# Eliminazione evento: batch e avanzamento senza ricariche

Apps Script elimina ora intervalli contigui in ordine decrescente con `deleteRows()`. Il limite resta 100 righe per richiesta e circa 7 secondi nel ciclo; letture iniziali, Drive e singole chiamate remote possono superare quel tempo. Restano invariati lock, identificativo idempotente, esclusione dei fogli condivisi e cancellazione delle iscrizioni solo dopo i dati dipendenti.

Il portale invia i passi a `wp_ajax_mi_delete_event`, con gli stessi controlli di permesso, nonce legato all'evento e conferme iniziali. Una sola richiesta alla volta; dopo una risposta parziale valida il passo successivo parte dopo due secondi. Errori applicativi, di rete o sessione interrompono la prosecuzione automatica e consentono una ripresa esplicita. La pagina si ricarica soltanto al completamento per mostrare eventuali avvisi e il foglio conservato. Senza JavaScript rimane il form POST con ripresa manuale.

Non si tratta di un worker in background né di polling di sola lettura: ogni chiamata AJAX fa avanzare la cancellazione e può attendere Google. Chiudere la pagina interrompe la prosecuzione automatica, ma il lavoro già completato rimane persistito. Non vengono introdotti cron con privilegi impliciti.

Il timeout della sola azione ELIMINA_DATI_EVENTO passa da 45 a 110 secondi, recependo la proposta del pacchetto allegato; LEGGI_MODIFICHE_FOGLIO resta a 45. I limiti PHP/proxy/hosting possono essere inferiori: il timeout remoto non li modifica.

Verifiche locali: test Apps Script per batch contigui e intervallati, idempotenza, errori Drive, fogli condivisi e avanzamento; test UI per doppio invio, risposta parziale, completamento ed errori; test PHP accesso/nonce; collaudo InnoDB con permessi, rollback, isolamento e ripresa. Asset minificati rigenerati.

Per ottenere entrambi i benefici bisogna aggiornare sia il plugin sia la distribuzione Apps Script con EliminazioneEvento.gs. Nessun evento reale è stato eliminato e non sono state esaminate le Esecuzioni Google. Prima del rilascio operativo resta il collaudo su un evento fittizio nell'ambiente WordPress/Google di prova, inclusa la ripresa dopo interruzione.
