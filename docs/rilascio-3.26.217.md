# Versione 3.26.217

Corregge i tre difetti documentati nell'audit della 3.26.216 del 2 ottobre 2026.

- Il filtro dei pagamenti non classifica più come saldate le persone con quote o pagamenti non ricostruibili. Il nuovo indicatore `totals_known` distingue questi casi dai saldi noti con sole caparre incoerenti. Correzione condivisa dai filtri PHP e JavaScript.
- La lettura delle pagine verifica la versione dei dati prima e dopo l'elaborazione, anche per cache e filtri avanzati. Se cambia, restituisce un errore esplicito e invita a riprovare.
- Gli errori della query COUNT vengono controllati prima della SELECT successiva, per entrambe le viste. La richiesta restituisce un errore invece di un totale zero apparentemente valido.

Test di regressione aggiunti alle suite esistenti: dati economici sconosciuti, saldo noto con caparra incoerente, modifiche durante la lettura nelle due viste e nei percorsi SQL/scansione, errore COUNT e successivo recupero. Test JavaScript eseguito sul filtro effettivamente usato dal portale.

Verifica generale completata: 393 test Node, 7 verifiche degli asset, suite PHP del progetto e lint PHP. Il nuovo test JavaScript aggiunto successivamente è verificato separatamente insieme alla relativa suite. Asset minificati rigenerati. Verifiche locali con database simulato; nessuna installazione o prova sul sito di produzione.

Aggiornamento solo del plugin WordPress. Apps Script non modificato; nessuna migrazione del database richiesta. Il pacchetto pubblico esclude la configurazione privata. L'installazione sul sito resta da eseguire.
