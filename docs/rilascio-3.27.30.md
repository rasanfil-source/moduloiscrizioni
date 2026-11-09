# Modulo Iscrizioni 3.27.30

Rilascio del 9 novembre 2026. Correzione urgente del caricamento iniziale della scheda Messaggi.

La lettura degli eventi eseguita prima di mostrare Messaggi combinava numerosi JOIN su postmeta attraverso condizioni OR e NOT EXISTS. La nuova lettura mantiene il filtro SQL degli eventi autorizzati, precarica i metadati in blocco e distingue gli eventi correnti e passati con la classificazione gia usata dalla stessa scheda.

Unica modifica funzionale: caricamento dell'elenco eventi interno a Messaggi, compreso il richiamo dello storico. Composizione, tipi di messaggio, conferme, invio delle prove, invii massivi, code email e sorgenti Google invariati. Nessuna modifica alle colonne o agli orari di iscrizione e cancellazione.

Installazione: aggiornare soltanto WordPress con modulo-iscrizioni-3.27.30.zip oppure con la variante -pubblico abitualmente usata. Non occorre aggiornare MODULI AUTONOMO per questa correzione. Il rilascio conserva le funzionalita della 3.27.29 e ne mantiene i requisiti Google gia previsti.

Dopo l'aggiornamento, ricaricare completamente il portale e riaprire Messaggi. Se il server ha ancora richieste lente della versione precedente in esecuzione, attendere che terminino prima di riprovare. La correzione non azzera le code e non ripete gli invii.

Verifiche superate: suite completa tools/verify.ps1, asset minificati, sanitizzazione e sintassi PHP. Verifiche dedicate: eventi correnti e passati, eventi odierni, priorita della data evento sulla chiusura, annullati e archiviati, autorizzazioni ristrette e vuote, elenco sintetico di 3000 eventi. Regressione della composizione, anteprima, conferma e prova. Confronto binario dei pacchetti con la 3.27.29: invariati gli altri 98 file, cambiati soltanto il caricamento interno a Messaggi e il numero di versione. Nessun invio reale e nessuna installazione sul sito; lo sblocco in produzione va verificato dopo l'aggiornamento.

Gli ZIP precedenti, incluso 3.26.226, restano invariati.
