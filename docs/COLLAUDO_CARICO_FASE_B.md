# Collaudo di carico della Fase B

## Obiettivo di accettazione

- tre eventi contemporanei con capacità di 100, 50 e 30 partecipanti;
- margine complessivo fino a 300 partecipanti;
- almeno 20 richieste concorrenti sugli ultimi posti;
- nessun superamento della capienza, doppio ordine o doppio addebito;
- retry della stessa chiave idempotente con restituzione dello stesso ordine;
- registrazione coerente di lista d'attesa, audit e replica Workspace.

## Stato

La suite isolata verifica lock, transazioni, limiti, idempotenza, anti-replay e
riconciliazione, ma non sostituisce un test concorrente contro WordPress, database
e Web App reali. Il collaudo di carico non è stato eseguito sul sito pubblico per
evitare iscrizioni, code email, scritture economiche e pressione volontaria sul
sistema di produzione.

## Ambiente necessario

Usare una copia di staging di WordPress e del database, tre eventi fittizi e un
deployment Workspace isolato in modalità `PREVIEW`. Disabilitare la consegna delle
email e usare soltanto identità sintetiche. Registrare per ogni esecuzione tempi,
codici HTTP, codici ordine, contatori finali, duplicati e righe di replica.

Il test è superato soltanto se i contatori finali rispettano tutte le capacità e
la ripetizione delle chiavi non produce nuove iscrizioni o movimenti.
