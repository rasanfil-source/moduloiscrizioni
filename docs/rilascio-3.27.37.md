# Modulo Iscrizioni 3.27.37

Rilascio del 9 novembre 2026. Origine degli annullamenti visibile nel dettaglio della coda email.

Nelle notifiche **PARTICIPANT_CANCEL_SECRETARIAT** e **PARTICIPANT_CANCEL_CONFIRMATION**, il dettaglio mostra una nuova sezione **Origine dell’annullamento**. Legge esclusivamente i dati gia conservati nella notifica: `cancellation_actor`, codice/ID prenotazione e ID della persona.

- `PARTICIPANT_LINK`: conferma tramite un collegamento personale valido. Il valore identifica il canale, non prova l'identita della persona che ha premuto il pulsante.
- `TRAVEL_LINK`: rinuncia dalla pagina degli spostamenti.
- `TRAVEL_DEADLINE`: cancellazione automatica per mancata assegnazione del passaggio entro il termine.
- `ADMIN`: operazione amministrativa. Altri valori, compreso il nome di un operatore, vengono mostrati come registrati.
- Dato assente: dichiarato non presente, senza ricostruzioni o attribuzioni presunte.

La sezione e disponibile anche per notifiche gia inviate, mantenendo gli stessi controlli di accesso della coda. Non introduce query aggiuntive, operazioni di ripristino, nuovi invii o modifiche alle iscrizioni. Non visualizza token personali. Nessun cambiamento al percorso di prova o ai collegamenti operativi.

Questa versione consente di verificare l'origine degli annullamenti segnalati; non stabilisce se fossero voluti e non li corregge automaticamente.

Installazione: aggiornare soltanto WordPress con `modulo-iscrizioni-3.27.37.zip` oppure la consueta variante `-pubblico`. Non serve aggiornare Google. Riaprire i dettagli delle notifiche interessate e leggere **Origine registrata**.

Verifiche superate: valori conservati, canali distinti, dati mancanti, escaping, assenza di token e azioni operative; percorso demo privo di scritture; verifica locale completa, 440 test Node e 7 test della cache generata, controlli PHP e sanitizzazione. Nessuna email reale inviata, nessuna iscrizione modificata e nessuna installazione sul sito. Gli ZIP precedenti, incluso 3.26.226, restano invariati.
