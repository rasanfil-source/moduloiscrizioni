# Versione 3.26.220

Corregge la modalita `NONE`: aggiunta/rimozione dei servizi e cambio sistemazione ricalcolano il dovuto. Solo `ZERO` identifica un evento totalmente gratuito. Il dettaglio mantiene disponibili i pagamenti per `NONE` e le email conservano le istruzioni economiche.

La riaccodatura manuale aggiorna esclusivamente `FAILED` e `TEST_FAILED` mediante una singola scrittura atomica, preservando modalita di prova e chiave della consegna. Il pannello distingue successo, stato non riaccodabile ed errore SQL; non propone il comando per `SENDING` e `TEST_SENDING`. La consegna rimane asincrona e le ricevute remote non vengono cancellate.

Include l'ottimizzazione frontend `defer` della 3.26.219. Non esegue correzioni retroattive degli importi gia registrati, invii reali o installazioni sul sito.

Verifiche: suite locale Node e PHP, regressioni aggiunta/rimozione servizi `NONE`/`ZERO`, istruzioni pagamento email, esiti della riaccodatura e verifica degli asset. La campagna InnoDB non e disponibile per assenza del servizio MariaDB locale; nessun collaudo del sito installato.

Pacchetto locale: `modulo-iscrizioni-3.26.220.zip`. Pacchetto GitHub: `modulo-iscrizioni-3.26.220-pubblico.zip`, senza configurazione privata. Ogni file degli archivi viene confrontato con il sorgente mediante SHA-256.
