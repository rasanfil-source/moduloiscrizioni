# Titoli nella scheda iscritto: entità HTML visibili

La scheda riceveva `event_title` da `MI_Management_Service::detail()` tramite `get_the_title()`, senza decodificare le entità HTML. Il frontend applica correttamente l'escaping prima di inserire il testo nell'HTML: un titolo contenente `&#8217;` e `&#8211;` veniva quindi mostrato con queste sequenze letterali. Il testo atteso è «L’Uomo nel progetto di Dio – Chi sono?».

## Origine e prevenzione delle ricomparse

Nella cronologia locale, già nel commit `a466af9` (3.26.135), `all_people()` decodificava il titolo mentre `detail()` non lo faceva. La correzione dell'elenco non copriva quindi la scheda. Non emerge una rimozione recente della decodifica in questo percorso; non è possibile attribuire alla stessa causa ogni precedente segnalazione di codifica nelle email o in altre schermate.

Il test integrato della gestione individuale usava un titolo privo di entità, «Pellegrinaggio ad Assisi», e non controllava il titolo restituito dal dettaglio. Questa lacuna permetteva al difetto di restare inosservato.

Elenco e dettaglio usano ora la stessa funzione per produrre testo Unicode nel JSON. L'escaping HTML nel browser resta attivo. Non occorre modificare i titoli salvati, il database o GAS.

La suite `wordpress-plugin/tests/individual-management-innodb.php` verifica ora entrambi i percorsi con apostrofo e lineetta numerici, ampersand, virgolette, accenti e testo già Unicode. Suite eseguita con successo su MariaDB locale sintetico. Correzione nei sorgenti: richiede una nuova distribuzione del plugin per essere attiva sul sito.
