# Architettura operativa: WordPress e Google Sheets

## Regola generale

WordPress configura e pubblica il modulo, raccoglie l'iscrizione iniziale e la consegna a Google Sheets. Dopo la consegna, le attività della segreteria si svolgono in Sheets: modifiche, servizi, camere, pasti, trasporti, rinunce, pagamenti, rimborsi, note e integrazioni successive.

WordPress conserva soltanto quanto serve alla raccolta pubblica, alla visualizzazione dello stato, ai report e alle comunicazioni. Non deve diventare il gestionale quotidiano della segreteria.

## Documenti personali

Il sistema non chiede e non raccoglie mai fotografie, scansioni o file dei documenti di identità.

Quando un'iniziativa richiede dati del documento, sono ammessi esclusivamente campi testuali strutturati:

- tipo di documento;
- numero;
- Paese di rilascio;
- data di scadenza.

Se questi dati sono richiesti nel modulo iniziale, transitano temporaneamente da WordPress per essere consegnati a Sheets. Dopo la conferma della consegna vengono rimossi dai dati del partecipante conservati in WordPress. Se vengono acquisiti in un secondo momento, la segreteria li inserisce direttamente in Sheets.

Non aggiungere campi di caricamento file, collegamenti a servizi di upload o istruzioni che invitino a inviare documenti via email.

## Console della segreteria

Il foglio principale offre due percorsi guidati:

- creazione di una nuova iniziativa e del relativo foglio operativo;
- aggiornamento di un'iscrizione già raccolta.

Ogni operazione è registrata in modo append-only nel foglio `Operazioni segreteria`; `Stato operativo` presenta invece la situazione corrente. I fogli dedicati alle iniziative sono strumenti operativi e non sostituiscono il modulo pubblico WordPress.
