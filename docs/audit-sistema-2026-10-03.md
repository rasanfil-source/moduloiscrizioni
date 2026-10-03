# Analisi corrente — 3 ottobre 2026

Analizzati i sorgenti locali dichiarati 3.26.217, incluse le modifiche preesistenti non committate. Nessuna correzione applicativa o distribuzione eseguita in questa analisi. Questo rapporto sostituisce gli audit storici rimossi; non certifica l'assenza di altri difetti né lo stato dei deployment.

## P1 — I servizi a pagamento della modalità NONE vengono trattati come gratuiti

**Riprodotto con i metodi PHP reali e dati sintetici.** Nel wizard `NONE` significa “In base ai servizi scelti” (`class-mi-portal.php`, selettore `pricing_mode`), mentre `ZERO` significa “Evento totalmente gratuito”. La creazione dell'iscrizione conteggia i servizi quando il prezzo è `NONE` (`class-mi-registration-service.php:366`).

Le modifiche locali in `class-mi-management-service.php:708–711` azzerano invece i prezzi delle opzioni e la differenza del dovuto per entrambe le modalità. Caso riprodotto: una persona con pranzo da **20 €**, quota base zero, totale **20 €**, modalità `NONE/FULL_PAYMENT`. Rimuovere il pranzo produce `after_options=[]`, ma `delta=0` e `total_cents=2000`: il servizio sparisce e il dovuto rimane **20 €**. Aggiungere servizi allo stesso tipo di evento può analogamente azzerarne il prezzo invece di aggiornare il dovuto.

Lo stesso equivoco interessa il cambio sistemazione (`class-mi-management-service.php:482`), il dettaglio (`:142–153`, nasconde rettifica e accesso ai pagamenti) e la pulizia dei riferimenti al pagamento nelle email (`class-mi-modello-email.php:222–224`, `:804`). Questi percorsi sono verificati staticamente; la riproduzione eseguita riguarda la rimozione del pranzo.

**Correzione proposta:** riservare la gratuità a `ZERO`, verificare separatamente le prenotazioni senza importi e mantenere i ricalcoli di `NONE`. Aggiungere regressioni per creazione, aggiunta/rimozione servizi, cambio alloggio, dettaglio ed email. Non applicare la precedente proposta di equiparare indiscriminatamente `NONE` a `ZERO`: l'audit esterno storico interpretava erroneamente il significato del wizard.

Diagnostica locale: `.tmp/analisi-sistema-2026-10-03.php`. Non scrive nel database; le asserzioni dimostrano il difetto attuale, non il comportamento corretto.

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe -d extension_dir=.tmp/php-runtime/ext -d extension=mbstring .tmp/analisi-sistema-2026-10-03.php"
```

## P2 — Riaccoda dichiara successo anche per email non riaccodate

**Confermato dalla lettura del percorso corrente.** `class-mi-admin.php:804` mostra Riaccoda per `FAILED`, `TEST_FAILED`, `SENDING` e `TEST_SENDING`. `class-mi-spedizione-email.php:387` aggiorna però soltanto i primi due stati, senza controllare il numero di righe modificate, poi redirige con `mi_esito=riaccodata` (`:389`). Su un messaggio `SENDING` il comando può dunque confermare una riaccodatura senza aver modificato la riga.

La protezione contro invii duplicati è necessaria: un esito remoto incerto non autorizza a cancellare la ricevuta Google o cambiare automaticamente la chiave. Allineare comando e messaggio agli stati realmente trattabili, controllare l'esito SQL e prevedere un percorso esplicito di riconciliazione. Nessun invio reale eseguito.

## Questioni conservate dai precedenti audit

Queste voci restano utili allo sviluppo; non sono nuove riproduzioni della presente analisi:

- **Drive e cancellazione eventi:** un file definitivamente assente può lasciare incompleto il job; un errore `getFileById` può anche indicare permessi mancanti o indisponibilità. Progettare il recupero esplicito senza equiparare ogni errore a file già eliminato e senza abbandonare il riferimento silenziosamente.
- **Ambito delle colonne economiche Google:** importi individuali e subtotali per metodo riferiti all'intera prenotazione possono comparire sulla stessa prima riga. Chiarire etichette/ambiti evitando sia duplicazioni dei totali sia attribuzioni personali inventate.
- **Email con ricevuta remota incerta:** la riaccodatura WordPress non riconcilia una ricevuta Google `SENDING`. È distinto dal difetto del messaggio di successo descritto sopra.
- **Prestazioni e prove operative:** misure su database reale, carico concorrente e payload grandi restano nella [guida di sviluppo](SVILUPPO.md) e nel [piano di dismissione](piano-dismissione-db-moduli.md).

Restano intenzionali: progressivi individuali M nelle camerate; presenze storiche già rilevate anche dopo annullamento; annullamenti distinti da rettifiche/rimborsi; rotte di gestione legacy ritirate. Non modificarli sulla base degli audit rimossi.

## Verifiche eseguite

`tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe` completato: **394 test Node e 7 verifiche degli asset superati**, controllo degli asset generati, sanitizzazione, lint PHP e tutte le suite PHP previste dal verificatore. Log: `.tmp/analisi-sistema-2026-10-03-verify.log`; parte dell'output PHP viene emessa direttamente in console tramite `Out-Host`.

Riproduzione aggiuntiva PHP del caso `NONE`: riuscita dopo aver completato le funzioni WordPress simulate richieste dalla fixture. Nessuna esecuzione della campagna MariaDB/browser in questa sessione, nessuna consultazione di dati reali e nessun collaudo remoto. Il successo della suite ordinaria non copre il caso economico aggiuntivo.

Dopo la pulizia dei documenti e delle distribuzioni è stata rieseguita con successo la verifica completa; log `.tmp/analisi-sistema-2026-10-03-finale.log`. Corretta esclusivamente la gestione dei file eliminati nello strumento di sanitizzazione. Verificati anche i nuovi documenti, l'assenza di collegamenti Markdown interrotti e i nove checksum disponibili dei pacchetti conservati.
