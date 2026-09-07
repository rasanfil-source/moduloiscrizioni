# Rilascio 3.23.24

## Modifiche

- Lista d'attesa con messaggio dedicato, proposta di posto valida per 48 ore (configurabile da 1 a 168), accettazione o rinuncia tramite collegamento personale. Alla scadenza il posto viene rilasciato dal controllo periodico WordPress esistente.
- Limite di persone per prenotazione nel passaggio 4: una persona oppure più persone, fino al massimo configurato (20 al massimo).
- Le date incoerenti bloccano l'avanzamento nel modulo di creazione, riportando al campo da correggere senza perdere i valori inseriti.
- Tessere evento: stato dell'evento distinto da iscrizioni aperte, future, chiuse o lista d'attesa.
- Messaggi finali di iscrizione più leggibili, senza codice tecnico quando non serve.

## Compatibilità e installazione

Sostituire il plugin WordPress con lo ZIP 3.23.24. L'aggiornamento dello schema aggiunge i campi delle proposte in lista d'attesa. Non cambiare le impostazioni di spedizione e non attivare timer Apps Script.

Non è necessario distribuire Apps Script: durante una proposta non ancora accettata, la replica Workspace conserva lo stato compatibile WAITLISTED. Il dettaglio «Posto proposto» e la sua scadenza rimangono in WordPress. Le funzioni email rispettano le impostazioni operative già esistenti; questo rilascio non autorizza prove di invio.

## Verifiche locali

- 161 test Node superati.
- Sintassi JavaScript verificata per public.js e portal.js.
- Tutti i 21 file PHP analizzati con php-parser in modalità PHP 7.4, senza errori di sintassi. Non equivale a un collaudo runtime PHP/WordPress.
- Controllo sanitizzazione e git diff --check superati.

Il collaudo dal vivo della versione installata resta da svolgere. Nessuna iscrizione, email, modifica dei dati reali o attivazione timer eseguita per queste verifiche. Il collaudo concorrente su 300 utenti resta escluso in assenza di staging.
