# Rilascio 3.26.123

La coda operativa non conteneva la notifica di pubblicazione dell'evento già pubblicato. La 3.26.122 aveva reso visibili le email senza iscrizione associata, ma non recuperava una notifica assente.

- La transizione a pubblicato programma un recupero persistente prima dei successivi hook di salvataggio. Se la preparazione fallisce, conserva l'errore e riprova fino a tre volte. Se l'evento non è più pubblicato, non prepara l'avviso.
- In Coda email gli amministratori possono recuperare la notifica alla segreteria per un evento pubblicato accessibile. Si riutilizzano modello, destinatario centrale, modalità corrente e chiave di deduplicazione esistenti. Nessuna migrazione automatica degli eventi storici.
- Il messaggio di esito distingue una nuova notifica da una già presente e mostra gli errori di preparazione.

Verifiche: 286 test Node superati; test PHP su database locale per pubblicazione interrotta, errore di accodamento, recupero, deduplicazione e mancato recupero di eventi in bozza; test di trasporto simulato operativo per creazione e annullamento senza prefisso PROVA. Lint PHP superato. ZIP verificato: 74 file identici ai sorgenti.

Pubblicazione: aggiornamento WordPress da 3.26.122 a 3.26.123 confermato dall'interfaccia il 15 settembre 2026. Recuperata la notifica mancante dell'evento selezionato tramite il nuovo comando; creata la riga 185 EVENT_MANAGER_READY in modalità OPERATIVO. La causa storica dell'omissione non è dimostrabile dalla sola coda; il recupero protegge dalle interruzioni future senza attribuire una causa non verificata.

Esito operativo verificato: riga 185 SENT, un tentativo, nessun errore, inviata il 15 settembre 2026 alle 12:09:35 UTC. Oggetto conservato: «Congratulazioni! Il tuo evento è stato pubblicato.», senza prefisso PROVA. Lo stato conferma la spedizione tramite il servizio, non la lettura nella casella del destinatario.
