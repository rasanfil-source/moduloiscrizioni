# 3.26.205 — Riduzione dei caricamenti operativi

- Script gestione iscrizioni limitato a iscrizioni, dettaglio e gruppi; risorse pagamenti limitate alle schermate pagamenti. Il CSS gestione resta condiviso perché contiene stili usati dalla pagina Eventi.
- Pannelli camere e presenze costruiti alla prima apertura, una sola volta per riepilogo.
- Nessuna lettura automatica Google per mostrare Sincronizza: il controllo viene eseguito premendo il pulsante.
- Ricerca saldo immediatamente disponibile, senza ping, tentativi e attese preliminari.
- Dettaglio prenotazione senza HTML pagamenti e cronologie non utilizzate; riuso della registrazione già letta.
- Spedizione email accodata a un worker WordPress, senza trasporto nella risposta di iscrizione. Il cron già pianificato rimane valido; quando manca viene pianificato subito. La tempestività dipende dall’esecuzione del cron del sito.
- Risposte già paginate non passano più attraverso la compattazione del riepilogo e il controllo disponibilità presenze.

Verifiche: suite locale Node/PHP, build degli asset, sanitizzazione e prova browser dell’evento completo su desktop e mobile. Test dedicati per 18 combinazioni di caricamento e per la spedizione asincrona senza accesso a database o trasporto nella richiesta.

Limite: il riepilogo trasferisce ancora l’insieme compatto dei partecipanti per contatori, filtri e pannelli. La separazione completa in endpoint aggregati e dati caricati su richiesta, così come l’ottimizzazione della ricerca nominativa del saldo, richiede un intervento ulteriore. Non sono dichiarati tempi di miglioramento in produzione: le verifiche sono locali e sintetiche.

Aggiornamento solo WordPress; nessuna modifica o distribuzione Google Apps Script. Pacchetti locali preparati, non installati sul sito.
