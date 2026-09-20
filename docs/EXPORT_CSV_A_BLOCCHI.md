# Export CSV a blocchi

Entrambi gli export amministrativi leggono al massimo 500 righe per query. I pagamenti avanzano con un cursore su `(effective_at, id)` crescente. Le iscrizioni avanzano con `r.id` decrescente e `p.id` crescente, conservando anche le prenotazioni senza partecipanti.

Una transazione di sola lettura REPEATABLE READ con snapshot coerente mantiene allineati iscrizioni, partecipanti e movimenti InnoDB durante modifiche concorrenti. Non vengono usati lock di riga. Lo snapshot resta aperto durante la generazione: export molto grandi possono comunque trattenere versioni MVCC e raggiungere i limiti di esecuzione del server.

Il CSV viene preparato in un file temporaneo e trasmesso soltanto dopo il completamento e il COMMIT. Per le iscrizioni una prima passata salva le righe su disco e raccoglie le intestazioni dinamiche; una seconda passata locale genera il CSV. Le posizioni economiche sono lette in blocco nello stesso snapshot. Totale, primo versamento, versato e residuo compaiono soltanto sulla prima riga della prenotazione, anche quando questa attraversa un confine tra blocchi.

La memoria dei risultati SQL è limitata al blocco; l'insieme delle intestazioni dinamiche e le cache WordPress restano proporzionali ai rispettivi dati. Servono spazio temporaneo sufficiente e tabelle InnoDB. I file temporanei sono chiusi e rimossi al termine. Gli errori di lettura e scrittura durante la preparazione impediscono il download; un'interruzione di rete durante la trasmissione resta possibile.

Il test `wordpress-plugin/tests/csv-export-innodb.php` usa esclusivamente il database locale `mi_ledger_test`, porta 33317, e tabelle proprie. Verifica oltre mille righe, date uguali, prenotazioni divise tra blocchi, assenza di partecipanti, intestazioni tardive, saldi netti, protezione formule CSV, modifiche concorrenti, errori al secondo blocco, filtri ed export vuoti.

La query dell'elenco eventi nella pagina Pagamenti è stata inoltre ridotta a un solo `get_posts`, con lo stesso filtro di gruppo. Ricerca testuale, minificazione degli asset e invalidazione con `filemtime()` non cambiano in questo intervento.
