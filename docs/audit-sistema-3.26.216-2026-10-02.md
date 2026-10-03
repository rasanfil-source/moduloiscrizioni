# Audit locale 3.26.216 — 2 ottobre 2026

Aggiornamento: i tre difetti qui descritti sono stati corretti localmente nella [3.26.217](rilascio-3.26.217.md), con test di regressione. Le sezioni seguenti conservano i risultati dell'audit precedente alla correzione.

Nuova revisione concentrata sulla gestione delle iscrizioni, sulla coerenza della paginazione e sui dati economici incompleti. Sono stati riprodotti tre difetti distinti. Nessuna modifica al codice applicativo effettuata durante questa revisione.

## 1. [P2] Posizioni economiche sconosciute incluse nel filtro Saldato

Riferimenti: `class-mi-management-list.php:27–34`, `class-mi-management-service.php:350–351` e `:382–386`; anche il filtro locale in `assets/portal-management.js:597` usa il solo saldo.

Quando mancano i dati storici necessari a ricostruire le quote individuali, il riepilogo espone `economics_known=false` ma mantiene valori economici di ripiego pari a zero. Il filtro Saldato controlla soltanto `balance <= 0`, includendo queste persone. I contatori del riepilogo, invece, escludono correttamente le posizioni sconosciute.

Riproduzione: ordine PENDING_PAYMENT con due persone attive, totale dovuto 20.000 centesimi, nessun pagamento e nessuna riga storica delle quote. Risultato: entrambe le posizioni sconosciute, saldi individuali zero, due risultati nel filtro Saldato e zero saldati nei contatori.

Impatto: la lista può presentare come saldate persone il cui saldo non è verificabile. Non è una modifica dei pagamenti registrati.

Correzione suggerita: conservare e verificare esplicitamente la disponibilità di quote e pagamenti prima di classificare un saldo; allineare i filtri PHP e JavaScript. Distinguere l'incertezza sulle caparre da quella sul saldo, per non nascondere saldi noti quando la sola caparra è incoerente.

## 2. [P2] Modifica durante la lettura non rilevata dal fingerprint

Riferimenti: `class-mi-management-service.php:161–165`, `:228–241`, `:272`; controllo client in `assets/portal-management.js:556–560`.

La versione dei dati viene letta prima delle query della pagina. Il percorso SQL riutilizza quella versione nel fingerprint senza verificarla nuovamente dopo la lettura dei dati. Una modifica intervenuta nel frattempo può quindi essere restituita con il fingerprint precedente, se il totale dei risultati resta invariato.

Riproduzione con interleaving controllato: prima pagina letta normalmente; nella pagina successiva, dopo la lettura della versione e prima della SELECT, modifica delle camere e incremento di workspace_revision. La pagina restituisce la camera CHANGED ma mantiene lo stesso fingerprint della prima pagina.

Impatto: il controllo client può accettare pagine o esportazioni che mescolano versioni diverse, senza chiedere di ricaricare. La prova riguarda il percorso senza riepilogo completo in cache.

Correzione suggerita: verificare nuovamente la versione dopo tutte le letture e rifiutare o ripetere la pagina se è cambiata; in alternativa usare una lettura coerente a livello di database. Coprire anche la scansione dei filtri avanzati. Aggiungere un test che cambi i dati durante una richiesta, non solo fra richieste completate.

## 3. [P2] Errore COUNT perso prima del controllo database

Riferimenti: `class-mi-management-service.php:206–214`; terminazione esportazione in `assets/portal-management.js:560`.

Il risultato COUNT viene convertito subito in intero; prima del controllo degli errori viene eseguita un'altra SELECT. Se COUNT fallisce e la SELECT successiva riesce, l'errore della prima query viene cancellato e il totale diventa zero. Il problema è presente nei rami persone e ordini.

Riproduzione: adattatore database che restituisce null e last_error per COUNT, poi azzera last_error alla successiva SELECT riuscita, come avviene con errori riferiti alla singola query. Risultato accettato: total=0 con 30 righe restituite.

Impatto: conteggio errato e possibile esportazione troncata alla prima pagina, perché il client termina quando il numero di righe raccolte raggiunge il totale dichiarato.

Correzione suggerita: controllare l'esito del COUNT immediatamente, prima della query successiva, e restituire un errore anziché una pagina valida. Aggiungere fault injection per entrambe le viste.

## Verifiche e limiti

Eseguite nuovamente con PHP locale e mbstring:

- `management-page-consistency.php`: superato.
- `management-deposit-totals.php`: superato.
- `economic-read-consistency.php`: superato.
- `.tmp/audit-216-pages.php`: riproduce i difetti 2 e 3.
- `.tmp/audit-216-unknown.php`: riproduce il difetto 1.

Gli script diagnostici verificano la presenza dei difetti e non costituiscono test di regressione del comportamento corretto. Utilizzano i metodi applicativi reali con fixture e adattatori database sintetici. Nessuna verifica su database MySQL reale, browser o ambiente di produzione in questa revisione; nessuna nuova esecuzione completa della suite. Il superamento dei test esistenti sopra elencati non copre i tre nuovi scenari.
