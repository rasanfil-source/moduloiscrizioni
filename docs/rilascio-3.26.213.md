# Rilascio 3.26.213 — Correzioni dell'audit del 30 settembre

Aggiornamento del solo plugin WordPress. Include le modifiche locali della 3.26.212 al menu «Foglio». Nessuna modifica ad Apps Script e nessuna installazione sul sito eseguita.

## Correzioni

- **Caparre incoerenti:** il saldo pubblico interrompe ricerca, anteprima e conferma quando la caparra complessiva non coincide con le quote individuali. Mostra la richiesta di verifica alla segreteria, senza modificare importi, stato, servizi o audit. Le prenotazioni coerenti restano utilizzabili.
- **Copia di un evento annullato:** il duplicatore esclude anche il job di annullamento, preservando la configurazione dell'evento e creando nuovi identificativi di consenso. L'evento originale resta intatto.
- **Date personalizzate:** le domande di tipo data ammettono date passate e future valide. La correzione comprende le definizioni già pubblicate prive di una regola esplicita. Restano i controlli sulle date impossibili e i limiti specifici per nascita, rilascio e scadenza documenti. PHP e modulo JavaScript applicano le stesse regole.

Le correzioni non alterano automaticamente caparre storiche incoerenti né copie di eventi già create con il vecchio blocco: questi casi richiedono verifica dei dati esistenti. Non è stata rilevata la loro eventuale presenza sul sito.

## Verifica

`tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe` completato con successo: **392 test Node, 7 test degli asset**, verifica degli asset generati, sanitizzazione, lint PHP e tutte le suite PHP previste, comprese tre nuove regressioni.

Nuovi test:

- `wordpress-plugin/tests/public-balance-deposit-consistency.php`: blocco di ricerca, anteprima e scrittura su dati incoerenti, assenza di modifiche e conferma dopo una correzione esplicita della caparra nella fixture.
- `wordpress-plugin/tests/event-duplication-cancellation.php`: copia senza stati di annullamento, configurazione conservata, nuovi consensi e originale intatto.
- `wordpress-plugin/tests/custom-date-fields.php`: date personalizzate attuali e precedenti, date invalide e limiti dei campi anagrafici/documentali.
- `wordpress-plugin/tests/custom-date-fields.test.mjs`: esecuzione del renderer reale dei campi data per verificare i limiti HTML.

I nuovi test hanno riprodotto i difetti prima della correzione e sono passati dopo. Log della suite: `.tmp/fix-audit-2026-09-30-verify.log`; l'output PHP passato a `Out-Host` è disponibile nella sessione di esecuzione. Test con dati sintetici; nessun collaudo sui servizi remoti.

## Pacchetti

- `dist/modulo-iscrizioni-3.26.213.zip`: pacchetto locale per l'installazione, con la configurazione locale prevista dal progetto.
- `dist/modulo-iscrizioni-3.26.213-pubblico.zip`: pacchetto senza configurazione privata.
- Checksum SHA-256 nei file `.zip.sha256` corrispondenti.

Preparazione tramite `tools/package-3.26.213.ps1`, che controlla versione, asset e corrispondenza SHA-256 di ogni file dei pacchetti con i sorgenti.
