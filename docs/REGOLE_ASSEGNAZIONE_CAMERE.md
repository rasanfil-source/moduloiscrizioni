# Regole di assegnazione delle camere

Queste regole descrivono come vengono assegnati i codici di sistemazione e quando interviene la segreteria. La tipologia scelta durante l’iscrizione esprime una richiesta; il codice di camera identifica l’assegnazione registrata.

## Sistemazione in multipla

La camerata multipla è considerata senza un limite numerico prefissato ai fini dell’assegnazione automatica. Ogni partecipante che sceglie la sistemazione multipla riceve automaticamente un codice individuale progressivo `M`, continuando la numerazione dell’evento. Se la numerazione parte da `M1`, con 45 partecipanti l’ultimo codice sarà `M45`.

Il codice `M` individua il posto assegnato nella camerata e non richiede di trovare un compagno o di completare una camera di capienza fissa. Questo comportamento è intenzionale.

## Doppie e triple

L’automatismo assegna insieme i partecipanti della stessa prenotazione che hanno scelto la stessa tipologia quando formano un gruppo completo: due persone per una doppia, tre per una tripla. Le persone che hanno scelto altre tipologie nella medesima prenotazione vengono gestite separatamente.

L’automatismo non divide un gruppo più numeroso in più camere e non abbina persone appartenenti a prenotazioni diverse. Se, per esempio, tre persone della stessa prenotazione scelgono una doppia, nessuna delle tre viene assegnata automaticamente; lo stesso vale per quattro persone che scelgono la doppia. La segreteria raccoglie le preferenze, decide chi condivide la camera e se abbinare qualcuno con persone di altre prenotazioni, quindi registra manualmente le assegnazioni nel portale.

Questa scelta evita abbinamenti automatici non concordati. La segreteria deve verificare la capienza e gli occupanti risultanti dopo il salvataggio.

## Verifica e registrazione

Nel portale, la tipologia richiesta e la camera assegnata sono dati distinti. Dopo un intervento manuale, controllare il codice della camera, tutte le persone assegnate e la capienza effettiva. Gli abbinamenti tra prenotazioni diverse restano sempre una decisione della segreteria.

Le regole sui progressivi `M` e sugli abbinamenti manuali per gruppi doppi o tripli incompleti o troppo numerosi sono coperte dai test di gestione camere in `wordpress-plugin/tests/management-innodb.php`.
